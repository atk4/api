<?php

declare(strict_types=1);

namespace Atk4\Api\Tests;

use Atk4\Schema\PhpunitTestCase;
use Laminas\Diactoros\Request;

class ApiTester extends PhpunitTestCase
{
    public $api;

    protected function setUp(): void
    {
        $this->api = new \Atk4\Api\Api();
    }

    public function assertRequest($response, $method, $uri = '/', $data = null)
    {
        $request = new Request(
            'http://localhost' . $uri,
            $method,
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        if ($data !== null) {
            $request->getBody()->write(json_encode($data));
        }
    }

    /**
     * Simulates a request to $uri using $method and with $data body as it's
     * passed into an Api class. Execute callback $apiBuild afterwards allowing
     * you to define your custom handlers. Match response from the API against
     * the $response and if it is different - create assertion error.
     *
     * @param string   $response
     * @param callable $apiBuild
     * @param string   $uri
     * @param string   $method
     * @param array    $data
     */
    public function assertApi($response, $apiBuild, $uri = '/ping', $method = 'GET', $data = null)
    {
        // create fake request
        $request = new Request(
            'http://localhost' . $uri,
            $method,
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        if ($data !== null) {
            $request->getBody()->write(json_encode($data));
        }

        $api = new \Atk4\Api\Api($request);
        $api->emitter = false; // don't emmit response

        $apiBuild($api);

        $ret = json_decode($api->response->getBody()->getContents(), true);
        $this->assertSame($response, $ret);
    }

    /**
     * Simulate a request and validate a response.
     *
     * @param string   $response
     * @param callable $handler
     * @param string   $method
     * @param array    $data
     */
    public function assertReq($response, $handler, $method = 'GET', $data = null)
    {
        $uri = '/request';

        $m = strtolower($method);
        $this->assertApi($response, function ($api) use ($handler, $uri, $m) {
            $api->{$m}($uri, $handler);
        }, $uri, $method);
    }
}
