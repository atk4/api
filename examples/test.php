<?php

include '../vendor/autoload.php';

$api = new \atk4\api\Api();
session_start();
$db = new \atk4\data\Persistence_SQL('mysql:dbname=atk4;host=localhost', 'root', '');

$api->get('/ping/', function () {
    return 'Hello, World';
});
$api->get('/ping/:hello', function ($hello) {
    return "Hello, $hello";
});

// Basic usage of REST client
$api->rest('/clients', new \atk4\api\tests\Country($db));

// Tweak our model accordingly
$api->rest('/clients2', function () use ($db) {
    $c = new \atk4\api\tests\Country($db);
    $c->setLimit(10);
    $c->setOrder('name');
    $c->addCondition('id', '<', 100);

    return $c;
});
