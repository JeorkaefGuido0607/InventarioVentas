<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\MovimientoInventario;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MovimientoController extends BaseController {
    private const TIPOS_MANUALES = ['Entrada', 'Salida', 'Ajuste'];

    private function consulta(): Builder {
        return MovimientoInventario::query()
            ->select([
                'movimientos_inventario.id',
                'movimientos_inventario.fecha',
                'movimientos_inventario.producto_id',
                'productos.codigo as producto_codigo',
                'productos.nombre as producto_nombre',
                'movimientos_inventario.tipo',
                'movimientos_inventario.cantidad',
                'movimientos_inventario.stock_anterior',
                'movimientos_inventario.stock_nuevo',
                'movimientos_inventario.motivo',
                'movimientos_inventario.venta_id',
                'movimientos_inventario.usuario_id',
                'usuarios.nombre_completo as usuario_nombre'
            ])
            ->join('productos', 'movimientos_inventario.producto_id', '=', 'productos.id')
            ->leftJoin('usuarios', 'movimientos_inventario.usuario_id', '=', 'usuarios.id');
    }

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $movimiento = $this->consulta()
                ->where('movimientos_inventario.id', $id)
                ->first();

            if ($movimiento === null) {
                return $this->json($response, ['error' => 'Movimiento no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $movimiento->toArray()], 200);
        }

        $movimientos = $this->consulta()
            ->orderByDesc('movimientos_inventario.fecha')
            ->orderByDesc('movimientos_inventario.id')
            ->get()
            ->toArray();

        return $this->json($response, ['data' => $movimientos], 200);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $paginacion = $this->paginacion($args);
        if ($paginacion === null) {
            return $this->errorPaginacion($response);
        }
        [$offset, $limit] = $paginacion;

        $params = $request->getQueryParams();
        $productId = filter_var($params['producto_id'] ?? null, FILTER_VALIDATE_INT);
        $type = trim((string) ($params['tipo'] ?? ''));
        $from = trim((string) ($params['desde'] ?? ''));
        $to = trim((string) ($params['hasta'] ?? ''));

        if (($from !== '' && !$this->esFechaValida($from)) || ($to !== '' && !$this->esFechaValida($to))) {
            return $this->json($response, ['error' => 'Las fechas deben tener el formato AAAA-MM-DD.'], 422);
        }

        $query = $this->consulta();

        if ($productId !== false && $productId !== null) {
            $query->where('movimientos_inventario.producto_id', $productId);
        }
        if ($type !== '') {
            $query->where('movimientos_inventario.tipo', $type);
        }
        if ($from !== '') {
            $query->where('movimientos_inventario.fecha', '>=', "{$from} 00:00:00");
        }
        if ($to !== '') {
            $query->where('movimientos_inventario.fecha', '<=', "{$to} 23:59:59");
        }

        $query->orderByDesc('movimientos_inventario.fecha')->orderByDesc('movimientos_inventario.id');

        return $this->paginado($response, $query, $offset, $limit, 'movimientos_inventario.id');
    }

    public function create(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();
        $data = $this->body($request);

        $errors = [];
        $productId = filter_var($data['producto_id'] ?? null, FILTER_VALIDATE_INT);
        $userId = filter_var($data['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $type = (string) ($data['tipo'] ?? '');
        $quantity = filter_var($data['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $reason = $this->opcional($data, 'motivo');

        if ($productId === false || $productId === null || $productId < 1) {
            $errors['producto_id'] = 'Debe ser un entero positivo.';
        }
        if (!in_array($type, self::TIPOS_MANUALES, true)) {
            $errors['tipo'] = 'Debe ser Entrada, Salida o Ajuste.';
        }
        if ($quantity === false || $quantity === null || $quantity < 0) {
            $errors['cantidad'] = 'Debe ser un entero mayor o igual a 0.';
        }
        if ($reason !== null && mb_strlen($reason) > 255) {
            $errors['motivo'] = 'Debe tener máximo 255 caracteres.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        $userId = ($userId === false || $userId === null) ? null : $userId;

        $eloquent->connection()->statement(
            'CALL sp_registrar_movimiento(?, ?, ?, ?, ?, @movimiento_id, @resultado)',
            [$productId, $userId, $type, $quantity, $reason]
        );
        $result = $eloquent->connection()->selectOne('SELECT @movimiento_id AS movimiento_id, @resultado AS resultado');
        $message = (string) ($result->resultado ?? '');

        if ($message === 'Error: El producto no existe.') {
            return $this->json($response, ['error' => 'Producto no encontrado.'], 404);
        }

        if (str_starts_with($message, 'Error:')) {
            return $this->json($response, ['error' => $message], 409);
        }

        $movimiento = $this->consulta()
            ->where('movimientos_inventario.id', (int) $result->movimiento_id)
            ->first();

        return $this->json($response, [
            'message' => $message,
            'data' => $movimiento?->toArray()
        ], 201);
    }
}
