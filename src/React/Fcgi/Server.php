<?php
namespace React\Fcgi;

use React\EventLoop\LoopInterface;
use React\Fcgi\Exception\ConnectionException;

/**
 * FastCGI application server class. Handles listening socket - accepts incoming connections, creates {@link Connection}.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Server
{

	/** @var LoopInterface */
	private $loop;

	/** @var HandlerInterface */
	private $handler;

	/** @var resource */
	private $socket;

	/**
	 * Constructor.
	 *
	 * @param LoopInterface $loop
	 * @param HandlerInterface|callable $handler
	 */
	public function __construct(LoopInterface $loop, $handler)
	{
		$this->loop = $loop;

		if (is_callable($handler)) {
			$this->handler = new CallableHandler($handler);
		} elseif ($handler instanceof HandlerInterface) {
			$this->handler = $handler;
		} else {
			throw new \InvalidArgumentException(
				"Parameter 'handler' has to be either instance of HandlerInterface, or callable."
			);
		}
	}

	/**
	 * Starts listening on given port (eventually host).
	 *
	 * @param int $port
	 * @param string $host
	 */
	public function listen($port, $host = "127.0.0.1")
	{
		if (strpos($host, ":") !== false) {
			$host = "[{$host}]";
		}

		$this->socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
		if ($this->socket === false) {
			$this->handler->onError(new ConnectionException(
				"Could not bind listen socket to {$host}:{$port}: #{$errno} - {$errstr}."
			));
		}
		stream_set_blocking($this->socket, 0);

		$this->loop->addReadStream($this->socket, function () {
			$this->onAccept();
		});
	}

	/**
	 * Called when new connection arrives.
	 */
	public function onAccept()
	{
		$stream = @stream_socket_accept($this->socket);
		if ($stream === false) {
			$this->handler->onError(new ConnectionException("Could not accept() connection: " . error_get_last() . "."));
		} else {
			$this->handler->onOpen(new Connection($this->loop, $this->handler, $stream));
		}
	}

}
