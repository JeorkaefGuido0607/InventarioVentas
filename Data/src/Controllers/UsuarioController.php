<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UsuarioController extends BaseController {
    private const COLUMNAS = [
        'usuarios.id',
        'usuarios.username',
        'usuarios.nombre_completo',
        'usuarios.correo',
        'usuarios.rol_id',
        'roles.nombre as rol',
        'usuarios.activo',
        'usuarios.creado_en'
    ];

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $usuario = User::query()
                ->select(self::COLUMNAS)
                ->join('roles', 'usuarios.rol_id', '=', 'roles.id')
                ->where('usuarios.id', $id)
                ->first();

            if ($usuario === null) {
                return $this->json($response, ['error' => 'Usuario no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $usuario->toArray()], 200);
        }

        $usuarios = User::query()
            ->select(self::COLUMNAS)
            ->join('roles', 'usuarios.rol_id', '=', 'roles.id')
            ->orderBy('usuarios.nombre_completo')
            ->get()
            ->toArray();

        return $this->json($response, ['data' => $usuarios], 200);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $paginacion = $this->paginacion($args);
        if ($paginacion === null) {
            return $this->errorPaginacion($response);
        }
        [$offset, $limit] = $paginacion;

        $params = $request->getQueryParams();
        $name = trim((string) ($params['nombre'] ?? $params['nombre_completo'] ?? ''));
        $username = trim((string) ($params['username'] ?? ''));
        $roleId = filter_var($params['rol_id'] ?? null, FILTER_VALIDATE_INT);
        $active = $params['activo'] ?? null;

        $query = User::query()
            ->select(self::COLUMNAS)
            ->join('roles', 'usuarios.rol_id', '=', 'roles.id');

        if ($name !== '') {
            $query->where('usuarios.nombre_completo', 'like', "%{$name}%");
        }
        if ($username !== '') {
            $query->where('usuarios.username', 'like', "%{$username}%");
        }
        if ($roleId !== false && $roleId !== null) {
            $query->where('usuarios.rol_id', $roleId);
        }
        if ($active !== null && $active !== '') {
            $query->where('usuarios.activo', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('usuarios.nombre_completo');

        return $this->paginado($response, $query, $offset, $limit, 'usuarios.id');
    }

    public function roles(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $roles = Rol::query()->orderBy('id')->get()->toArray();

        return $this->json($response, ['data' => $roles], 200);
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $this->body($request);

        $errors = [];
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $roleId = filter_var($data['rol_id'] ?? null, FILTER_VALIDATE_INT);
        $fullName = trim((string) ($data['nombre_completo'] ?? ''));
        $email = trim((string) ($data['correo'] ?? ''));

        if ($username === '' || mb_strlen($username) > 50) {
            $errors['username'] = 'Es obligatorio y debe tener máximo 50 caracteres.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Debe tener al menos 8 caracteres.';
        }
        if ($roleId === false || $roleId === null || $roleId < 1) {
            $errors['rol_id'] = 'Debe ser un entero positivo.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors['nombre_completo'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
        }
        if ($email === '' || mb_strlen($email) > 150) {
            $errors['correo'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $this->eloquent();
            $usuario = User::create([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'rol_id' => $roleId,
                'nombre_completo' => $fullName,
                'correo' => $email,
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            ]);
        } catch (QueryException $exception) {
            return $this->errorEscritura($response, $exception);
        }

        return $this->json($response, ['data' => $usuario->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de usuario válido.'], 400);
        }

        $usuario = User::find($id);

        if ($usuario === null) {
            return $this->json($response, ['error' => 'Usuario no encontrado.'], 404);
        }

        $data = $this->body($request);

        if (isset($data['username'])) {
            $username = trim((string) $data['username']);

            if ($username === '' || mb_strlen($username) > 50) {
                return $this->json($response, ['error' => 'Username inválido.'], 422);
            }

            $usuario->username = $username;
        }

        if (isset($data['password'])) {
            $password = (string) $data['password'];

            if (mb_strlen($password) < 8) {
                return $this->json($response, ['error' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
            }

            $usuario->password = password_hash($password, PASSWORD_DEFAULT);
        }

        if (isset($data['rol_id'])) {
            $roleId = filter_var($data['rol_id'], FILTER_VALIDATE_INT);

            if ($roleId === false || $roleId < 1) {
                return $this->json($response, ['error' => 'Rol inválido.'], 422);
            }

            $usuario->rol_id = $roleId;
        }

        if (isset($data['nombre_completo'])) {
            $fullName = trim((string) $data['nombre_completo']);

            if ($fullName === '' || mb_strlen($fullName) > 150) {
                return $this->json($response, ['error' => 'Nombre completo inválido.'], 422);
            }

            $usuario->nombre_completo = $fullName;
        }

        if (isset($data['correo'])) {
            $email = trim((string) $data['correo']);

            if ($email === '' || mb_strlen($email) > 150) {
                return $this->json($response, ['error' => 'Correo inválido.'], 422);
            }

            $usuario->correo = $email;
        }

        if (array_key_exists('activo', $data)) {
            $usuario->activo = (bool) $data['activo'];
        }

        try {
            $usuario->save();
        } catch (QueryException $exception) {
            return $this->errorEscritura($response, $exception);
        }

        return $this->json($response, ['data' => $usuario->toArray()], 200);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de usuario válido.'], 400);
        }

        $usuario = User::find($id);

        if ($usuario === null) {
            return $this->json($response, ['error' => 'Usuario no encontrado.'], 404);
        }

        // Un usuario con ventas registradas no se borra: se desactiva (activo = false)
        $ventasCount = $eloquent->table('ventas')
            ->where('usuario_id', $usuario->id)
            ->count();

        if ($ventasCount > 0) {
            return $this->json($response, ['error' => 'No se puede eliminar el usuario porque tiene ventas registradas. Desactívelo en su lugar.'], 409);
        }

        $usuario->delete();

        return $this->json($response, ['message' => 'Usuario eliminado exitosamente.'], 200);
    }

    private function errorEscritura(Response $response, QueryException $exception): Response {
        if ($this->esDuplicado($exception)) {
            if (str_contains($this->mensajeDuplicado($exception), 'username')) {
                return $this->json($response, ['error' => 'El nombre de usuario ya está registrado.'], 409);
            }

            return $this->json($response, ['error' => 'El correo ya está registrado.'], 409);
        }

        if ($this->esLlaveForaneaInvalida($exception)) {
            return $this->json($response, ['error' => 'El rol indicado no existe.'], 422);
        }

        throw $exception;
    }
}
