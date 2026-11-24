<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ResponseInterface;

/**
 * Contrato del cliente HTTP hacia la capa de datos.
 * Los servicios de negocio dependen de esta interfaz (no de Guzzle), así
 * en las pruebas unitarias se reemplaza por un doble (tests/Fakes).
 */
interface DataServiceInterface
{
    public function get(string $path, array $query = []): ResponseInterface;

    public function post(string $path, string $body): ResponseInterface;

    public function put(string $path, string $body): ResponseInterface;

    public function delete(string $path, array $query = []): ResponseInterface;
}
