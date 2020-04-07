<?php

namespace atk4\api\tests;

use atk4\api\Api;
use atk4\data\Persistence;
use atk4\schema\Migration;
use Laminas\Diactoros\Request;

class ApiTesterRestTest extends \atk4\core\PHPUnit_AgileTestCase
{
    /** @var Persistence\SQL */
    protected $db;

    /** @var Country */
    protected $model;

    /** @var Api */
    private $api;

    public static function tearDownAfterClass()
    {
        unlink('./sqlite.db');
    }

    public function setUp()
    {
        parent::setUp();
        touch('./sqlite.db');

        $this->db = new Persistence\SQL('sqlite:./sqlite.db');
        $this->model = new Country($this->db);
        Migration::of($this->model)->run();
    }

    public function processRequest(Request $request)
    {
        $this->api = new Api($request);
        $this->api->emitter = false;
        $this->api->rest('/client', $this->model);

        return json_decode($this->api->response->getBody()->getContents(), true);
    }

    public function testCreate()
    {
        $data = [
            'name'      => 'test',
            'sys_name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
        ];

        $request = new Request(
            'http://localhost/client',
            'POST',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertEquals(201, $this->api->response_code);
        $this->assertEquals([
            'id'        => '1',
            'name'      => 'test',
            'sys_name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
        ], $response);
    }

    public function testGETOne()
    {
        $request = new Request(
            'http://localhost/client/1',
            'GET',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertEquals([
            'id'        => '1',
            'name'      => 'test',
            'sys_name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
        ], $response);
    }

    public function testGETOneByField()
    {
        $request = new Request(
            'http://localhost/client/name:test',
            'GET',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertEquals([
            'id'        => '1',
            'name'      => 'test',
            'sys_name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
        ], $response);
    }

    public function testGETAll()
    {
        $request = new Request(
            'http://localhost/client',
            'GET',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertEquals([
            0 => [
                'id'        => '1',
                'name'      => 'test',
                'nicename'  => 'test',
                'iso'       => 'IT',
                'iso3'      => 'ITA',
                'numcode'   => '666',
                'phonecode' => '39',
            ],
        ], $response);
    }

    public function testModify()
    {
        $request = new Request(
            'http://localhost/client/1',
            'GET',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );

        $data = $this->processRequest($request);
        $data['name'] = 'test modified';

        $request = $request->withMethod('POST');
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertEquals([
            'id'        => '1',
            'name'      => 'test modified',
            'sys_name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
        ], $response);
    }

    public function testDelete()
    {

        // delete record
        $request = new Request(
            'http://localhost/client/1',
            'DELETE',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );

        $this->processRequest($request);

        // check via getAll
        $request = new Request(
            'http://localhost/client',
            'GET',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );

        $response = $this->processRequest($request);
        $this->assertEquals([], $response);
    }

    public function testOnlyApiFields()
    {
        $this->model->apiFields = [
            'read' => [
                'name',
                'iso',
                'numcode',
            ],
        ];

        $data = [
            'name'      => 'test',
            'sys_name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
        ];

        $request = new Request(
            'http://localhost/client',
            'POST',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertEquals(201, $this->api->response_code);
        $this->assertEquals([
            'name'    => 'test',
            'iso'     => 'IT',
            'numcode' => '666',
        ], $response);
    }
}

class Country extends \atk4\data\Model
{
    public $table = 'country';
    /*
        public $apiFields = [
            'read' => [
                'id',
                'name',
                'sys_name',
                'iso',
                'iso3',
                'numcode',
                'phonecode',
            ]
        ];
    */

    /**
     * @throws \atk4\core\Exception
     */
    public function init()
    {
        parent::init();
        $this->addField('name', ['actual'=>'nicename', 'required'=>true, 'type'=>'string']);
        $this->addField('sys_name', ['actual'=>'name', 'system'=>true]);

        $this->addField('iso', ['caption'=>'ISO', 'required'=>true, 'type'=>'string']);
        $this->addField('iso3', ['caption'=>'ISO3', 'required'=>true, 'type'=>'string']);
        $this->addField('numcode', ['caption'=>'ISO Numeric Code', 'type'=>'number', 'required'=>true]);
        $this->addField('phonecode', ['caption'=>'Phone Prefix', 'type'=>'number']);

        $this->onHook('beforeSave', function ($m) {
            if (!$m['sys_name']) {
                $m['sys_name'] = strtoupper($m['name']);
            }
        });

        $this->onHook('validate', function ($m) {
            $errors = [];

            if (strlen($m['iso']) !== 2) {
                $errors['iso'] = 'Must be exactly 2 characters';
            }

            if (strlen($m['iso3']) !== 3) {
                $errors['iso3'] = 'Must be exactly 3 characters';
            }

            // look if name is unique
            $c = clone $m;
            $c->unload();
            $c->tryLoadBy('name', $m['name']);
            if ($c->loaded() && $c->id != $m->id) {
                $errors['name'] = 'Country name must be unique';
            }

            return $errors;
        });
    }
}
