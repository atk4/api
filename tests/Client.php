<?php

namespace atk4\api\tests;

class Client extends \atk4\data\Model
{
    public $table = 'country';

    public function init()
    {
        parent::init();
        $this->addField('name');

        $this->hasMany('Invoices', new Invoice());

        $this->hasMany('InvoicesDue', (new Invoice($this->persistence))->addCondition('is_paid', false))
            ->addField('total_due', ['aggregate'=>'sum', 'field'=>'total']);
    }
}
