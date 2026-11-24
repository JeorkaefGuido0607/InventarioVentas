<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductoController extends BaseController {

    private function consulta(): Builder {
        return Producto::query()
            ->select([
                'productos.id',
                'productos.codigo',
                'productos.nombre',
                'productos.descripcion',
                'productos.categoria_id',
                'categorias.nombre as categoria_nombre',
                'productos.proveedor_id',
                'proveedores.nombre as proveedor_nombre',
                'productos.precio_compra',
                'productos.precio_venta',
                'productos.stock',
                'productos.stock_minimo',
                'productos.imagen_url',
                'productos.activo',
                'productos.creado_en'
            ])
            ->selectRaw('(productos.stock <= productos.stock_minimo) as stock_bajo')
            ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
            ->leftJoin('proveedores', 'productos.proveedor_id', '=', 'proveedores.id');
    }

    private function formatear(array $producto): array {
        $producto['stock_bajo'] = (bool) ($producto['stock_bajo'] ?? false);
        return $producto;
    }

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $producto = $this->consulta()
                ->where('productos.id', $id)
                ->first();

            if ($producto === null) {
                return $this->json($response, ['error' => 'Producto no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $this->formatear($producto->toArray())], 200);
        }

        $productos = $this->consulta()
            ->orderBy('productos.nombre')
            ->get()
            ->map(fn (Producto $p) => $this->formatear($p->toArray()))
            ->all();

        return $this->json($response, ['data' => $productos], 200);
    }

    /** Búsqueda por código de barras (la usa la app móvil al escanear con la cámara) */
    public function readByCodigo(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $code = trim((string) ($args['codigo'] ?? ''));

        if ($code === '') {
            return $this->json($response, ['error' => 'Debe indicar el código del producto.'], 422);
        }

        $producto = $this->consulta()
            ->where('productos.codigo', $code)
            ->first();

        if ($producto === null) {
            return $this->json($response, ['error' => 'No existe un producto con ese código.'], 404);
        }

        return $this->json($response, ['data' => $this->formatear($producto->toArray())], 200);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $paginacion = $this->paginacion($args);
        if ($paginacion === null) {
            return $this->errorPaginacion($response);
        }
        [$offset, $limit] = $paginacion;

        $params = $request->getQueryParams();
        $name = trim((string) ($params['nombre'] ?? ''));
        $code = trim((string) ($params['codigo'] ?? ''));
        $categoryId = filter_var($params['categoria_id'] ?? null, FILTER_VALIDATE_INT);
        $supplierId = filter_var($params['proveedor_id'] ?? null, FILTER_VALIDATE_INT);
        $lowStock = filter_var($params['stock_bajo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $active = $params['activo'] ?? null;

        $query = $this->consulta();

        if ($name !== '') {
            $query->where('productos.nombre', 'like', "%{$name}%");
        }
        if ($code !== '') {
            $query->where('productos.codigo', 'like', "%{$code}%");
        }
        if ($categoryId !== false && $categoryId !== null) {
            $query->where('productos.categoria_id', $categoryId);
        }
        if ($supplierId !== false && $supplierId !== null) {
            $query->where('productos.proveedor_id', $supplierId);
        }
        if ($lowStock) {
            $query->whereColumn('productos.stock', '<=', 'productos.stock_minimo');
        }
        if ($active !== null && $active !== '') {
            $query->where('productos.activo', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $total = (clone $query)->count('productos.id');

        $productos = $query
            ->orderBy('productos.nombre')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Producto $p) => $this->formatear($p->toArray()))
            ->all();

        return $this->json($response, [
            'data' => $productos,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total
            ]
        ], 200);
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $this->body($request);

        $errors = [];
        $code = trim((string) ($data['codigo'] ?? ''));
        $name = trim((string) ($data['nombre'] ?? ''));
        $categoryId = filter_var($data['categoria_id'] ?? null, FILTER_VALIDATE_INT);
        $supplierId = isset($data['proveedor_id']) ? filter_var($data['proveedor_id'], FILTER_VALIDATE_INT) : null;
        $purchasePrice = filter_var($data['precio_compra'] ?? 0, FILTER_VALIDATE_FLOAT);
        $salePrice = filter_var($data['precio_venta'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($data['stock'] ?? 0, FILTER_VALIDATE_INT);
        $minimumStock = filter_var($data['stock_minimo'] ?? 5, FILTER_VALIDATE_INT);
        $userId = filter_var($data['usuario_id'] ?? null, FILTER_VALIDATE_INT);

        if ($code === '' || mb_strlen($code) > 50) {
            $errors['codigo'] = 'Es obligatorio y debe tener máximo 50 caracteres.';
        }
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['nombre'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
        }
        if ($categoryId === false || $categoryId === null || $categoryId < 1) {
            $errors['categoria_id'] = 'Debe ser un entero positivo.';
        }
        if ($supplierId === false || ($supplierId !== null && $supplierId < 1)) {
            $errors['proveedor_id'] = 'Debe ser un entero positivo.';
        }
        if ($purchasePrice === false || $purchasePrice < 0) {
            $errors['precio_compra'] = 'Debe ser un número mayor o igual a 0.';
        }
        if ($salePrice === false || $salePrice === null || $salePrice < 0) {
            $errors['precio_venta'] = 'Es obligatorio y debe ser un número mayor o igual a 0.';
        }
        if ($stock === false || $stock < 0) {
            $errors['stock'] = 'Debe ser un entero mayor o igual a 0.';
        }
        if ($minimumStock === false || $minimumStock < 0) {
            $errors['stock_minimo'] = 'Debe ser un entero mayor o igual a 0.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $eloquent = $this->eloquent();
            $producto = $eloquent->connection()->transaction(
                function () use ($data, $code, $name, $categoryId, $supplierId, $purchasePrice, $salePrice, $stock, $minimumStock, $userId): Producto {
                    $producto = Producto::create([
                        'codigo' => $code,
                        'nombre' => $name,
                        'descripcion' => $this->opcional($data, 'descripcion'),
                        'categoria_id' => $categoryId,
                        'proveedor_id' => $supplierId,
                        'precio_compra' => $purchasePrice,
                        'precio_venta' => $salePrice,
                        'stock' => $stock,
                        'stock_minimo' => $minimumStock,
                        'imagen_url' => $this->opcional($data, 'imagen_url'),
                        'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
                    ]);

                    // El stock inicial queda registrado en la bitácora de inventario
                    if ($stock > 0) {
                        MovimientoInventario::create([
                            'producto_id' => $producto->id,
                            'usuario_id' => ($userId !== false && $userId !== null) ? $userId : null,
                            'tipo' => 'Entrada',
                            'cantidad' => $stock,
                            'stock_anterior' => 0,
                            'stock_nuevo' => $stock,
                            'motivo' => 'Inventario inicial',
                        ]);
                    }

                    return $producto;
                }
            );
        } catch (QueryException $exception) {
            return $this->errorEscritura($response, $exception);
        }

        return $this->json($response, ['data' => $producto->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de producto válido.'], 400);
        }

        $producto = Producto::find($id);

        if ($producto === null) {
            return $this->json($response, ['error' => 'Producto no encontrado.'], 404);
        }

        $data = $this->body($request);

        // El stock solo cambia por ventas o movimientos de inventario (queda en la bitácora)
        if (array_key_exists('stock', $data)) {
            return $this->json($response, ['error' => 'El stock no se modifica directamente; registre un movimiento de inventario.'], 422);
        }

        if (isset($data['codigo'])) {
            $code = trim((string) $data['codigo']);

            if ($code === '' || mb_strlen($code) > 50) {
                return $this->json($response, ['error' => 'Código inválido.'], 422);
            }

            $producto->codigo = $code;
        }

        if (isset($data['nombre'])) {
            $name = trim((string) $data['nombre']);

            if ($name === '' || mb_strlen($name) > 150) {
                return $this->json($response, ['error' => 'Nombre inválido.'], 422);
            }

            $producto->nombre = $name;
        }

        if (array_key_exists('descripcion', $data)) {
            $producto->descripcion = $this->opcional($data, 'descripcion');
        }

        if (isset($data['categoria_id'])) {
            $categoryId = filter_var($data['categoria_id'], FILTER_VALIDATE_INT);

            if ($categoryId === false || $categoryId < 1) {
                return $this->json($response, ['error' => 'Categoría inválida.'], 422);
            }

            $producto->categoria_id = $categoryId;
        }

        if (array_key_exists('proveedor_id', $data)) {
            $supplierId = $data['proveedor_id'] === null ? null : filter_var($data['proveedor_id'], FILTER_VALIDATE_INT);

            if ($supplierId === false || ($supplierId !== null && $supplierId < 1)) {
                return $this->json($response, ['error' => 'Proveedor inválido.'], 422);
            }

            $producto->proveedor_id = $supplierId;
        }

        foreach (['precio_compra', 'precio_venta'] as $campo) {
            if (!isset($data[$campo])) {
                continue;
            }

            $price = filter_var($data[$campo], FILTER_VALIDATE_FLOAT);

            if ($price === false || $price < 0) {
                return $this->json($response, ['error' => "El campo {$campo} debe ser un número mayor o igual a 0."], 422);
            }

            $producto->{$campo} = $price;
        }

        if (isset($data['stock_minimo'])) {
            $minimumStock = filter_var($data['stock_minimo'], FILTER_VALIDATE_INT);

            if ($minimumStock === false || $minimumStock < 0) {
                return $this->json($response, ['error' => 'Stock mínimo inválido.'], 422);
            }

            $producto->stock_minimo = $minimumStock;
        }

        if (array_key_exists('imagen_url', $data)) {
            $producto->imagen_url = $this->opcional($data, 'imagen_url');
        }

        if (array_key_exists('activo', $data)) {
            $producto->activo = (bool) $data['activo'];
        }

        try {
            $producto->save();
        } catch (QueryException $exception) {
            return $this->errorEscritura($response, $exception);
        }

        return $this->json($response, ['data' => $producto->toArray()], 200);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de producto válido.'], 400);
        }

        $producto = Producto::find($id);

        if ($producto === null) {
            return $this->json($response, ['error' => 'Producto no encontrado.'], 404);
        }

        // Un producto que ya se vendió forma parte del historial: se desactiva, no se borra
        $ventasCount = $eloquent->table('detalle_ventas')
            ->where('producto_id', $producto->id)
            ->count();

        if ($ventasCount > 0) {
            return $this->json($response, ['error' => 'No se puede eliminar el producto porque tiene ventas registradas. Desactívelo en su lugar.'], 409);
        }

        // Sus movimientos de inventario se eliminan en cascada (FK ON DELETE CASCADE)
        $producto->delete();

        return $this->json($response, ['message' => 'Producto eliminado exitosamente.'], 200);
    }

    private function errorEscritura(Response $response, QueryException $exception): Response {
        if ($this->esDuplicado($exception)) {
            return $this->json($response, ['error' => 'El código del producto ya está registrado.'], 409);
        }

        if ($this->esLlaveForaneaInvalida($exception)) {
            $message = $this->mensajeDuplicado($exception);

            if (str_contains($message, 'fk_productos_proveedores')) {
                return $this->json($response, ['error' => 'El proveedor indicado no existe.'], 422);
            }
            if (str_contains($message, 'fk_movimientos_usuarios')) {
                return $this->json($response, ['error' => 'El usuario indicado no existe.'], 422);
            }

            return $this->json($response, ['error' => 'La categoría indicada no existe.'], 422);
        }

        throw $exception;
    }
}
