<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DetalleVenta;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VentaController extends BaseController {
    private const METODOS_PAGO = ['Efectivo', 'Tarjeta', 'SINPE Móvil', 'Transferencia'];
    private const ESTADOS = ['Completada', 'Anulada'];

    /** Consulta base: venta + nombre del cliente y del vendedor */
    private function consulta(): Builder {
        return Venta::query()
            ->select([
                'ventas.id',
                'ventas.fecha',
                'ventas.cliente_id',
                'clientes.nombre_completo as cliente_nombre',
                'ventas.usuario_id',
                'usuarios.nombre_completo as vendedor_nombre',
                'ventas.metodo_pago',
                'ventas.subtotal',
                'ventas.descuento',
                'ventas.impuesto',
                'ventas.total',
                'ventas.estado',
                'ventas.observaciones'
            ])
            ->leftJoin('clientes', 'ventas.cliente_id', '=', 'clientes.id')
            ->join('usuarios', 'ventas.usuario_id', '=', 'usuarios.id');
    }

    /** Venta completa con sus líneas de detalle */
    private function ventaConDetalle(int $id): ?array {
        $venta = $this->consulta()->where('ventas.id', $id)->first();

        if ($venta === null) {
            return null;
        }

        $detalle = $this->eloquent()->connection()->select('CALL sp_obtener_detalle_venta(?)', [$id]);

        $venta = $venta->toArray();
        $venta['detalle'] = array_map(static fn (object $linea): array => [
            'id' => (int) $linea->id,
            'producto_id' => (int) $linea->producto_id,
            'codigo' => $linea->codigo,
            'producto' => $linea->producto,
            'cantidad' => (int) $linea->cantidad,
            'precio_unitario' => (float) $linea->precio_unitario,
            'subtotal' => (float) $linea->subtotal,
        ], $detalle);

        return $venta;
    }

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $venta = $this->ventaConDetalle($id);

            if ($venta === null) {
                return $this->json($response, ['error' => 'Venta no encontrada.'], 404);
            }

            return $this->json($response, ['data' => $venta], 200);
        }

        $ventas = $this->consulta()
            ->orderByDesc('ventas.fecha')
            ->orderByDesc('ventas.id')
            ->get()
            ->toArray();

        return $this->json($response, ['data' => $ventas], 200);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $paginacion = $this->paginacion($args);
        if ($paginacion === null) {
            return $this->errorPaginacion($response);
        }
        [$offset, $limit] = $paginacion;

        $params = $request->getQueryParams();
        $from = trim((string) ($params['desde'] ?? ''));
        $to = trim((string) ($params['hasta'] ?? ''));
        $status = trim((string) ($params['estado'] ?? ''));
        $paymentMethod = trim((string) ($params['metodo_pago'] ?? ''));
        $customerId = filter_var($params['cliente_id'] ?? null, FILTER_VALIDATE_INT);
        $userId = filter_var($params['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $customer = trim((string) ($params['cliente'] ?? ''));

        if (($from !== '' && !$this->esFechaValida($from)) || ($to !== '' && !$this->esFechaValida($to))) {
            return $this->json($response, ['error' => 'Las fechas deben tener el formato AAAA-MM-DD.'], 422);
        }

        $query = $this->consulta();

        if ($from !== '') {
            $query->where('ventas.fecha', '>=', "{$from} 00:00:00");
        }
        if ($to !== '') {
            $query->where('ventas.fecha', '<=', "{$to} 23:59:59");
        }
        if ($status !== '') {
            $query->where('ventas.estado', $status);
        }
        if ($paymentMethod !== '') {
            $query->where('ventas.metodo_pago', $paymentMethod);
        }
        if ($customerId !== false && $customerId !== null) {
            $query->where('ventas.cliente_id', $customerId);
        }
        if ($userId !== false && $userId !== null) {
            $query->where('ventas.usuario_id', $userId);
        }
        if ($customer !== '') {
            $query->where('clientes.nombre_completo', 'like', "%{$customer}%");
        }

        $query->orderByDesc('ventas.fecha')->orderByDesc('ventas.id');

        return $this->paginado($response, $query, $offset, $limit, 'ventas.id');
    }

    /**
     * Registra la venta en una transacción:
     *  1. bloquea las filas de los productos (SELECT ... FOR UPDATE),
     *  2. verifica de nuevo el stock (otra venta pudo entrar en paralelo),
     *  3. inserta encabezado, detalle y movimientos, y descuenta el stock.
     * Los montos ya vienen calculados por la capa de negocio.
     */
    public function create(Request $request, Response $response, array $args): Response {
        $data = $this->body($request);

        try {
            $venta = $this->datosVenta($data);
            $lineas = $this->datosDetalle($data['detalle'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['errors' => json_decode($exception->getMessage(), true)], 422);
        }

        try {
            $eloquent = $this->eloquent();
            $ventaId = $eloquent->connection()->transaction(function () use ($venta, $lineas): int {
                $productos = Producto::query()
                    ->whereIn('id', array_column($lineas, 'producto_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($lineas as $linea) {
                    $producto = $productos->get($linea['producto_id']);

                    if ($producto === null) {
                        throw new DomainException("El producto #{$linea['producto_id']} no existe.");
                    }
                    if ($producto->stock < $linea['cantidad']) {
                        throw new DomainException("Stock insuficiente para '{$producto->nombre}'. Disponible: {$producto->stock}.");
                    }
                }

                $nueva = Venta::create($venta);

                foreach ($lineas as $linea) {
                    $producto = $productos->get($linea['producto_id']);
                    $stockAnterior = $producto->stock;

                    DetalleVenta::create([
                        'venta_id' => $nueva->id,
                        'producto_id' => $linea['producto_id'],
                        'cantidad' => $linea['cantidad'],
                        'precio_unitario' => $linea['precio_unitario'],
                        'subtotal' => round($linea['cantidad'] * $linea['precio_unitario'], 2),
                    ]);

                    $producto->stock = $stockAnterior - $linea['cantidad'];
                    $producto->save();

                    MovimientoInventario::create([
                        'producto_id' => $producto->id,
                        'usuario_id' => $venta['usuario_id'],
                        'tipo' => 'Venta',
                        'cantidad' => $linea['cantidad'],
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $producto->stock,
                        'motivo' => "Venta #{$nueva->id}",
                        'venta_id' => $nueva->id,
                    ]);
                }

                return (int) $nueva->id;
            });
        } catch (DomainException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 409);
        } catch (QueryException $exception) {
            if ($this->esLlaveForaneaInvalida($exception)) {
                $message = $this->mensajeDuplicado($exception);
                $error = str_contains($message, 'fk_ventas_clientes') ? 'El cliente indicado no existe.' : 'El usuario indicado no existe.';

                return $this->json($response, ['error' => $error], 422);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $this->ventaConDetalle($ventaId)], 201);
    }

    /** Solo se pueden corregir datos que no afectan montos ni inventario */
    public function update(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de venta válido.'], 400);
        }

        $venta = Venta::find($id);

        if ($venta === null) {
            return $this->json($response, ['error' => 'Venta no encontrada.'], 404);
        }

        if ($venta->estado === 'Anulada') {
            return $this->json($response, ['error' => 'No se puede modificar una venta anulada.'], 409);
        }

        $data = $this->body($request);

        if (isset($data['metodo_pago'])) {
            if (!in_array($data['metodo_pago'], self::METODOS_PAGO, true)) {
                return $this->json($response, ['error' => 'Método de pago inválido.'], 422);
            }

            $venta->metodo_pago = $data['metodo_pago'];
        }

        if (array_key_exists('cliente_id', $data)) {
            $customerId = $data['cliente_id'] === null ? null : filter_var($data['cliente_id'], FILTER_VALIDATE_INT);

            if ($customerId === false || ($customerId !== null && $customerId < 1)) {
                return $this->json($response, ['error' => 'Cliente inválido.'], 422);
            }

            $venta->cliente_id = $customerId;
        }

        if (array_key_exists('observaciones', $data)) {
            $notes = $this->opcional($data, 'observaciones');

            if ($notes !== null && mb_strlen($notes) > 255) {
                return $this->json($response, ['error' => 'Las observaciones deben tener máximo 255 caracteres.'], 422);
            }

            $venta->observaciones = $notes;
        }

        try {
            $venta->save();
        } catch (QueryException $exception) {
            if ($this->esLlaveForaneaInvalida($exception)) {
                return $this->json($response, ['error' => 'El cliente indicado no existe.'], 422);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $this->ventaConDetalle($id)], 200);
    }

    /** DELETE no borra la venta: la anula y devuelve el stock (sp_anular_venta) */
    public function delete(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de venta válido.'], 400);
        }

        $userId = filter_var($request->getQueryParams()['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $userId = ($userId === false || $userId === null) ? null : $userId;

        $eloquent->connection()->statement('CALL sp_anular_venta(?, ?, @resultado)', [$id, $userId]);
        $result = $eloquent->connection()->selectOne('SELECT @resultado AS resultado');
        $message = (string) ($result->resultado ?? '');

        if ($message === 'Error: La venta no existe.') {
            return $this->json($response, ['error' => 'Venta no encontrada.'], 404);
        }

        if (str_starts_with($message, 'Error:')) {
            return $this->json($response, ['error' => $message], 409);
        }

        return $this->json($response, ['message' => $message, 'data' => $this->ventaConDetalle($id)], 200);
    }

    private function datosVenta(array $data): array {
        $errors = [];

        $userId = filter_var($data['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $customerId = isset($data['cliente_id']) ? filter_var($data['cliente_id'], FILTER_VALIDATE_INT) : null;
        $paymentMethod = (string) ($data['metodo_pago'] ?? 'Efectivo');

        if ($userId === false || $userId === null || $userId < 1) {
            $errors['usuario_id'] = 'Debe ser un entero positivo.';
        }
        if ($customerId === false || ($customerId !== null && $customerId < 1)) {
            $errors['cliente_id'] = 'Debe ser un entero positivo.';
        }
        if (!in_array($paymentMethod, self::METODOS_PAGO, true)) {
            $errors['metodo_pago'] = 'Debe ser uno de: ' . implode(', ', self::METODOS_PAGO) . '.';
        }

        $montos = [];
        foreach (['subtotal', 'descuento', 'impuesto', 'total'] as $campo) {
            $valor = filter_var($data[$campo] ?? ($campo === 'descuento' ? 0 : null), FILTER_VALIDATE_FLOAT);

            if ($valor === false || $valor === null || $valor < 0) {
                $errors[$campo] = 'Debe ser un número mayor o igual a 0.';
                continue;
            }
            $montos[$campo] = round($valor, 2);
        }

        $notes = $this->opcional($data, 'observaciones');
        if ($notes !== null && mb_strlen($notes) > 255) {
            $errors['observaciones'] = 'Debe tener máximo 255 caracteres.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return [
            'cliente_id' => $customerId,
            'usuario_id' => $userId,
            'metodo_pago' => $paymentMethod,
            'subtotal' => $montos['subtotal'],
            'descuento' => $montos['descuento'],
            'impuesto' => $montos['impuesto'],
            'total' => $montos['total'],
            'estado' => 'Completada',
            'observaciones' => $notes,
        ];
    }

    private function datosDetalle(mixed $detalle): array {
        if (!is_array($detalle) || $detalle === [] || !array_is_list($detalle)) {
            throw new InvalidArgumentException(json_encode(['detalle' => 'Debe incluir al menos un producto.'], JSON_UNESCAPED_UNICODE));
        }

        $lineas = [];
        $errors = [];

        foreach ($detalle as $i => $linea) {
            if (!is_array($linea)) {
                $errors["detalle.{$i}"] = 'Cada línea debe ser un objeto con producto_id, cantidad y precio_unitario.';
                continue;
            }

            $productId = filter_var($linea['producto_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($linea['cantidad'] ?? null, FILTER_VALIDATE_INT);
            $price = filter_var($linea['precio_unitario'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($productId === false || $productId === null || $productId < 1
                || $quantity === false || $quantity === null || $quantity < 1
                || $price === false || $price === null || $price < 0) {
                $errors["detalle.{$i}"] = 'Cada línea requiere producto_id, cantidad (> 0) y precio_unitario (>= 0).';
                continue;
            }

            if (isset($lineas[$productId])) {
                $errors["detalle.{$i}"] = 'El producto está repetido en el detalle.';
                continue;
            }

            $lineas[$productId] = [
                'producto_id' => $productId,
                'cantidad' => $quantity,
                'precio_unitario' => round($price, 2),
            ];
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return array_values($lineas);
    }
}
