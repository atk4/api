<?php

namespace atk4\api\tests;

class Invoice extends \atk4\data\Model
{
    public $table = 'invoice';
    public $title_field = 'ref_no';

    public function init()
    {
        parent::init();
        $this->addField('ref_no');

        $this->hasOne('client_id', new Client());
        $this->addField('is_paid', ['type'=>'boolean', 'default'=>false]);
        $this->addField('is_shipped', ['type'=>'boolean', 'default'=>false]);

        $this->hasMany('Lines', new Line())
            ->addField('total', ['aggregate'=>'sum']);
    }
}
