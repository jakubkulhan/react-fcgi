<?php
namespace React\Fcgi;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base handler adapter. Implements method from interface with default implementation.
 *
 * - onOpen(), onClose() - empty implementation.
 * - onRequest() - returns empty response.
 * - onError() throws given exception.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Handler implements HandlerInterface
{

	public function onOpen(Connection $connection)
	{
	}

	public function onRequest(Request $request, Connection $connection)
	{
		return Response::create();
	}

	public function onClose(Connection $connection)
	{
	}

	public function onError(\Exception $e, Connection $connection = null)
	{
		throw $e;
	}

}
