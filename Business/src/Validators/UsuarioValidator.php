<?php
declare (strict_types=1);

namespace App\Validators;

use Respect\Validation\ValidatorBuilder as v;

final class UsuarioValidator extends BaseValidator {
    protected function reglas(): array {
        return [
            'username' => [
                'regla' => v::stringType()->notBlank()->length(v::between(8, 16))->regex('/^[a-z0-9]{8,16}$/'),
                'mensaje' => 'El username debe tener entre 8 y 16 caracteres y solo letras minúsculas y números, sin espacios.',
            ],
            'password' => [
                'regla' => v::stringType()
                    ->notBlank()
                    ->length(v::between(8, 64))
                    ->regex('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[#$@*])[\s\S]{8,64}$/'),
                'mensaje' => 'La contraseña debe tener entre 8 y 64 caracteres, incluir mayúscula, minúscula, número y un símbolo especial (#$@*).',
            ],
            'nombre_completo' => [
                'regla' => v::stringType()
                    ->notBlank()
                    ->length(v::between(6, 150))
                    ->regex('/^[A-Za-zÑñÁÉÍÓÚáéíóú]{2,}(?: [A-Za-zÑñÁÉÍÓÚáéíóú]{2,}){1,3}$/u'),
                'mensaje' => 'El nombre completo es obligatorio y debe tener entre 6 y 150 caracteres, solo letras y espacios.',
            ],
            'correo' => [
                'regla' => v::stringType()->email()->length(v::between(5, 150)),
                'mensaje' => 'El correo es obligatorio y debe tener un formato válido (máximo 150 caracteres).',
            ],
            'rol_id' => [
                'regla' => v::intType()->positive(),
                'mensaje' => 'Debe seleccionar un rol válido.',
            ],
            'activo' => [
                'regla' => v::boolType(),
                'mensaje' => 'El campo activo debe ser true o false.',
            ],
        ];
    }

    protected function opcionales(): array {
        return ['activo'];
    }
}
