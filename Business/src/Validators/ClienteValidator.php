<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

final class ClienteValidator extends BaseValidator {
    protected function reglas(): array {
        return [
            'cedula' => [
                'regla' => v::stringType()
                    ->notBlank()
                    ->regex('/^(?:[1-9][0-9]{8}|1[0-9]{11})$/'),
                'mensaje' => 'La cédula es obligatoria: 9 dígitos (nacional) o 12 dígitos iniciando con 1 (DIMEX).',
            ],
            'nombre_completo' => [
                'regla' => v::stringType()
                    ->notBlank()
                    ->length(v::between(11, 150))
                    ->regex('/^[A-Za-zÑñÁÉÍÓÚáéíóú]{2,}(?: [A-Za-zÑñÁÉÍÓÚáéíóú]{2,}){1,3}$/u'),
                'mensaje' => 'El nombre completo es obligatorio y debe tener entre 11 y 150 carácteres, solo letras y espacios.',
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
            'latitud' => [
                'regla' => v::numericVal()->between(-90, 90),
                'mensaje' => 'La latitud debe ser un número entre -90 y 90.',
            ],
            'longitud' => [
                'regla' => v::numericVal()->between(-180, 180),
                'mensaje' => 'La longitud debe ser un número entre -180 y 180.',
            ],
        ];
    }

    protected function opcionales(): array {
        return ['telefono', 'correo', 'direccion', 'latitud', 'longitud'];
    }
}
