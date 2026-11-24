<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controlador REST genérico: read, filter, create, update y delete.
 * Mismos métodos que los controladores de CitasMedicas, pero la lógica
 * (validación, reglas, llamada a la capa de datos) vive en el servicio.
 * Las excepciones las convierte en JSON el manejador central de errores.
 */
abstract class CrudController extends BaseController {

    /** Clase del servicio de negocio del módulo */
    abstract protected function servicio(): CrudService;

    public function read(Request $request, Response $response, array $args): Response {
        $resultado = isset($args['id'])
            ? $this->servicio()->obtener((string) $args['id'])
            : $this->servicio()->listar();

        return $this->responder($response, $resultado);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $resultado = $this->servicio()->filtrar(
            (string) $args['offset'],
            (string) $args['limit'],
            $request->getQueryParams()
        );

        return $this->responder($response, $resultado);
    }

    public function create(Request $request, Response $response, array $args): Response {
        $resultado = $this->servicio()->crear($this->body($request), $this->usuario($request));

        return $this->responder($response, $resultado);
    }

    public function update(Request $request, Response $response, array $args): Response {
        $resultado = $this->servicio()->actualizar((string) $args['id'], $this->body($request), $this->usuario($request));

        return $this->responder($response, $resultado);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $resultado = $this->servicio()->eliminar((string) $args['id'], $this->usuario($request));

        return $this->responder($response, $resultado);
    }
}
