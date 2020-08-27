<?php

use atk4\data\Model;

include '../vendor/autoload.php';

$api = new \atk4\api\Api();

class Country extends \atk4\data\Model
{
    public $table = 'country';

    /**
     * @throws \atk4\core\Exception
     */
    protected function init()
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

        $this->onHook('validate', function (Model $m) {
            $errors = [];

            if (strlen($m['iso']) !== 2) {
                $errors['iso'] = 'Must be exactly 2 characters';
            }

            if (strlen($m['iso3']) !== 3) {
                $errors['iso3'] = 'Must be exactly 3 characters';
            }

            // look if name is unique
            $c = (clone $m)->unload()->tryLoadBy('name', $m['name']);
            if ($c->loaded() && $c->id != $m->id) {
                $errors['name'] = 'Country name must be unique';
            }

            return $errors;
        });
    }
}
session_start();
$db = new \atk4\data\Persistence\SQL('mysql:dbname=atk4;host=localhost', 'root', '');

$api->get('/ping/', function () {
    return 'Hello, World';
});
$api->get('/ping/:hello', function ($hello) {
    return "Hello, $hello";
});

$api->rest('/client', new Country($db));
