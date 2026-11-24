<?php
declare(strict_types=1);

namespace App\Exceptions;

/** La capa de datos no respondió: 502 Bad Gateway */
final class ServicioNoDisponibleException extends ApiException
{
    public function __construct(string $mensaje = 'El servicio de datos no está disponible')
    {
        parent::__construct(502, ['error' => $mensaje]);
    }
}
