<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends BaseController
{
    /** POST /api/auth/login (ruta pública) */
    public function login(Request $request, Response $response): Response
    {
        $sesion = $this->container->get(AuthService::class)->login($this->body($request));

        return $this->json($response, $sesion, 200);
    }

    /** GET /api/auth/perfil: datos del usuario dueño del token */
    public function perfil(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => AuthService::perfil($this->usuario($request))], 200);
    }
}
