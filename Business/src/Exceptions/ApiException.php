<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción de negocio con código HTTP y cuerpo JSON.
 * El manejador central de errores (src/app/errores.php) la convierte en
 * la respuesta: status = getStatus(), body = getPayload().
 */
class ApiException extends RuntimeException
{
    public function __construct(private int $status, private array $payload)
    {
        parent::__construct((string) ($payload['error'] ?? 'Error de la API.'), $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public static function con(int $status, string $mensaje): self
    {
        return new self($status, ['error' => $mensaje]);
    }
}
