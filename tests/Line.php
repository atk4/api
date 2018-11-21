<?php
namespace atk4\api\tests;

class Line extends \atk4\data\Model
{
    public $table = 'line';

    public function init()
    {
        parent::init();
        $this->addField('ref_no');

        $this->hasOne('client_id', new Client());
        $this->hasMany('Lines', new Line())
            ->addField('total', ['aggregate'=>'sum']);
    }
}
