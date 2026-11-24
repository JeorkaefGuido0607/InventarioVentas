<?php
declare(strict_types=1);

/*
 * Manejo centralizado de errores de la capa de negocio.
 * Los controladores y servicios no atrapan excepciones: las lanzan y aquí
 * se convierten en una respuesta JSON con el código HTTP correcto.
 *
 *   AuthorizationException (JWT)     → 401 { error }
 *   HttpNotFoundException            → 404 { error }
 *   HttpMethodNotAllowedException    → 405 { error, metodos_permitidos }
 *   ValidacionException              → 422/400 { errores: { campo: mensaje } }
 *   ServicioNoDisponibleException    → 502 { error }
 *   ApiException (otras de negocio)  → su propio código y cuerpo
 *   Cualquier otra                   → 500 { error } (+ detalle si APP_DEBUG=true)
 */

use App\Exceptions\ApiException;
use JimTools\JwtAuth\Exceptions\AuthorizationException;
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

        if ($exception instanceof ApiException) {
            $status = $exception->getStatus();
            $payload = $exception->getPayload();
        } elseif ($exception instanceof AuthorizationException) {
            $status = 401;
            $payload = ['error' => 'Token inválido o ausente.'];
        } elseif ($exception instanceof HttpNotFoundException) {
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
