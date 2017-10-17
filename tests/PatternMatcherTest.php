<?php

namespace atk4\api\tests;

class PatternMatcherTest extends \atk4\core\PHPUnit_AgileTestCase
{
    public $api;
    public function setUp()
    {
        $this->api = new \atk4\api\Api();
    }

    public function assertMatch($pattern, $request)
    { 
        $this->api->path = $request;
        $this->assertTrue($this->api->match($pattern));
    }

    public function assertNoMatch($pattern, $request)
    {
        $this->api->path = $request;
        $this->assertFalse($this->api->match($pattern));
    }

    public function testBasic()
    {
        $this->assertMatch('/', '/');
        $this->assertMatch('/hello', '/hello');
        $this->assertMatch('/hello', '/hello/');
        $this->assertMatch('/hello/', '/hello');

        $this->assertNoMatch('/hello', '/world');
        $this->assertNoMatch('/hello//world', '/hello/world');
        $this->assertNoMatch('/hello/world', '/hello//world');
    }

    public function testAsterisk()
    {
        $this->assertNoMatch('/*', '/');
        $this->assertMatch('/*', '/hello');
        $this->assertNoMatch('/*', '/hello/world');

        $this->assertNoMatch('/test/*', '/test');
        $this->assertNoMatch('/test/*', '/test/');
        $this->assertMatch('/test/*', '/test/something');
        $this->assertNoMatch('/test/*', '/test/something/else');

        $this->assertMatch('/test/*/abc', '/test/bah/abc');
        $this->assertNoMatch('/test/*/abc', '/test/bah/cba');
        $this->assertNoMatch('/test/*/abc', '/test/abc');
        $this->assertNoMatch('/test/*/abc', '/test//abc');
        $this->assertMatch('/test/*/abc', '/test/*/abc');
    }

    public function testParam()
    {
        $this->assertNoMatch('/:', '/');
        $this->assertMatch('/:', '/hello');
        $this->assertNoMatch('/:', '/hello/world');

        $this->assertNoMatch('/test/:', '/test');
        $this->assertNoMatch('/test/:', '/test/');
        $this->assertMatch('/test/:', '/test/something');
        $this->assertNoMatch('/test/:', '/test/something/else');

        $this->assertMatch('/test/:/abc', '/test/bah/abc');
        $this->assertNoMatch('/test/:/abc', '/test/bah/cba');
        $this->assertNoMatch('/test/:/abc', '/test/abc');
        $this->assertNoMatch('/test/:/abc', '/test//abc');
        $this->assertMatch('/test/:/abc', '/test/*/abc');
    }

    public function testDoubleAsterisk()
    {
        $this->assertMatch('/**', '/');
        $this->assertMatch('/**', '/hello');
        $this->assertMatch('/**', '/hello/world');

        $this->assertMatch('/test/**', '/test');
        $this->assertMatch('/test/**', '/test/');
        $this->assertMatch('/test/**', '/test/something');
        $this->assertMatch('/test/**', '/test/something/else');

        $this->assertNoMatch('/test/**', '/else');
    }
}
