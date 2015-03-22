<?php
namespace React\Fcgi;

class ServerTest extends \PHPUnit_Framework_TestCase
{

	public function testListen()
	{
		$pipes = [];
		$proc = proc_open("php " . __DIR__ . "/Fixtures/hi.php", [], $pipes);

		$this->assertTrue(is_resource($proc));
		$this->assertEquals("hi", file_get_contents("http://localhost:8080/"));

		proc_close($proc);
	}

}
