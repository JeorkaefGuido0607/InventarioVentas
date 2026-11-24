<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\VentaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VentaController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(VentaService::class);
    }

    /** POST /api/ventas/cotizar: calcula la venta (precios, IVA, total) sin guardarla */
    public function cotizar(Request $request, Response $response, array $args): Response {
        $resultado = $this->container->get(VentaService::class)->cotizar($this->body($request));

        return $this->responder($response, $resultado);
    }
}
