<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ClienteController extends BaseController {
    private const COLUMNAS = [
        'clientes.id',
        'clientes.cedula',
        'clientes.nombre_completo',
        'clientes.telefono',
        'clientes.correo',
        'clientes.direccion',
        'clientes.latitud',
        'clientes.longitud',
        'clientes.creado_en'
    ];

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $cliente = Cliente::query()
                ->select(self::COLUMNAS)
                ->withCount('ventas')
                ->where('clientes.id', $id)
                ->first();

            if ($cliente === null) {
                return $this->json($response, ['error' => 'Cliente no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $cliente->toArray()], 200);
        }

        $clientes = Cliente::query()
            ->select(self::COLUMNAS)
            ->orderBy('nombre_completo')
            ->get()
            ->toArray();

        return $this->json($response, ['data' => $clientes], 200);
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
        $identityCard = trim((string) ($params['cedula'] ?? ''));

        $query = Cliente::query()
            ->select(self::COLUMNAS);

        if ($name !== '') {
            $query->where('clientes.nombre_completo', 'like', "%{$name}%");
        }
        if ($identityCard !== '') {
            $query->where('clientes.cedula', 'like', "%{$identityCard}%");
        }

        $query->orderBy('clientes.nombre_completo');

        return $this->paginado($response, $query, $offset, $limit, 'clientes.id');
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $this->body($request);

        $errors = [];
        $identityCard = trim((string) ($data['cedula'] ?? ''));
        $fullName = trim((string) ($data['nombre_completo'] ?? ''));
        $phone = $this->opcional($data, 'telefono');
        $email = $this->opcional($data, 'correo');
        $address = $this->opcional($data, 'direccion');
        $latitude = $this->coordenada($data, 'latitud', 90);
        $longitude = $this->coordenada($data, 'longitud', 180);

        if ($identityCard === '' || mb_strlen($identityCard) > 20) {
            $errors['cedula'] = 'Es obligatoria y debe tener máximo 20 caracteres.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors['nombre_completo'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
        }
        if ($phone !== null && mb_strlen($phone) > 20) {
            $errors['telefono'] = 'Debe tener máximo 20 caracteres.';
        }
        if ($email !== null && mb_strlen($email) > 150) {
            $errors['correo'] = 'Debe tener máximo 150 caracteres.';
        }
        if ($address !== null && mb_strlen($address) > 255) {
            $errors['direccion'] = 'Debe tener máximo 255 caracteres.';
        }
        if ($latitude === false) {
            $errors['latitud'] = 'Debe ser un número entre -90 y 90.';
        }
        if ($longitude === false) {
            $errors['longitud'] = 'Debe ser un número entre -180 y 180.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $this->eloquent();
            $cliente = Cliente::create([
                'cedula' => $identityCard,
                'nombre_completo' => $fullName,
                'telefono' => $phone,
                'correo' => $email,
                'direccion' => $address,
                'latitud' => $latitude,
                'longitud' => $longitude,
            ]);
        } catch (QueryException $exception) {
            if ($this->esDuplicado($exception)) {
                return $this->json($response, ['error' => 'La cédula ya está registrada.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $cliente->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de cliente válido.'], 400);
        }

        $cliente = Cliente::find($id);

        if ($cliente === null) {
            return $this->json($response, ['error' => 'Cliente no encontrado.'], 404);
        }

        $data = $this->body($request);

        if (isset($data['cedula'])) {
            $identityCard = trim((string) $data['cedula']);

            if ($identityCard === '' || mb_strlen($identityCard) > 20) {
                return $this->json($response, ['error' => 'Cédula inválida.'], 422);
            }

            $cliente->cedula = $identityCard;
        }

        if (isset($data['nombre_completo'])) {
            $fullName = trim((string) $data['nombre_completo']);

            if ($fullName === '' || mb_strlen($fullName) > 150) {
                return $this->json($response, ['error' => 'Nombre completo inválido.'], 422);
            }

            $cliente->nombre_completo = $fullName;
        }

        foreach (['telefono' => 20, 'correo' => 150, 'direccion' => 255] as $campo => $maximo) {
            if (!array_key_exists($campo, $data)) {
                continue;
            }

            $valor = $this->opcional($data, $campo);

            if ($valor !== null && mb_strlen($valor) > $maximo) {
                return $this->json($response, ['error' => "El campo {$campo} debe tener máximo {$maximo} caracteres."], 422);
            }

            $cliente->{$campo} = $valor;
        }

        foreach (['latitud' => 90, 'longitud' => 180] as $campo => $limite) {
            if (!array_key_exists($campo, $data)) {
                continue;
            }

            $valor = $this->coordenada($data, $campo, $limite);

            if ($valor === false) {
                return $this->json($response, ['error' => "El campo {$campo} debe ser un número entre -{$limite} y {$limite}."], 422);
            }

            $cliente->{$campo} = $valor;
        }

        try {
            $cliente->save();
        } catch (QueryException $exception) {
            if ($this->esDuplicado($exception)) {
                return $this->json($response, ['error' => 'La cédula ya está registrada.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $cliente->toArray()], 200);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de cliente válido.'], 400);
        }

        $cliente = Cliente::find($id);

        if ($cliente === null) {
            return $this->json($response, ['error' => 'Cliente no encontrado.'], 404);
        }

        // Verificar si el cliente a eliminar tiene ventas registradas
        $ventasCount = $eloquent->table('ventas')
            ->where('cliente_id', $cliente->id)
            ->count();

        if ($ventasCount > 0) {
            return $this->json($response, ['error' => 'No se puede eliminar el cliente porque tiene ventas registradas.'], 409);
        }

        $cliente->delete();

        return $this->json($response, ['message' => 'Cliente eliminado exitosamente.'], 200);
    }

    /** null si no viene, false si es inválida, float si es válida */
    private function coordenada(array $data, string $campo, int $limite): float|false|null {
        if (!array_key_exists($campo, $data) || $data[$campo] === null || $data[$campo] === '') {
            return null;
        }

        $valor = filter_var($data[$campo], FILTER_VALIDATE_FLOAT);

        if ($valor === false || $valor < -$limite || $valor > $limite) {
            return false;
        }

        return $valor;
    }
}
