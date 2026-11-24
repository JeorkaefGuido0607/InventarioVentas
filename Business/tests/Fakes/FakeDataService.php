<?php
declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\DataServiceInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Doble de prueba de la capa de datos: no hace peticiones HTTP.
 * Se le programan respuestas por "MÉTODO ruta" y registra cada llamada
 * para poder verificar qué envió el servicio de negocio.
 */
final class FakeDataService implements DataServiceInterface
{
    /** @var array<string, array{0:int,1:array}> */
    private array $respuestas = [];

    /** @var array<int, array{metodo:string, ruta:string, query:array, body:?array}> */
    public array $llamadas = [];

    public function responder(string $metodo, string $ruta, int $status, array $body): self
    {
        $this->respuestas["{$metodo} {$ruta}"] = [$status, $body];
        return $this;
    }

    public function get(string $path, array $query = []): ResponseInterface
    {
        return $this->registrar('GET', $path, $query, null);
    }

    public function post(string $path, string $body): ResponseInterface
    {
        return $this->registrar('POST', $path, [], json_decode($body, true));
    }

    public function put(string $path, string $body): ResponseInterface
    {
        return $this->registrar('PUT', $path, [], json_decode($body, true));
    }

    public function delete(string $path, array $query = []): ResponseInterface
    {
        return $this->registrar('DELETE', $path, $query, null);
    }

    public function ultimaLlamada(): ?array
    {
        return $this->llamadas === [] ? null : $this->llamadas[array_key_last($this->llamadas)];
    }

    /** Llamadas que no son GET (las que escriben en la base de datos) */
    public function escrituras(): array
    {
        return array_values(array_filter($this->llamadas, static fn (array $l) => $l['metodo'] !== 'GET'));
    }

    private function registrar(string $metodo, string $ruta, array $query, ?array $body): ResponseInterface
    {
        $this->llamadas[] = ['metodo' => $metodo, 'ruta' => $ruta, 'query' => $query, 'body' => $body];

        [$status, $respuesta] = $this->respuestas["{$metodo} {$ruta}"]
            ?? [200, ['data' => $body ?? []]];

        return new Response($status, ['Content-Type' => 'application/json'], json_encode($respuesta));
    }
}
