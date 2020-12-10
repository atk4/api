<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';
require_once 'Country.php';

use Atk4\Api\Api;
use Atk4\Data\Persistence;

$api = new Api();

session_start();
$db = new Persistence\Sql('mysql:dbname=atk4;host=localhost', 'root', '');

$api->get('/ping/', function () {
    return 'Hello, World';
});
$api->get('/ping/:hello', function ($hello) {
    return "Hello, {$hello}";
});

$api->rest('/client', new Country($db));
