<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use Firebase\JWT\JWT;

/**
 * Autenticación: verifica las credenciales en la capa de datos y emite
 * el JWT firmado (HS256) con el id, el usuario y el rol.
 */
final class AuthService
{
    public const EMISOR = 'inventario-ventas-business';

    public function __construct(
        private DataServiceInterface $dataService,
        private object $config
    ) {}

    public function login(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new ValidacionException(['credenciales' => 'El username y la contraseña son obligatorios.']);
        }

        $resultado = ApiResult::desde($this->dataService->post('auth/credentials', json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR)));

        if ($resultado->status === 401 || $resultado->status === 422) {
            throw ApiException::con(401, 'Credenciales inválidas.');
        }
        if (!$resultado->exitoso()) {
            throw new ApiException($resultado->status, $resultado->body);
        }

        $credenciales = $resultado->body;
        $usuario = [
            'id' => (int) $credenciales['id'],
            'username' => $credenciales['username'],
            'nombre_completo' => $credenciales['nombre_completo'] ?? $credenciales['username'],
            'rol_id' => (int) $credenciales['rol_id'],
            'rol' => $credenciales['rol'],
        ];

        $ahora = time();
        $payload = [
            'iss' => self::EMISOR,
            'iat' => $ahora,
            'exp' => $ahora + $this->config->jwt_ttl,
            'sub' => (string) $usuario['id'],
            'username' => $usuario['username'],
            'nombre' => $usuario['nombre_completo'],
            'rol_id' => $usuario['rol_id'],
            'rol' => $usuario['rol'],
        ];

        return [
            'token' => JWT::encode($payload, $this->config->key, 'HS256'),
            'token_type' => 'Bearer',
            'expires_in' => $this->config->jwt_ttl,
            'user' => $usuario,
        ];
    }

    /** Datos del usuario autenticado a partir del token ya validado */
    public static function perfil(array $token): array
    {
        return [
            'id' => (int) ($token['sub'] ?? 0),
            'username' => $token['username'] ?? null,
            'nombre_completo' => $token['nombre'] ?? null,
            'rol_id' => (int) ($token['rol_id'] ?? 0),
            'rol' => $token['rol'] ?? null,
            'expira' => isset($token['exp']) ? date('c', (int) $token['exp']) : null,
        ];
    }
}
