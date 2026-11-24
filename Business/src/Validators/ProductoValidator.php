<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

final class ProductoValidator extends BaseValidator {
    protected function reglas(): array {
        return [
            'codigo' => [
                'regla' => v::stringType()->notBlank()->regex('/^[A-Za-z0-9-]{3,50}$/'),
                'mensaje' => 'El código es obligatorio: entre 3 y 50 caracteres, solo letras, números y guiones (ej. código de barras).',
            ],
            'nombre' => [
                'regla' => v::stringType()->notBlank()->length(v::between(3, 150)),
                'mensaje' => 'El nombre es obligatorio y debe tener entre 3 y 150 caracteres.',
            ],
            'descripcion' => [
                'regla' => v::stringType()->length(v::between(1, 1000)),
                'mensaje' => 'La descripción debe tener máximo 1000 caracteres.',
            ],
            'categoria_id' => [
                'regla' => v::intType()->positive(),
                'mensaje' => 'Debe seleccionar una categoría válida.',
            ],
            'proveedor_id' => [
                'regla' => v::intType()->positive(),
                'mensaje' => 'Debe seleccionar un proveedor válido.',
            ],
            'precio_compra' => [
                'regla' => v::numericVal()->greaterThanOrEqual(0),
                'mensaje' => 'El precio de compra es obligatorio y debe ser un número mayor o igual a 0.',
            ],
            'precio_venta' => [
                'regla' => v::numericVal()->positive(),
                'mensaje' => 'El precio de venta es obligatorio y debe ser un número mayor a 0.',
            ],
            'stock' => [
                'regla' => v::intType()->greaterThanOrEqual(0),
                'mensaje' => 'El stock inicial debe ser un entero mayor o igual a 0.',
            ],
            'stock_minimo' => [
                'regla' => v::intType()->greaterThanOrEqual(0),
                'mensaje' => 'El stock mínimo debe ser un entero mayor o igual a 0.',
            ],
            'imagen_url' => [
                'regla' => v::stringType()->url()->length(v::between(1, 255)),
                'mensaje' => 'La imagen debe ser una URL válida de máximo 255 caracteres.',
            ],
            'activo' => [
                'regla' => v::boolType(),
                'mensaje' => 'El campo activo debe ser true o false.',
            ],
        ];
    }

    protected function opcionales(): array {
        return ['descripcion', 'proveedor_id', 'stock', 'stock_minimo', 'imagen_url', 'activo'];
    }
}
