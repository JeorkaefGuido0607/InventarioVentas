<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends BaseController
{
    public function credentials(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->json($response, ['error' => 'El username y la contraseña son obligatorios.'], 422);
        }

        try {
            $this->eloquent();
            $user = User::query()
                ->select(['usuarios.*', 'roles.nombre as rol'])
                ->join('roles', 'usuarios.rol_id', '=', 'roles.id')
                ->where('usuarios.username', $username)
                ->where('usuarios.activo', true)
                ->first();
        } catch (QueryException) {
            return $this->json($response, ['error' => 'No fue posible validar las credenciales.'], 500);
        }

        if ($user === null || !password_verify($password, $user->password)) {
            return $this->json($response, ['error' => 'Credenciales inválidas.'], 401);
        }

        return $this->json($response, [
            'id' => (int) $user->id,
            'username' => $user->username,
            'rol_id' => (int) $user->rol_id,
            'rol' => $user->rol,
            'nombre_completo' => $user->nombre_completo,
        ], 200);
    }
}
