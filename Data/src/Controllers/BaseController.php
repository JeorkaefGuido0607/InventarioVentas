<?php
declare(strict_types=1);

namespace App\Controllers;

use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController {
    protected const LIMITE_MAXIMO = 100;

    public function __construct(protected ContainerInterface $container) {}

    protected function eloquent(): Capsule {
        return $this->container->get('eloquent');
    }

    protected function body(Request $request): array {
        $data = $request->getParsedBody();
        return is_array($data) ? $data : [];
    }

    /** Devuelve el id de la ruta si es un entero positivo, o null si no lo es. */
    protected function idValido(array $args, string $clave = 'id'): ?int {
        $id = filter_var($args[$clave] ?? null, FILTER_VALIDATE_INT);
        return ($id === false || $id === null || $id < 1) ? null : $id;
    }

    /** @return array{0:int,1:int}|null [offset, limit] o null si son inválidos */
    protected function paginacion(array $args): ?array {
        $offset = filter_var($args['offset'] ?? 0, FILTER_VALIDATE_INT);
        $limit = filter_var($args['limit'] ?? 20, FILTER_VALIDATE_INT);

        if ($offset === false || $offset < 0 || $limit === false || $limit < 1 || $limit > self::LIMITE_MAXIMO) {
            return null;
        }
        return [$offset, $limit];
    }

    protected function errorPaginacion(Response $response): Response {
        return $this->json($response, ['error' => 'El offset debe ser mayor o igual a 0 y el límite debe estar entre 1 y 100.'], 422);
    }

    /**
     * Ejecuta la consulta paginada y arma la respuesta estándar:
     * { data: [...], pagination: { offset, limit, total } }
     */
    protected function paginado(Response $response, EloquentBuilder|QueryBuilder $query, int $offset, int $limit, string $columnaConteo): Response {
        $total = (clone $query)->count($columnaConteo);

        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return $this->json($response, [
            'data' => $data,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total
            ]
        ], 200);
    }

    /** Error 1062: llave única duplicada */
    protected function esDuplicado(QueryException $exception): bool {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    /** Error 1452: la llave foránea apunta a un registro que no existe */
    protected function esLlaveForaneaInvalida(QueryException $exception): bool {
        return (int) ($exception->errorInfo[1] ?? 0) === 1452;
    }

    protected function mensajeDuplicado(QueryException $exception): string {
        return (string) ($exception->errorInfo[2] ?? '');
    }

    protected function esFechaValida(string $fecha): bool {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        return $date !== false && $date->format('Y-m-d') === $fecha;
    }

    /** Convierte '' en null y recorta espacios (para campos opcionales). */
    protected function opcional(array $data, string $campo): ?string {
        if (!array_key_exists($campo, $data) || $data[$campo] === null) {
            return null;
        }
        $valor = trim((string) $data[$campo]);
        return $valor === '' ? null : $valor;
    }

    protected function json(Response $response, array $data, int $status): Response {
        $response->getBody()->write(json_encode($data,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withStatus($status);
    }
}
