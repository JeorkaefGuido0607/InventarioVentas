<?php
declare(strict_types=1);

namespace App\Controllers;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReporteController extends BaseController {

    /** @return array{0:string,1:string}|null */
    private function rango(Request $request): ?array {
        $params = $request->getQueryParams();
        $to = trim((string) ($params['hasta'] ?? ''));
        $from = trim((string) ($params['desde'] ?? ''));

        $to = $to === '' ? date('Y-m-d') : $to;
        $from = $from === '' ? date('Y-m-d', strtotime($to . ' -29 days')) : $from;

        if (!$this->esFechaValida($from) || !$this->esFechaValida($to) || $from > $to) {
            return null;
        }
        return [$from, $to];
    }

    private function errorRango(Response $response): Response {
        return $this->json($response, ['error' => 'Rango de fechas inválido. Use AAAA-MM-DD y desde <= hasta.'], 422);
    }

    /** Ventas completadas dentro del rango (encabezado) */
    private function ventas(string $from, string $to): Builder {
        return $this->eloquent()->table('ventas')
            ->where('ventas.estado', 'Completada')
            ->whereBetween('ventas.fecha', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    }

    /** Líneas de detalle de las ventas completadas dentro del rango */
    private function lineas(string $from, string $to): Builder {
        return $this->ventas($from, $to)
            ->join('detalle_ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id');
    }

    /** 1. Ventas por período (día o mes), con los días/meses sin ventas en 0 */
    public function ventasPeriodo(Request $request, Response $response, array $args): Response {
        $rango = $this->rango($request);
        if ($rango === null) {
            return $this->errorRango($response);
        }
        [$from, $to] = $rango;

        $groupBy = ($request->getQueryParams()['agrupar'] ?? 'dia') === 'mes' ? 'mes' : 'dia';
        $format = $groupBy === 'mes' ? '%Y-%m' : '%Y-%m-%d';

        $filas = $this->ventas($from, $to)
            ->selectRaw("DATE_FORMAT(ventas.fecha, '{$format}') AS periodo")
            ->selectRaw('COUNT(*) AS cantidad_ventas')
            ->selectRaw('SUM(ventas.subtotal - ventas.descuento) AS monto_neto')
            ->selectRaw('SUM(ventas.impuesto) AS impuesto')
            ->selectRaw('SUM(ventas.total) AS total')
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        $paso = $groupBy === 'mes' ? new DateInterval('P1M') : new DateInterval('P1D');
        $inicio = new DateTimeImmutable($groupBy === 'mes' ? substr($from, 0, 7) . '-01' : $from);
        $fin = (new DateTimeImmutable($to))->modify('+1 day');

        $data = [];
        foreach (new DatePeriod($inicio, $paso, $fin) as $fecha) {
            $periodo = $fecha->format($groupBy === 'mes' ? 'Y-m' : 'Y-m-d');
            $fila = $filas->get($periodo);

            $data[] = [
                'periodo' => $periodo,
                'cantidad_ventas' => (int) ($fila->cantidad_ventas ?? 0),
                'monto_neto' => round((float) ($fila->monto_neto ?? 0), 2),
                'impuesto' => round((float) ($fila->impuesto ?? 0), 2),
                'total' => round((float) ($fila->total ?? 0), 2),
            ];
        }

        $totalVentas = array_sum(array_column($data, 'cantidad_ventas'));
        $totalMonto = round(array_sum(array_column($data, 'total')), 2);

        return $this->json($response, [
            'filtros' => ['desde' => $from, 'hasta' => $to, 'agrupar' => $groupBy],
            'resumen' => [
                'cantidad_ventas' => $totalVentas,
                'total' => $totalMonto,
                'ticket_promedio' => $totalVentas > 0 ? round($totalMonto / $totalVentas, 2) : 0,
            ],
            'data' => $data,
        ], 200);
    }

    /** 2. Productos más vendidos (por unidades) */
    public function productosMasVendidos(Request $request, Response $response, array $args): Response {
        $rango = $this->rango($request);
        if ($rango === null) {
            return $this->errorRango($response);
        }
        [$from, $to] = $rango;

        $limit = filter_var($request->getQueryParams()['limite'] ?? 10, FILTER_VALIDATE_INT);
        $limit = ($limit === false || $limit < 1 || $limit > 50) ? 10 : $limit;

        $data = $this->lineas($from, $to)
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->select(['productos.id as producto_id', 'productos.codigo', 'productos.nombre', 'categorias.nombre as categoria'])
            ->selectRaw('SUM(detalle_ventas.cantidad) AS unidades_vendidas')
            ->selectRaw('SUM(detalle_ventas.subtotal) AS ingresos')
            ->selectRaw('COUNT(DISTINCT ventas.id) AS cantidad_ventas')
            ->groupBy('productos.id', 'productos.codigo', 'productos.nombre', 'categorias.nombre')
            ->orderByDesc('unidades_vendidas')
            ->orderByDesc('ingresos')
            ->limit($limit)
            ->get()
            ->map(static fn (object $f): array => [
                'producto_id' => (int) $f->producto_id,
                'codigo' => $f->codigo,
                'nombre' => $f->nombre,
                'categoria' => $f->categoria,
                'unidades_vendidas' => (int) $f->unidades_vendidas,
                'ingresos' => round((float) $f->ingresos, 2),
                'cantidad_ventas' => (int) $f->cantidad_ventas,
            ])
            ->all();

        return $this->json($response, [
            'filtros' => ['desde' => $from, 'hasta' => $to, 'limite' => $limit],
            'data' => $data,
        ], 200);
    }

    /** 3. Ventas por categoría (con porcentaje de participación) */
    public function ventasCategoria(Request $request, Response $response, array $args): Response {
        $rango = $this->rango($request);
        if ($rango === null) {
            return $this->errorRango($response);
        }
        [$from, $to] = $rango;

        $filas = $this->lineas($from, $to)
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->select(['categorias.id as categoria_id', 'categorias.nombre as categoria'])
            ->selectRaw('SUM(detalle_ventas.cantidad) AS unidades_vendidas')
            ->selectRaw('SUM(detalle_ventas.subtotal) AS ingresos')
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderByDesc('ingresos')
            ->get();

        $totalIngresos = (float) $filas->sum('ingresos');

        $data = $filas->map(static fn (object $f): array => [
            'categoria_id' => (int) $f->categoria_id,
            'categoria' => $f->categoria,
            'unidades_vendidas' => (int) $f->unidades_vendidas,
            'ingresos' => round((float) $f->ingresos, 2),
            'porcentaje' => $totalIngresos > 0 ? round(((float) $f->ingresos / $totalIngresos) * 100, 2) : 0,
        ])->all();

        return $this->json($response, [
            'filtros' => ['desde' => $from, 'hasta' => $to],
            'resumen' => ['ingresos' => round($totalIngresos, 2)],
            'data' => $data,
        ], 200);
    }

    /** 4. Desempeño por vendedor */
    public function ventasVendedor(Request $request, Response $response, array $args): Response {
        $rango = $this->rango($request);
        if ($rango === null) {
            return $this->errorRango($response);
        }
        [$from, $to] = $rango;

        $data = $this->ventas($from, $to)
            ->join('usuarios', 'usuarios.id', '=', 'ventas.usuario_id')
            ->select(['usuarios.id as usuario_id', 'usuarios.username', 'usuarios.nombre_completo'])
            ->selectRaw('COUNT(*) AS cantidad_ventas')
            ->selectRaw('SUM(ventas.total) AS total')
            ->selectRaw('AVG(ventas.total) AS ticket_promedio')
            ->groupBy('usuarios.id', 'usuarios.username', 'usuarios.nombre_completo')
            ->orderByDesc('total')
            ->get()
            ->map(static fn (object $f): array => [
                'usuario_id' => (int) $f->usuario_id,
                'username' => $f->username,
                'nombre_completo' => $f->nombre_completo,
                'cantidad_ventas' => (int) $f->cantidad_ventas,
                'total' => round((float) $f->total, 2),
                'ticket_promedio' => round((float) $f->ticket_promedio, 2),
            ])
            ->all();

        return $this->json($response, [
            'filtros' => ['desde' => $from, 'hasta' => $to],
            'data' => $data,
        ], 200);
    }

    /** 5. Ventas por método de pago */
    public function ventasMetodoPago(Request $request, Response $response, array $args): Response {
        $rango = $this->rango($request);
        if ($rango === null) {
            return $this->errorRango($response);
        }
        [$from, $to] = $rango;

        $data = $this->ventas($from, $to)
            ->select('ventas.metodo_pago')
            ->selectRaw('COUNT(*) AS cantidad_ventas')
            ->selectRaw('SUM(ventas.total) AS total')
            ->groupBy('ventas.metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(static fn (object $f): array => [
                'metodo_pago' => $f->metodo_pago,
                'cantidad_ventas' => (int) $f->cantidad_ventas,
                'total' => round((float) $f->total, 2),
            ])
            ->all();

        return $this->json($response, [
            'filtros' => ['desde' => $from, 'hasta' => $to],
            'data' => $data,
        ], 200);
    }

    /** 6. Estado del inventario: productos en stock bajo y valorización por categoría */
    public function inventario(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $stockBajo = $eloquent->table('productos')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->leftJoin('proveedores', 'proveedores.id', '=', 'productos.proveedor_id')
            ->where('productos.activo', true)
            ->whereColumn('productos.stock', '<=', 'productos.stock_minimo')
            ->select([
                'productos.id as producto_id', 'productos.codigo', 'productos.nombre',
                'categorias.nombre as categoria', 'proveedores.nombre as proveedor',
                'productos.stock', 'productos.stock_minimo'
            ])
            ->selectRaw('(productos.stock_minimo * 2 - productos.stock) AS sugerido_reabastecer')
            ->orderByRaw('(productos.stock - productos.stock_minimo) ASC')
            ->get()
            ->map(static fn (object $f): array => [
                'producto_id' => (int) $f->producto_id,
                'codigo' => $f->codigo,
                'nombre' => $f->nombre,
                'categoria' => $f->categoria,
                'proveedor' => $f->proveedor,
                'stock' => (int) $f->stock,
                'stock_minimo' => (int) $f->stock_minimo,
                'sugerido_reabastecer' => max(0, (int) $f->sugerido_reabastecer),
            ])
            ->all();

        $valorizacion = $eloquent->table('productos')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->where('productos.activo', true)
            ->select(['categorias.id as categoria_id', 'categorias.nombre as categoria'])
            ->selectRaw('COUNT(productos.id) AS productos')
            ->selectRaw('SUM(productos.stock) AS unidades')
            ->selectRaw('SUM(productos.stock * productos.precio_compra) AS valor_costo')
            ->selectRaw('SUM(productos.stock * productos.precio_venta) AS valor_venta')
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderByDesc('valor_costo')
            ->get()
            ->map(static fn (object $f): array => [
                'categoria_id' => (int) $f->categoria_id,
                'categoria' => $f->categoria,
                'productos' => (int) $f->productos,
                'unidades' => (int) $f->unidades,
                'valor_costo' => round((float) $f->valor_costo, 2),
                'valor_venta' => round((float) $f->valor_venta, 2),
                'ganancia_potencial' => round((float) $f->valor_venta - (float) $f->valor_costo, 2),
            ])
            ->all();

        return $this->json($response, [
            'resumen' => [
                'productos_stock_bajo' => count($stockBajo),
                'unidades' => array_sum(array_column($valorizacion, 'unidades')),
                'valor_costo' => round(array_sum(array_column($valorizacion, 'valor_costo')), 2),
                'valor_venta' => round(array_sum(array_column($valorizacion, 'valor_venta')), 2),
            ],
            'stock_bajo' => $stockBajo,
            'valorizacion' => $valorizacion,
        ], 200);
    }

    /** Indicadores clave (KPI) para el dashboard */
    public function dashboard(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();
        $hoy = (string) $eloquent->connection()->selectOne('SELECT CURDATE() AS hoy')->hoy;
        $inicioMes = substr($hoy, 0, 8) . '01';
        $inicioSemana = date('Y-m-d', strtotime($hoy . ' -6 days'));

        $resumen = function (string $from, string $to): array {
            $fila = $this->ventas($from, $to)
                ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(ventas.total), 0) AS total')
                ->first();

            $cantidad = (int) $fila->cantidad;
            $total = round((float) $fila->total, 2);

            return [
                'cantidad' => $cantidad,
                'total' => $total,
                'ticket_promedio' => $cantidad > 0 ? round($total / $cantidad, 2) : 0,
            ];
        };

        $porDia = $this->ventas($inicioSemana, $hoy)
            ->selectRaw('DATE(ventas.fecha) AS dia, COUNT(*) AS cantidad, SUM(ventas.total) AS total')
            ->groupBy('dia')
            ->get()
            ->keyBy('dia');

        $ultimos7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime($hoy . " -{$i} days"));
            $ultimos7[] = [
                'dia' => $dia,
                'cantidad' => (int) ($porDia->get($dia)->cantidad ?? 0),
                'total' => round((float) ($porDia->get($dia)->total ?? 0), 2),
            ];
        }

        $productos = $eloquent->table('productos')
            ->where('activo', true)
            ->selectRaw('COUNT(*) AS activos')
            ->selectRaw('SUM(stock <= stock_minimo) AS stock_bajo')
            ->selectRaw('COALESCE(SUM(stock * precio_compra), 0) AS valor_inventario')
            ->first();

        $topMes = $this->lineas($inicioMes, $hoy)
            ->select(['productos.id as producto_id', 'productos.nombre'])
            ->selectRaw('SUM(detalle_ventas.cantidad) AS unidades')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('unidades')
            ->limit(5)
            ->get()
            ->map(static fn (object $f): array => [
                'producto_id' => (int) $f->producto_id,
                'nombre' => $f->nombre,
                'unidades' => (int) $f->unidades,
            ])
            ->all();

        $ultimasVentas = $eloquent->table('ventas')
            ->leftJoin('clientes', 'clientes.id', '=', 'ventas.cliente_id')
            ->select(['ventas.id', 'ventas.fecha', 'clientes.nombre_completo as cliente', 'ventas.total', 'ventas.estado'])
            ->orderByDesc('ventas.fecha')
            ->orderByDesc('ventas.id')
            ->limit(5)
            ->get()
            ->map(static fn (object $f): array => [
                'id' => (int) $f->id,
                'fecha' => $f->fecha,
                'cliente' => $f->cliente ?? 'Cliente de contado',
                'total' => round((float) $f->total, 2),
                'estado' => $f->estado,
            ])
            ->all();

        return $this->json($response, [
            'fecha' => $hoy,
            'ventas_hoy' => $resumen($hoy, $hoy),
            'ventas_mes' => $resumen($inicioMes, $hoy),
            'ventas_ultimos_7_dias' => $ultimos7,
            'inventario' => [
                'productos_activos' => (int) $productos->activos,
                'productos_stock_bajo' => (int) $productos->stock_bajo,
                'valor_inventario' => round((float) $productos->valor_inventario, 2),
            ],
            'clientes_registrados' => $eloquent->table('clientes')->count(),
            'top_productos_mes' => $topMes,
            'ultimas_ventas' => $ultimasVentas,
        ], 200);
    }
}
