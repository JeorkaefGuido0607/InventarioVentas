<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\UsuarioService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UsuarioController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(UsuarioService::class);
    }

    /** Catálogo de roles para los formularios de usuarios */
    public function roles(Request $request, Response $response, array $args): Response {
        return $this->responder($response, $this->container->get(UsuarioService::class)->roles());
    }
}
