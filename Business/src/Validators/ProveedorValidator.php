<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

final class ProveedorValidator extends BaseValidator {
    protected function reglas(): array {
        return [
            'cedula_juridica' => [
                'regla' => v::stringType()
                    ->notBlank()
                    ->regex('/^3[0-9]{9}$/'),
                'mensaje' => 'La cédula jurídica es obligatoria y debe tener 10 dígitos iniciando con 3 (sin guiones).',
            ],
            'nombre' => [
                'regla' => v::stringType()->notBlank()->length(v::between(3, 150)),
                'mensaje' => 'El nombre es obligatorio y debe tener entre 3 y 150 caracteres.',
            ],
            'telefono' => [
                'regla' => v::stringType()
                    ->regex('/^[2-9][0-9]{3}-[0-9]{4}$/')
                    ->length(v::equals(9)),
                'mensaje' => 'El teléfono debe tener el formato 0000-0000 y 9 caracteres.',
            ],
            'correo' => [
                'regla' => v::stringType()->email()->length(v::between(5, 150)),
                'mensaje' => 'El correo debe tener un formato válido (máximo 150 caracteres).',
            ],
            'direccion' => [
                'regla' => v::stringType()->length(v::between(1, 255)),
                'mensaje' => 'La dirección debe tener máximo 255 caracteres.',
            ],
            'activo' => [
                'regla' => v::boolType(),
                'mensaje' => 'El campo activo debe ser true o false.',
            ],
        ];
    }

    protected function opcionales(): array {
        return ['telefono', 'correo', 'direccion', 'activo'];
    }
}
