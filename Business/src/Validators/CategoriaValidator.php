<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

final class CategoriaValidator extends BaseValidator {
    protected function reglas(): array {
        return [
            'nombre' => [
                'regla' => v::stringType()
                    ->notBlank()
                    ->length(v::between(3, 100))
                    ->regex('/^[\p{L}0-9][\p{L}0-9 ,.&()-]*$/u'),
                'mensaje' => 'El nombre es obligatorio y debe tener entre 3 y 100 caracteres (letras, números, espacios y , . & ( ) -).',
            ],
            'descripcion' => [
                'regla' => v::stringType()->length(v::between(1, 255)),
                'mensaje' => 'La descripción debe tener máximo 255 caracteres.',
            ],
            'activo' => [
                'regla' => v::boolType(),
                'mensaje' => 'El campo activo debe ser true o false.',
            ],
        ];
    }

    protected function opcionales(): array {
        return ['descripcion', 'activo'];
    }
}
