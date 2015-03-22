<?php
namespace React\Fcgi\Fixtures;

use React\EventLoop\Factory;
use React\Fcgi\Server;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . "/../../../../vendor/autoload.php";

$loop = Factory::create();

$server = new Server($loop, function (Request $request) {
	return new Response("hi");
});

$server->listen(9000);

$loop->run();
