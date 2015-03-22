<?php
namespace React\Fcgi;

use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FastCGI server handler.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface HandlerInterface
{

	/**
	 * Called when new connection is opened.
	 *
	 * @param Connection $connection
	 * @return void
	 */
	public function onOpen(Connection $connection);

	/**
	 * Called to get HTTP response.
	 *
	 * If method returns promise, promise's resolved value must return response object.
	 *
	 * @param Request $request
	 * @param Connection $connection
	 * @return Response|PromiseInterface
	 */
	public function onRequest(Request $request, Connection $connection);

	/**
	 * Called when connection is being closed.
	 *
	 * @param Connection $connection
	 * @return void
	 */
	public function onClose(Connection $connection);

	/**
	 * Called upon errors.
	 *
	 * @param \Exception $e
	 * @param Connection $connection
	 * @return void
	 */
	public function onError(\Exception $e, Connection $connection = null);

}
