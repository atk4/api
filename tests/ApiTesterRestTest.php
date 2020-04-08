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

    /** @var Api */
    protected $api;

    protected static $init = false;

    public static function tearDownAfterClass()
    {
        unlink(__DIR__ . '/sqlite.db');
    }

    public function setUp()
    {
        parent::setUp();
        $filename = __DIR__ . '/sqlite.db';
        touch($filename);

        $this->db = new Persistence\SQL('sqlite:' . $filename);
        if(!self::$init) {
            self::$init = true;
            Migration::of(new Country($this->db))->run();
        }
    }

    public function processRequest(Request $request, $model = null)
    {

        $model_default = new Country($this->db);

        $this->api = new Api($request);
        $this->api->emitter = false;
        $this->api->rest('/client', $model ?? $model_default);

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
            'date'  => null,
            'datetime'  => null,
            'time'      => null
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
            'nicename'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
            'date'  => null,
            'datetime'  => null,
            'time'      => null
        ], $response);
    }

    public function testCreate2()
    {
        $data = [
            'name'      => 'test2',
            'sys_name'  => 'test2',
            'iso'       => 'DE',
            'iso3'      => 'DEU',
            'numcode'   => '999',
            'phonecode' => '43',
            'date'  => null,
            'datetime'  => null,
            'time'      => null
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
            'id'        => '2',
            'name'      => 'test2',
            'nicename'  => 'test2',
            'iso'       => 'DE',
            'iso3'      => 'DEU',
            'numcode'   => '999',
            'phonecode' => '43',
            'date'  => null,
            'datetime'  => null,
            'time'      => null
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
            'nicename'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
            'date'  => null,
            'datetime'  => null,
            'time'      => null
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
            [
                'id'        => '1',
                'name'      => 'test',
                'nicename'  => 'test',
                'iso'       => 'IT',
                'iso3'      => 'ITA',
                'numcode'   => '666',
                'phonecode' => '39',
                'date'  => null,
                'datetime'  => null,
                'time'      => null
            ],
            [
                'id'        => '2',
                'name'      => 'test2',
                'nicename'  => 'test2',
                'iso'       => 'DE',
                'iso3'      => 'DEU',
                'numcode'   => '999',
                'phonecode' => '43',
                'date'  => null,
                'datetime'  => null,
                'time'      => null
            ]
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
        $data['sys_name'] = 'test modified';
        $data['name'] = $data['nicename'];
        unset($data['nicename']);

        $request = $request->withMethod('POST');
        $request->getBody()->write(json_encode($data));

        $response = $this->processRequest($request);
        $this->assertEquals([
            'id'        => '1',
            'name'      => 'test modified',
            'nicename'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
            'date'  => null,
            'datetime'  => null,
            'time'      => null
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

        $response = $this->processRequest($request);
        $this->assertEquals([], $response);
    }

    public function testSerialization()
    {
        $date = $datetime = new \Datetime();
        $date->setTime(6,6,6); // this is for you imanst ;)

        $data = [
            'sys_name'      => 'test_time',
            'name'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
            'date'      => $date->format('Y-m-d'),
            'datetime'  => $date->format('Y-m-d H:i:s'),
            'time'      => $date->format('H:i:s'),
        ];

        // create new record
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

        // check on post
        $this->assertEquals([
            'id'        => '3',
            'name'      => 'test_time',
            'nicename'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
            'date'      => $date->format('Y-m-d'),
            'datetime'  => $date->format('Y-m-d\TH:i:sP'),
            'time'      => $date->format('H:i:s'),
        ], $response);

        // retrive created record
        $request = new Request(
            'http://localhost/client/sys_name:test_time',
            'GET',
            'php://memory',
            [
                'Content-Type'  => 'application/json',
            ]
        );
        $response = $this->processRequest($request);

        // check on get
        $this->assertEquals([
            'id'        => '3',
            'name'      => 'test_time',
            'nicename'  => 'test',
            'iso'       => 'IT',
            'iso3'      => 'ITA',
            'numcode'   => '666',
            'phonecode' => '39',
            'date'      => $date->format('Y-m-d'),
            'datetime'  => $date->format('Y-m-d\TH:i:sP'),
            'time'      => $date->format('H:i:s'),
        ], $response);
    }

    public function testOnlyApiFields()
    {

        $model = new Country($this->db);
        $model->apiFields = [
            'read' => [
                'sys_name',
                'iso',
                'iso3',
                'numcode',
            ]
        ];

        $data = [
            'name'      => 'USA',
            'iso'       => 'US',
            'iso3'      => 'USA',
            'numcode'   => '999'
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

        $response = $this->processRequest($request, $model);
        $this->assertEquals(201, $this->api->response_code);
        $this->assertEquals([
            'name'      => 'USA',
            'iso'       => 'US',
            'iso3'      => 'USA',
            'numcode'   => '999'
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

        $this->addField('date', ['caption'=>'Test Datetime', 'type'=>'date']);
        $this->addField('datetime', ['caption'=>'Test Datetime', 'type'=>'datetime']);
        $this->addField('time', ['caption'=>'Test Datetime', 'type'=>'time']);

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
