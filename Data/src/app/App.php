<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../../vendor/autoload.php';

$container = new Container();
$dotenv = Dotenv\Dotenv::createImmutable('/var/www/html');
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Costa_Rica');

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

require_once "routes.php";
require_once "config.php";
require_once "conexion.php";
require_once "eloquent.php";
require_once "errores.php";

$app->run();
