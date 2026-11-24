<?php

use Slim\Factory\AppFactory;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Rules\RequestPathRule;
use JimTools\JwtAuth\Secret;

require __DIR__ . '/../../vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable('/var/www/html');
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Costa_Rica');

$container = new Container();

AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

require_once "config.php";
require_once "routes.php";

// Autenticación JWT: todas las rutas /api requieren token, excepto el login
// y la documentación. Las peticiones OPTIONS (preflight CORS) no se validan.
$app->add(new JwtAuthentication(
	new Options(isSecure: false),
	new FirebaseDecoder(new Secret($_ENV['KEY'], 'HS256')),
	[
		new RequestMethodRule(['OPTIONS']),
		new RequestPathRule(
			['/api'],
			['/api/auth/login', '/api/docs'])
	]
));

// Manejo centralizado de errores (401, 404, 405, 422, 502, 500...) en JSON
require_once "errores.php";

$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($app): ResponseInterface {
	$origin = $request->getHeaderLine('Origin');
	$allowedOrigins = array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGIN'] ?? 'http://localhost:4200'));
	$allowed = in_array($origin, $allowedOrigins, true);

	if ($request->getMethod() === 'OPTIONS' && $allowed) {
		$response = $app->getResponseFactory()->createResponse(204);
	} else {
		$response = $handler->handle($request);
	}

	if (!$allowed) {
		return $response;
	}

	return $response
		->withHeader('Access-Control-Allow-Origin', $origin)
		->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
		->withHeader('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type, Origin, X-Requested-With')
		->withHeader('Access-Control-Expose-Headers', 'Content-Disposition')
		->withHeader('Vary', 'Origin');
});

$app->run();
