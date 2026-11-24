<?php
declare (strict_types=1);

namespace App\Validators;

/**
 * Validador base. Es el mismo método validate() de MedicoValidator y
 * PacienteValidator de CitasMedicas, generalizado: cada validador concreto
 * solo define sus reglas y qué campos son opcionales.
 */
abstract class BaseValidator {
    /** @return array<string, array{regla: \Respect\Validation\ValidatorBuilder, mensaje: string}> */
    abstract protected function reglas(): array;

    /** Campos que pueden omitirse o venir vacíos; si traen valor, se validan */
    protected function opcionales(): array {
        return [];
    }

    public function validate (array $data, bool $parcial = false): array {
        $errores =[];
        $opcionales = $this->opcionales();

        foreach ($this->reglas() as $campo => $config) {
            if ($parcial && !array_key_exists($campo, $data))
                continue;
            $valor =$data[$campo] ?? null;
            $regla = $config['regla'];
            $mensaje = $config['mensaje'];
            if(in_array($campo, $opcionales, true) && ($valor === null || $valor === ""))
                continue;
            if($regla->validate($valor)->hasFailed()){
                $errores [$campo] = $mensaje;
            }
        }
        return $errores;
    }
}
