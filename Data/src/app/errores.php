<?php
declare(strict_types=1);

use Illuminate\Database\QueryException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;

$displayErrorDetails = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

$errorMiddleware->setDefaultErrorHandler(
    function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) use ($app) {
        $status = 500;
        $payload = ['error' => 'Error interno del servidor.'];

        if ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $payload = ['error' => 'Ruta no encontrada.'];
        } elseif ($exception instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $payload = [
                'error' => 'Método no permitido.',
                'metodos_permitidos' => $exception->getAllowedMethods(),
            ];
        } elseif ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $payload = ['error' => $exception->getMessage()];
        } elseif ($exception instanceof QueryException) {
            $payload = ['error' => 'Error al acceder a la base de datos.'];
        }

        if ($status === 500) {
            error_log((string) $exception);   // queda en `docker logs <contenedor>`

            if ($displayErrorDetails) {
                $payload['detalle'] = $exception->getMessage();
            }
        }

        $response = $app->getResponseFactory()->createResponse($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
);
