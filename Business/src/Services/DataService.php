<?php
namespace App\Services;

use App\Exceptions\ServicioNoDisponibleException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

final class DataService implements DataServiceInterface {
    private ClientInterface $client;
    public function __construct(object $config) {
        $this->client = new Client([
            'base_uri' => rtrim($config->api_data, '/') . '/',
            'timeout' => $config->timeout,
            'connect_timeout' => $config->timeout,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
    }
    public function get(string $path, array $query = []): ResponseInterface {
        return $this->send('GET', $path,
        ['query' => $query]);
    }

    public function post(string $path, string $body): ResponseInterface  {
        return $this->send('POST', $path,
        ['body' => $body,
        'headers' => [
            'Content-Type' => 'application/json'
            ]
        ]);
    }

    public function put(string $path, string $body): ResponseInterface  {
        return $this->send('PUT', $path,
        ['body' => $body,
        'headers' => [
            'Content-Type' => 'application/json'
            ]
        ]);
    }

    public function delete(string $path, array $query = []): ResponseInterface  {
        return $this->send('DELETE', $path,
        ['query' => $query]);
    }

    /**
     * Centraliza el manejo de fallas de red: en CitasMedicas cada método del
     * controlador atrapaba ConnectException/RequestException; aquí se
     * traducen una sola vez a ServicioNoDisponibleException (502).
     */
    private function send(string $method, string $path, array $options): ResponseInterface {
        try {
            return $this->client->request($method, ltrim($path, '/'), $options);
        } catch (ConnectException) {
            throw new ServicioNoDisponibleException('El servicio de datos no está disponible');
        } catch (GuzzleException) {
            throw new ServicioNoDisponibleException('No se pudo consultar el servicio de datos');
        }
    }
}
