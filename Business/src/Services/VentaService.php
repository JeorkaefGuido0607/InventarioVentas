<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use App\Validators\BaseValidator;
use App\Validators\VentaValidator;

/**
 * Reglas de negocio de las ventas:
 *  - el precio de cada línea es el precio de venta vigente del producto
 *    (el cliente nunca envía precios),
 *  - no se vende un producto inactivo ni más unidades de las disponibles,
 *  - IVA del 13 % sobre el monto con descuento (descuento máximo 50 %),
 *  - los montos se calculan en céntimos (enteros) para evitar errores de
 *    redondeo con decimales.
 * La capa de datos guarda la venta en una transacción y vuelve a
 * verificar el stock con bloqueo de filas.
 */
final class VentaService extends CrudService
{
    public const IVA_PORCENTAJE = 13;
    public const CANTIDAD_MAXIMA_LINEA = 1000;
    private const CAMPOS_EDITABLES = ['metodo_pago', 'cliente_id', 'observaciones'];

    protected function recurso(): string
    {
        return 'ventas';
    }

    protected function validador(): BaseValidator
    {
        return new VentaValidator();
    }

    /** Registra la venta */
    public function crear(array $data, array $usuario = []): ApiResult
    {
        $venta = $this->armarVenta($data);

        $payload = [
            'cliente_id' => $venta['cliente_id'],
            'usuario_id' => $this->usuarioId($usuario),
            'metodo_pago' => $venta['metodo_pago'],
            'observaciones' => $venta['observaciones'],
            'subtotal' => $venta['totales']['subtotal'],
            'descuento' => $venta['totales']['descuento'],
            'impuesto' => $venta['totales']['impuesto'],
            'total' => $venta['totales']['total'],
            'detalle' => array_map(static fn (array $l): array => [
                'producto_id' => $l['producto_id'],
                'cantidad' => $l['cantidad'],
                'precio_unitario' => $l['precio_unitario'],
            ], $venta['lineas']),
        ];

        return ApiResult::desde($this->dataService->post('ventas', $this->aJson($payload)));
    }

    /** Calcula la venta sin guardarla (carrito / vista previa de la factura) */
    public function cotizar(array $data): ApiResult
    {
        $venta = $this->armarVenta($data);

        return new ApiResult(200, ['data' => [
            'cliente_id' => $venta['cliente_id'],
            'metodo_pago' => $venta['metodo_pago'],
            'descuento_porcentaje' => $venta['descuento_porcentaje'],
            'iva_porcentaje' => self::IVA_PORCENTAJE,
            'detalle' => $venta['lineas'],
            ...$venta['totales'],
        ]]);
    }

    /** Solo se corrigen datos que no afectan montos ni inventario */
    public function actualizar(string $id, array $data, array $usuario = []): ApiResult
    {
        $noEditables = array_diff(array_keys($data), self::CAMPOS_EDITABLES);

        if ($noEditables !== []) {
            throw new ValidacionException([
                'body' => 'Solo se pueden modificar: ' . implode(', ', self::CAMPOS_EDITABLES)
                    . '. Para cambiar productos o montos anule la venta y registre una nueva.',
            ]);
        }

        return parent::actualizar($id, $data, $usuario);
    }

    /** DELETE = anular: la capa de datos devuelve el stock y registra quién anuló */
    public function eliminar(string $id, array $usuario = []): ApiResult
    {
        $this->validarId($id);

        return ApiResult::desde($this->dataService->delete(
            'ventas/' . rawurlencode($id),
            ['usuario_id' => $this->usuarioId($usuario)]
        ));
    }

    /**
     * Calcula subtotal, descuento, IVA y total.
     * @param array<array{cantidad:int, precio_unitario:float|int}> $lineas
     */
    public static function calcularTotales(array $lineas, float|int $descuentoPorcentaje = 0): array
    {
        $subtotal = 0;   // en céntimos
        foreach ($lineas as $linea) {
            $subtotal += $linea['cantidad'] * (int) round($linea['precio_unitario'] * 100);
        }

        $descuento = (int) round($subtotal * $descuentoPorcentaje / 100);
        $base = $subtotal - $descuento;
        $impuesto = (int) round($base * self::IVA_PORCENTAJE / 100);
        $total = $base + $impuesto;

        return [
            'subtotal' => $subtotal / 100,
            'descuento' => $descuento / 100,
            'impuesto' => $impuesto / 100,
            'total' => $total / 100,
        ];
    }

    /**
     * Valida la venta completa y consulta en la capa de datos el cliente y
     * cada producto (existencia, estado, stock y precio vigente).
     */
    private function armarVenta(array $data): array
    {
        $errores = $this->validador()->validate($data);

        $detalle = $data['detalle'] ?? null;
        if (!isset($errores['detalle'])) {
            $errores += $this->validarLineas($detalle);
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        $clienteId = $data['cliente_id'] ?? null;
        if ($clienteId !== null) {
            $cliente = ApiResult::desde($this->dataService->get('clientes/' . $clienteId));
            if ($cliente->status === 404) {
                throw new ValidacionException(['cliente_id' => 'El cliente indicado no existe.']);
            }
            $this->exigirExito($cliente);
        }

        $lineas = [];
        foreach ($detalle as $i => $item) {
            $resultado = ApiResult::desde($this->dataService->get('productos/' . $item['producto_id']));

            if ($resultado->status === 404) {
                $errores["detalle.{$i}"] = "El producto #{$item['producto_id']} no existe.";
                continue;
            }
            $this->exigirExito($resultado);

            $producto = $resultado->data();

            if (($producto['activo'] ?? false) !== true) {
                $errores["detalle.{$i}"] = "El producto '{$producto['nombre']}' está inactivo y no se puede vender.";
                continue;
            }
            if ((int) $producto['stock'] < $item['cantidad']) {
                $errores["detalle.{$i}"] = "Stock insuficiente para '{$producto['nombre']}'. Disponible: {$producto['stock']}.";
                continue;
            }

            $precio = (float) $producto['precio_venta'];
            $lineas[] = [
                'producto_id' => (int) $producto['id'],
                'codigo' => $producto['codigo'],
                'producto' => $producto['nombre'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $precio,
                'subtotal' => self::calcularTotales([['cantidad' => $item['cantidad'], 'precio_unitario' => $precio]])['subtotal'],
            ];
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        $descuento = (float) ($data['descuento_porcentaje'] ?? 0);

        return [
            'cliente_id' => $clienteId,
            'metodo_pago' => ($data['metodo_pago'] ?? '') !== '' ? $data['metodo_pago'] : 'Efectivo',
            'observaciones' => ($data['observaciones'] ?? '') !== '' ? trim($data['observaciones']) : null,
            'descuento_porcentaje' => $descuento,
            'lineas' => $lineas,
            'totales' => self::calcularTotales($lineas, $descuento),
        ];
    }

    /** @return array<string,string> */
    private function validarLineas(mixed $detalle): array
    {
        if (!is_array($detalle) || !array_is_list($detalle)) {
            return ['detalle' => 'El detalle debe ser una lista de productos.'];
        }

        $errores = [];
        $vistos = [];

        foreach ($detalle as $i => $item) {
            $productoId = is_array($item) ? ($item['producto_id'] ?? null) : null;
            $cantidad = is_array($item) ? ($item['cantidad'] ?? null) : null;

            if (!is_int($productoId) || $productoId < 1) {
                $errores["detalle.{$i}"] = 'Cada línea requiere un producto_id entero positivo.';
            } elseif (!is_int($cantidad) || $cantidad < 1 || $cantidad > self::CANTIDAD_MAXIMA_LINEA) {
                $errores["detalle.{$i}"] = 'La cantidad debe ser un entero entre 1 y ' . self::CANTIDAD_MAXIMA_LINEA . '.';
            } elseif (isset($vistos[$productoId])) {
                $errores["detalle.{$i}"] = 'El producto está repetido; sume las cantidades en una sola línea.';
            }

            if (is_int($productoId)) {
                $vistos[$productoId] = true;
            }
        }

        return $errores;
    }

    private function exigirExito(ApiResult $resultado): void
    {
        if (!$resultado->exitoso()) {
            throw new ApiException($resultado->status, $resultado->body);
        }
    }
}
