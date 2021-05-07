<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CurlTest extends TestCase {
    public function testGet() : void
    {
        $http = new CurlWrapper\Curl;
        $response = $http->get('http://httpbin.org/get', array('test' => 1));
        $json = $response->json();

        $this->assertEquals($json->args->test, 1);
    }
}
