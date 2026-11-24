<?php
declare(strict_types=1);

namespace App\Exceptions;

/** Datos de entrada inválidos: 422 { "errores": { campo: mensaje } } */
final class ValidacionException extends ApiException
{
    public function __construct(private array $errores, int $status = 422)
    {
        parent::__construct($status, ['errores' => $errores]);
    }

    public function getErrores(): array
    {
        return $this->errores;
    }
}
