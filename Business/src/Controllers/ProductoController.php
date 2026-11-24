<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\ProductoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductoController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(ProductoService::class);
    }

    /** GET /api/productos/codigo/{codigo}: lo usa la app móvil al escanear el código de barras */
    public function readByCodigo(Request $request, Response $response, array $args): Response {
        $resultado = $this->container->get(ProductoService::class)->buscarPorCodigo((string) $args['codigo']);

        return $this->responder($response, $resultado);
    }
}
