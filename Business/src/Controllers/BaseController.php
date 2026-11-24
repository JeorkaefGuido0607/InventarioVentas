<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidacionException;
use App\Services\ApiResult;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController {

    public function __construct(protected ContainerInterface $container) {}

    /** Lee el cuerpo JSON de la petición (igual que en CitasMedicas) */
    protected function body(Request $request): array {
        $request->getBody()->rewind();
        $body = (string) $request->getBody();

        $data = json_decode($body, true);

        if(!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidacionException(['body' => 'Debe enviar un objeto JSON válido'], 400);
        }

        return $data;
    }

    /** Payload del JWT del usuario autenticado (lo deja JwtAuthentication) */
    protected function usuario(Request $request): array {
        $token = $request->getAttribute('token');
        return is_array($token) ? $token : [];
    }

    protected function responder(Response $response, ApiResult $resultado): Response {
        return $this->json($response, $resultado->body, $resultado->status);
    }

    protected function json(Response $response, array $data, int $status): Response {
        $response->getBody()->write(json_encode($data,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withStatus($status);
    }
}
