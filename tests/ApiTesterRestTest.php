<?php

declare(strict_types=1);

namespace Atk4\Api\Tests;

use Atk4\Api\Api;
use Atk4\Api\Tests\Model\Country;
use Atk4\Schema\PhpunitTestCase;
use Laminas\Diactoros\Request;

class ApiTesterRestTest extends PhpunitTestCase
{
    /** @var Country */
    protected $model;

    /** @var Api */
    private $api;

    public function processRequest(Request $request)
    {
        $this->api = new Api($request);
        $this->api->emitter = false;
        $this->api->rest('/country', clone $this->model);

        return json_decode($this->api->response->getBody()->getContents(), true);
    }

    public function setupModel()
    {
        $this->model = new Country($this->db);
        $this->getMigrator($this->model)->create();
    }

    public function testAll()
    {
        $this->setupModel();

        // Create new record
        $data = [
            'name' => 'test',
            'sys_name' => 'test',
            'iso' => 'IT',
            'iso3' => 'ITA',
            'numcode' => 666,
            'phonecode' => 39,
        ];

        $request = new Request(
            'http://localhost/country',
            'POST',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertSame(201, $this->api->response_code);
        $this->assertSame([
            'id' => 1,
            'name' => 'test',
            'sys_name' => 'test',
            'iso' => 'IT',
            'iso3' => 'ITA',
            'numcode' => 666,
            'phonecode' => 39,
        ], $response);

        // Request one record by id
        $request = new Request(
            'http://localhost/country/1',
            'GET',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertSame([
            'id' => 1,
            'name' => 'test',
            'sys_name' => 'test',
            'iso' => 'IT',
            'iso3' => 'ITA',
            'numcode' => 666,
            'phonecode' => 39,
        ], $response);

        // Request one record by value of some other field
        $request = new Request(
            'http://localhost/country/name:test',
            'GET',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertSame([
            'id' => 1,
            'name' => 'test',
            'sys_name' => 'test',
            'iso' => 'IT',
            'iso3' => 'ITA',
            'numcode' => 666,
            'phonecode' => 39,
        ], $response);

        // Request all records
        $request = new Request(
            'http://localhost/country',
            'GET',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertSame([
            0 => [
                'id' => 1,
                'nicename' => 'test',
                'name' => 'test',
                'iso' => 'IT',
                'iso3' => 'ITA',
                'numcode' => 666,
                'phonecode' => 39,
            ],
        ], $response);

        // Modify record data
        $request = new Request(
            'http://localhost/country/1',
            'GET',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        $data = $this->processRequest($request);
        $data['name'] = 'test modified';

        $request = $request->withMethod('POST');
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertSame([
            'id' => 1,
            'name' => 'test modified',
            'sys_name' => 'test',
            'iso' => 'IT',
            'iso3' => 'ITA',
            'numcode' => 666,
            'phonecode' => 39,
        ], $response);

        // Delete record
        $request = new Request(
            'http://localhost/country/1',
            'DELETE',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        $this->processRequest($request);

        // check via getAll
        $request = new Request(
            'http://localhost/country',
            'GET',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertSame([], $response);

        // Limit available model fields by using apiFields property
        $this->model->apiFields = [
            'read' => [
                'name',
                'iso',
                'numcode',
            ],
        ];

        $data = [
            'name' => 'test',
            'sys_name' => 'test',
            'iso' => 'IT',
            'iso3' => 'ITA',
            'numcode' => 666,
            'phonecode' => 39,
        ];

        $request = new Request(
            'http://localhost/country',
            'POST',
            'php://memory',
            [
                'Content-Type' => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertSame(201, $this->api->response_code);
        $this->assertSame([
            'name' => 'test',
            'iso' => 'IT',
            'numcode' => 666,
        ], $response);
    }
}
