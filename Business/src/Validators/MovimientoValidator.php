<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

final class MovimientoValidator extends BaseValidator {
    public const TIPOS = ['Entrada', 'Salida', 'Ajuste'];

    protected function reglas(): array {
        return [
            'producto_id' => [
                'regla' => v::intType()->positive(),
                'mensaje' => 'Debe seleccionar un producto válido.',
            ],
            'tipo' => [
                'regla' => v::stringType()->in(self::TIPOS),
                'mensaje' => 'El tipo de movimiento debe ser Entrada, Salida o Ajuste.',
            ],
            'cantidad' => [
                'regla' => v::intType()->greaterThanOrEqual(0)->lessThanOrEqual(100000),
                'mensaje' => 'La cantidad debe ser un entero entre 0 y 100000.',
            ],
            'motivo' => [
                'regla' => v::stringType()->notBlank()->length(v::between(3, 255)),
                'mensaje' => 'El motivo es obligatorio y debe tener entre 3 y 255 caracteres.',
            ],
        ];
    }
}
