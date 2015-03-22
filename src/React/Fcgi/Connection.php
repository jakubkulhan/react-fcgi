<?php
namespace React\Fcgi;

use React\EventLoop\LoopInterface;
use React\Fcgi\Exception\BadResponseException;
use React\Fcgi\Exception\ConnectionException;
use React\Fcgi\Exception\ResponsePromiseRejectedException;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connection class wraps single connection from web server to application (FastCGI server).
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Connection
{

	/** @var LoopInterface */
	private $loop;

	/** @var HandlerInterface */
	private $handler;

	/** @var resource */
	private $stream;

	/** @var boolean */
	private $closed = false;

	/** @var boolean */
	private $keepAlive = false;

	/** @var boolean */
	private $flushing = false;

	/** @var string */
	private $readBuffer;

	/** @var string */
	private $writeBuffer;

	/** @var string */
	private $contentBuffer;

	/** @var array */
	private $requests = [];

	/** @var array */
	private $fcgiValues = [
		Constants::VALUE_FCGI_MPXS_CONNS => "1",
	];

	/**
	 * Constructor.
	 *
	 * @param LoopInterface $loop
	 * @param HandlerInterface $handler
	 * @param resource $stream accepted connection
	 */
	public function __construct(LoopInterface $loop, HandlerInterface $handler, $stream)
	{
		$this->loop = $loop;
		$this->handler = $handler;
		$this->stream = $stream;
		$this->readBuffer = "";
		$this->writeBuffer = "";
		$this->contentBuffer = "";

		if (!is_resource($this->stream)) {
			throw new \InvalidArgumentException(
				"Expected stream to be resource, got " . gettype($this->stream) .
				(is_object($this->stream) ? " of class " . get_class($this->stream) : "") .
				"."
			);
		}

		stream_set_blocking($this->stream, 0);

		$this->loop->addReadStream($this->stream, [$this, "onReadAvailable"]);
	}

	/**
	 * If web server issues GET-VALUES request, set value to be returned.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function setFcgiValue($key, $value)
	{
		$this->fcgiValues[$key] = (string)$value;
	}

	/**
	 * Called by React's event loop when there data to be read
	 */
	public function onReadAvailable()
	{
		$s = @fread($this->stream, 0xffff);

		if ($s === false || @feof($this->stream)) {
			$this->close();

		} else {
			$this->readBuffer .= $s;

			while (($l = strlen($this->readBuffer)) >= 8) {
				$frame = unpack("Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength", $this->readBuffer);
				$type = $frame["type"];
				$requestId = $frame["requestId"];
				$contentLength = $frame["contentLength"];
				$paddingLength = $frame["paddingLength"];

				if ($l < 8 + $contentLength + $paddingLength) {
					break;
				}

				$this->contentBuffer = substr($this->readBuffer, 8, $contentLength);
				$this->readBuffer = substr($this->readBuffer, 8 + $contentLength + $paddingLength) ?: "";

				if ($frame["version"] !== 1) {
					$this->handler->onError(
						new ConnectionException("Protocol version mismatch. Expected 1, got {$frame["version"]}."),
						$this
					);
					continue;
				}

				if ($type === Constants::TYPE_PARAMS) {
					$this->onParamsFrame($requestId);

				} elseif ($type === Constants::TYPE_STDIN) {
					$this->onStdinFrame($requestId);

				} elseif ($type === Constants::TYPE_BEGIN_REQUEST) {
					$this->onBeginRequestFrame($requestId);

				} elseif ($type === Constants::TYPE_ABORT_REQUEST) {
					$this->onAbortRequestFrame($requestId);

				} elseif ($type === Constants::TYPE_GET_VALUES) {
					if ($requestId !== Constants::NULL_REQUEST_ID) {
						$this->closeWithError("Received frame GET-VALUES on non-null-request id channel.");
					} else {
						$this->onGetValuesFrame();
					}

				} elseif ($type === Constants::TYPE_END_REQUEST) {
					$this->closeWithError("Received unexpected frame: END-REQUEST.");

				} elseif ($type === Constants::TYPE_STDOUT) {
					$this->closeWithError("Received unexpected frame: STDOUT.");

				} elseif ($type === Constants::TYPE_STDERR) {
					$this->closeWithError("Received unexpected frame: STDERR.");

				} elseif ($type === Constants::TYPE_DATA) {
					$this->onDataFrame($requestId);

				} elseif ($type === Constants::TYPE_GET_VALUES_RESULT) {
					$this->closeWithError("Received unexpected frame: GET-VALUES-RESULT.");

				} elseif ($type === Constants::TYPE_UNKNOWN_TYPE) {
					$this->contentBuffer = "";

				} else {
					$this->closeWithError("Unhandled request type #{$type}, closing connection.");
				}

				if (!$this->closed && !empty($this->contentBuffer)) {
					$this->closeWithError("Content buffer hasn't been fully consumed.");
				}
			}
		}
	}

	/**
	 * Called when data from write buffer can be sent.
	 */
	public function onWriteAvailable()
	{
		if (($written = @fwrite($this->stream, $this->writeBuffer)) === false) {
			$this->closeWithError("Could not write data to socket.");
			return;
		}

		if ($written === 0) {
			$this->closeWithError("Broken pipe or closed connection.");
			return;
		}

		$this->writeBuffer = substr($this->writeBuffer, $written) ?: "";

		if (empty($this->writeBuffer)) {
			$this->flushing = false;
			$this->loop->removeWriteStream($this->stream);
		}
	}

	/**
	 * Closes connection.
	 */
	public function close()
	{
		$this->closed = true;
		$this->handler->onClose($this);
		$this->loop->removeReadStream($this->stream);
		$this->loop->removeWriteStream($this->stream);
		@fclose($this->stream);
	}

	/**
	 * Calls handler's onError() method and then closes connection.
	 *
	 * @param string $msg
	 */
	private function closeWithError($msg)
	{
		$this->handler->onError(new ConnectionException($msg), $this);
		$this->close();
	}

	/**
	 * Handles BEGIN-REQUEST frame.
	 *
	 * @param int $requestId
	 */
	private function onBeginRequestFrame($requestId)
	{
		if (isset($this->requests[$requestId])) {
			$this->closeWithError("Received double BEGIN-REQUEST for #{$requestId}.");
			return;
		}

		$data = unpack("nrole/Cflags", $this->contentBuffer);
		$this->contentBuffer = "";

		if ($data["role"] !== Constants::ROLE_RESPONDER) {
			// FIXME: respond with END-REQUEST protocol status unknown role?
			$this->closeWithError("Only RESPONDER role is supported.");
			return;
		}

		$this->keepAlive = $data["flags"] & Constants::FLAG_KEEP_CONNECTION > 0;
		$this->requests[$requestId] = ["", "", []];
	}

	/**
	 * Handles PARAMS frame.
	 *
	 * @param int $requestId
	 */
	private function onParamsFrame($requestId)
	{
		if (!isset($this->requests[$requestId])) {
			$this->closeWithError("Received PARAMS frame on un-begun request #{$requestId}.");
			return;
		}

		if (empty($this->contentBuffer)) {
			$this->requests[$requestId][2] = $this->readKeyValues($this->requests[$requestId][0]);
		} else {
			$this->requests[$requestId][0] .= $this->contentBuffer;
			$this->contentBuffer = "";
		}
	}

	/**
	 * Handles STDIN frame.
	 *
	 * @param int $requestId
	 */
	private function onStdinFrame($requestId)
	{
		if (!isset($this->requests[$requestId])) {
			$this->closeWithError("Received STDIN frame on un-begun request #{$requestId}.");
			return;
		}

		if (empty($this->contentBuffer)) {
			list(, $content, $server) = $this->requests[$requestId];
			$query = [];
			if (isset($server["QUERY_STRING"])) {
				parse_str($server["QUERY_STRING"], $query);
			} elseif (isset($server["REQUEST_URI"]) && ($p = strpos($server["REQUEST_URI"], "?")) !== false) {
				parse_str(substr($server["REQUEST_URI"], $p + 1) ?: "", $query);
			}

			$cookies = [];
			if (isset($server["HTTP_COOKIE"])) {
				foreach (explode(";", $server["HTTP_COOKIE"]) as $cookie) {
					list($name, $value) = explode("=", $cookie, 2);
					$cookies[urldecode(trim($name))] = urldecode(trim($value));
				}
			}

			$request = [];
			if (isset($server["CONTENT_TYPE"]) && $server["CONTENT_TYPE"] === "application/x-www-form-urlencoded") {
				parse_str($content, $request);
			}
			// TODO: multipart form data => files

			$request = new Request($query, $request, [], $cookies, [], $server, $content);

			$response = $this->handler->onRequest($request, $this);

			if ($response instanceof Response) {
				$this->completeRequest($requestId, Constants::APP_STATUS_OK, $response);

			} elseif ($response instanceof PromiseInterface) {
				$response->then(function (Response $response) use ($requestId) {
					$this->completeRequest($requestId, Constants::APP_STATUS_OK, $response);

				}, function () use ($requestId) {
					$this->handler->onError(new ResponsePromiseRejectedException(), $this);
					$response = new Response("500 Internal Server Error", 500);
					$this->completeRequest($requestId, Constants::APP_STATUS_ERROR, $response);
				});

			} else {
				$this->handler->onError(new BadResponseException(), $this);
				$response = new Response("500 Internal Server Error", 500);
				$this->completeRequest($requestId, Constants::APP_STATUS_ERROR, $response);
			}

		} else {
			$this->requests[$requestId][1] .= $this->contentBuffer;
			$this->contentBuffer = "";
		}
	}

	/**
	 * Handles DATA frame.
	 *
	 * DATA frames are just discarded for now.
	 *
	 * @param int $requestId
	 */
	private function onDataFrame($requestId)
	{
		if (!isset($this->requests[$requestId])) {
			$this->closeWithError("Received DATA frame on un-begun request #{$requestId}.");
			return;
		}

		// we don't want data frames => discard
		$this->contentBuffer = "";
	}

	/**
	 * Handles GET-VALUES frame.
	 */
	private function onGetValuesFrame()
	{
		$requestValues = $this->readKeyValues($this->contentBuffer);
		$responseValues = [];
		foreach ($this->fcgiValues as $k => $v) {
			if (isset($requestValues[$k])) {
				$responseValues[$k] = $v;
			}
		}

		$buffer = $this->writeKeyValues($requestValues);
		$this->writeFrame(Constants::TYPE_GET_VALUES_RESULT, Constants::NULL_REQUEST_ID, strlen($buffer), $buffer);
	}

	/**
	 * Handles ABORT-REQUEST frame.
	 *
	 * @param int $requestId
	 */
	private function onAbortRequestFrame($requestId)
	{
		if (!isset($this->requests[$requestId])) {
			$this->closeWithError("Received ABORT-REQUEST frame on un-begun request #{$requestId}.");
			return;
		}

		unset($this->requests[$requestId]);
	}

	/**
	 * Serializes response to STDOUT/END-REQUEST frames and flushes them.
	 *
	 * If server didn't want keep-alive connections, connection is closed.
	 *
	 * @param int $requestId
	 * @param int $appStatus
	 * @param Response $response
	 */
	private function completeRequest($requestId, $appStatus, Response $response)
	{
		if (!is_string($response->getContent())) {
			$this->closeWithError("Response does not have content.");
			return;
		}

		$stdout = "";

		$stdout .= "Status: " . $response->getStatusCode() .
			(isset(Response::$statusTexts[$response->getStatusCode()])
				? " " . Response::$statusTexts[$response->getStatusCode()]
				: "") .
			"\r\n";

		foreach ($response->headers->allPreserveCase() as $key => $values) {
			foreach ($values as $value) {
				$stdout .= "{$key}: {$value}\r\n";
			}
		}

		foreach ($response->headers->getCookies() as $cookie) {
			$stdout .= "Set-Cookie: {$cookie}\r\n";
		}

		$stdout .= "\r\n";
		$stdout .= $response->getContent();

		foreach (str_split($stdout, 0xffff) as $chunk) {
			$this->writeFrame(Constants::TYPE_STDOUT, $requestId, strlen($chunk), $chunk);
		}
		$this->writeFrame(Constants::TYPE_STDOUT, $requestId);
		$this->writeFrame(Constants::TYPE_END_REQUEST, $requestId, 8, pack("NCCCC", $appStatus, Constants::PROTOCOL_STATUS_COMPLETE, 0, 0, 0));

		unset($this->requests[$requestId]);

		if (!$this->keepAlive) {
			$this->close();
		}
	}

	/**
	 * Reads FastCGI name-value pairs from given string.
	 *
	 * @param string $buffer
	 * @return array
	 */
	private function readKeyValues($buffer)
	{
		$arr = [];
		$off = 0;
		$len = strlen($buffer);
		while ($off < $len) {
			list(, $keyLength, $valueLength) = unpack("C2", substr($buffer, $off, 2));

			if (($keyLength >> 7) > 0) {
				list(, $keyLength, $valueLength) = unpack("N2", substr($buffer, $off, 8));
				$keyLength &= 0x7fffffff;
				if (($valueLength >> 31) > 0) {
					$valueLength &= 0x7fffffff;
					$off += 8;
				} else {
					list(, $valueLength) = unpack("C", substr($buffer, $off + 4, 1));
					$off += 5;
				}
			} elseif (($valueLength >> 7) > 0) {
				list(, $valueLength) = unpack("N", substr($buffer, $off + 1, 4));
				$valueLength &= 0x7fffffff;
				$off += 5;
			} else {
				$off += 2;
			}

			$arr[substr($buffer, $off, $keyLength)] = substr($buffer, $off + $keyLength, $valueLength);
			$off += $keyLength + $valueLength;
		}
		return $arr;
	}

	/**
	 * Writes FastCGI's name-value pairs to string.
	 *
	 * @param array $arr
	 * @return string
	 */
	private function writeKeyValues(array $arr)
	{
		$buffer = "";
		foreach ($arr as $key => $value) {
			$keyLength = strlen($key);
			$valueLength = strlen($value);
			if ($keyLength <= 0x7f) {
				$buffer .= pack("C", $keyLength);
			} else {
				$buffer .= pack("N", $keyLength | 0x80000000);
			}
			if ($valueLength <= 0x7f) {
				$buffer .= pack("C", $valueLength);
			} else {
				$buffer .= pack("N", $keyLength | 0x80000000);
			}
			$buffer .= $key;
			$buffer .= $value;
		}
		return $buffer;
	}

	/**
	 * Writes specified type of frame to write buffer and adds stream for loop to be writable.
	 *
	 * @param int $type
	 * @param int $requestId
	 * @param int $contentLength
	 * @param string $content
	 */
	private function writeFrame($type, $requestId, $contentLength = 0, $content = "")
	{
		$this->writeBuffer .= pack(
			"CCnnCCa*a*",
			Constants::VERSION,
			$type,
			$requestId,
			$contentLength,
			$paddingLength = 8 - ($contentLength % 8),
			0,
			$content,
			str_repeat("\x00", $paddingLength)
		);

		if (!$this->flushing) {
			$this->flushing = true;
			$this->loop->addWriteStream($this->stream, [$this, "onWriteAvailable"]);
		}
	}

}
