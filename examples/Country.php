<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';

use Atk4\Data\Model;

class Country extends Model
{
    public $table = 'country';

    protected function init(): void
    {
        parent::init();

        $this->addField('name', ['actual' => 'nicename', 'required' => true, 'type' => 'string']);
        $this->addField('sys_name', ['actual' => 'name', 'system' => true]);

        $this->addField('iso', ['caption' => 'ISO', 'required' => true, 'type' => 'string']);
        $this->addField('iso3', ['caption' => 'ISO3', 'required' => true, 'type' => 'string']);
        $this->addField('numcode', ['caption' => 'ISO Numeric Code', 'type' => 'integer', 'required' => true]);
        $this->addField('phonecode', ['caption' => 'Phone Prefix', 'type' => 'integer']);

        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function ($m) {
                if (!$m['sys_name']) {
                    $m['sys_name'] = strtoupper($m['name']);
                }
            }
        );

        $this->onHook(
            Model::HOOK_VALIDATE,
            function (Model $m) {
                $errors = [];

                if (strlen($m['iso']) !== 2) {
                    $errors['iso'] = 'Must be exactly 2 characters';
                }

                if (strlen($m['iso3']) !== 3) {
                    $errors['iso3'] = 'Must be exactly 3 characters';
                }

                // look if name is unique
                $c = (clone $m)->unload()->tryLoadBy('name', $m['name']);
                if ($c->loaded() && $c->id !== $m->id) {
                    $errors['name'] = 'Country name must be unique';
                }

                return $errors;
            }
        );
    }
}
