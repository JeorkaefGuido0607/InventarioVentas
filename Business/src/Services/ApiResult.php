<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ResponseInterface;

/**
 * Resultado de una operación de negocio: código HTTP + cuerpo JSON.
 * Los controladores solo lo escriben en la respuesta.
 */
final class ApiResult
{
    public function __construct(
        public readonly int $status,
        public readonly array $body
    ) {}

    /** Convierte la respuesta de la capa de datos en un resultado de negocio */
    public static function desde(ResponseInterface $response): self
    {
        $body = json_decode((string) $response->getBody(), true);

        if (!is_array($body)) {
            return new self(502, ['error' => 'Respuesta inválida del servicio de datos.']);
        }

        // La capa de datos usa "errors"; la API pública usa siempre "errores"
        if (isset($body['errors']) && !isset($body['errores'])) {
            $body['errores'] = $body['errors'];
            unset($body['errors']);
        }

        return new self($response->getStatusCode(), $body);
    }

    public function exitoso(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** Atajo para el contenido de "data" (o [] si no viene) */
    public function data(): array
    {
        $data = $this->body['data'] ?? [];
        return is_array($data) ? $data : [];
    }
}
