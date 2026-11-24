<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

/**
 * Valida el encabezado de la venta. Las líneas del detalle se validan en
 * VentaService porque dependen del stock y precio de cada producto.
 */
final class VentaValidator extends BaseValidator {
    public const METODOS_PAGO = ['Efectivo', 'Tarjeta', 'SINPE Móvil', 'Transferencia'];
    public const DESCUENTO_MAXIMO = 50;

    protected function reglas(): array {
        return [
            'cliente_id' => [
                'regla' => v::intType()->positive(),
                'mensaje' => 'Debe seleccionar un cliente válido.',
            ],
            'metodo_pago' => [
                'regla' => v::stringType()->in(self::METODOS_PAGO),
                'mensaje' => 'El método de pago debe ser: ' . implode(', ', self::METODOS_PAGO) . '.',
            ],
            'descuento_porcentaje' => [
                'regla' => v::numericVal()->between(0, self::DESCUENTO_MAXIMO),
                'mensaje' => 'El descuento debe ser un porcentaje entre 0 y ' . self::DESCUENTO_MAXIMO . '.',
            ],
            'observaciones' => [
                'regla' => v::stringType()->length(v::between(1, 255)),
                'mensaje' => 'Las observaciones deben tener máximo 255 caracteres.',
            ],
            'detalle' => [
                'regla' => v::arrayType()->notBlank(),
                'mensaje' => 'La venta debe incluir al menos un producto en el detalle.',
            ],
        ];
    }

    protected function opcionales(): array {
        return ['cliente_id', 'metodo_pago', 'descuento_porcentaje', 'observaciones'];
    }
}
