<?php
namespace atk4\api\tests;

class Country extends \atk4\data\Model
{
    public $table = 'country';

    public function init()
    {
        parent::init();
        $this->addField('name', ['actual'=>'nicename', 'required'=>true, 'type'=>'string']);
        $this->addField('sys_name', ['actual'=>'name', 'system'=>true]);

        $this->addField('iso', ['caption'=>'ISO', 'required'=>true, 'type'=>'string']);
        $this->addField('iso3', ['caption'=>'ISO3', 'required'=>true, 'type'=>'string']);
        $this->addField('numcode', ['caption'=>'ISO Numeric Code', 'type'=>'number', 'required'=>true]);
        $this->addField('phonecode', ['caption'=>'Phone Prefix', 'type'=>'number']);

        $this->addHook('beforeSave', function ($m) {
            if (!$m['sys_name']) {
                $m['sys_name'] = strtoupper($m['name']);
            }
        });
    }

    public function validate($intent = null)
    {
        $errors = parent::validate($intent);

        if (strlen($this['iso']) !== 2) {
            $errors['iso'] = 'Must be exactly 2 characters';
        }

        if (strlen($this['iso3']) !== 3) {
            $errors['iso3'] = 'Must be exactly 3 characters';
        }

        // look if name is unique
        $c = clone $this;
        $c->unload();
        $c->tryLoadBy('name', $this['name']);
        if ($c->loaded() && $c->id != $this->id) {
            $errors['name'] = 'Country name must be unique';
        }

        return $errors;
    }
}
