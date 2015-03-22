# React\Fcgi

[![Build Status](https://travis-ci.org/jakubkulhan/react-fcgi.svg?branch=master)](https://travis-ci.org/jakubkulhan/react-fcgi)

> Asynchronous FastCGI server built on ReactPHP

## Requirements

React\Fcgi requires PHP `>= 5.4.0`.

## Installation

Add as [Composer](https://getcomposer.org/) dependency:

```sh
$ composer require react/fcgi:@dev
```

## Usage


```php
use React\EventLoop\Factory;
use React\Fcgi\Server;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$loop = Factory::create();

$server = new Server($loop, function (Request $request) {
	return new Response("hi");
});

$server->listen(9000);

$loop->run();

```

## License

React\Fcgi is licensed under MIT license. See `LICENSE` file.
