<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Autorización por roles. Se ejecuta después de JwtAuthentication, que
 * deja el token decodificado en el atributo 'token' de la petición.
 *   401 si no hay token, 403 si el rol no está entre los permitidos.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $roles */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private array $roles
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getAttribute('token');

        if (!is_array($token)) {
            return $this->error(401, 'Token inválido o ausente.');
        }

        if (!in_array($token['rol'] ?? null, $this->roles, true)) {
            return $this->error(403, 'No tiene permisos para realizar esta acción.');
        }

        return $handler->handle($request);
    }

    private function error(int $status, string $mensaje): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
