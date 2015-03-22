<?php
namespace React\Fcgi;

use Symfony\Component\HttpFoundation\Request;

/**
 * Simple handler that wraps callable that processes requests.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class CallableHandler extends Handler
{

	/** @var callable */
	private $callback;

	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	public function onRequest(Request $request, Connection $connection)
	{
		$callback = $this->callback;
		return $callback($request, $connection);
	}

}
