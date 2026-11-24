<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Proveedor;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProveedorController extends BaseController {
    private const COLUMNAS = [
        'proveedores.id',
        'proveedores.cedula_juridica',
        'proveedores.nombre',
        'proveedores.telefono',
        'proveedores.correo',
        'proveedores.direccion',
        'proveedores.activo'
    ];

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $proveedor = Proveedor::query()
                ->select(self::COLUMNAS)
                ->withCount('productos')
                ->where('proveedores.id', $id)
                ->first();

            if ($proveedor === null) {
                return $this->json($response, ['error' => 'Proveedor no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $proveedor->toArray()], 200);
        }

        $proveedores = Proveedor::query()
            ->select(self::COLUMNAS)
            ->withCount('productos')
            ->orderBy('nombre')
            ->get()
            ->toArray();

        return $this->json($response, ['data' => $proveedores], 200);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $paginacion = $this->paginacion($args);
        if ($paginacion === null) {
            return $this->errorPaginacion($response);
        }
        [$offset, $limit] = $paginacion;

        $params = $request->getQueryParams();
        $name = trim((string) ($params['nombre'] ?? ''));
        $legalId = trim((string) ($params['cedula_juridica'] ?? $params['cedula'] ?? ''));
        $active = $params['activo'] ?? null;

        $query = Proveedor::query()
            ->select(self::COLUMNAS)
            ->withCount('productos');

        if ($name !== '') {
            $query->where('proveedores.nombre', 'like', "%{$name}%");
        }
        if ($legalId !== '') {
            $query->where('proveedores.cedula_juridica', 'like', "%{$legalId}%");
        }
        if ($active !== null && $active !== '') {
            $query->where('proveedores.activo', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('proveedores.nombre');

        return $this->paginado($response, $query, $offset, $limit, 'proveedores.id');
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $this->body($request);

        $errors = [];
        $legalId = trim((string) ($data['cedula_juridica'] ?? ''));
        $name = trim((string) ($data['nombre'] ?? ''));
        $phone = $this->opcional($data, 'telefono');
        $email = $this->opcional($data, 'correo');
        $address = $this->opcional($data, 'direccion');

        if ($legalId === '' || mb_strlen($legalId) > 20) {
            $errors['cedula_juridica'] = 'Es obligatoria y debe tener máximo 20 caracteres.';
        }
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['nombre'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
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

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $this->eloquent();
            $proveedor = Proveedor::create([
                'cedula_juridica' => $legalId,
                'nombre' => $name,
                'telefono' => $phone,
                'correo' => $email,
                'direccion' => $address,
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            ]);
        } catch (QueryException $exception) {
            if ($this->esDuplicado($exception)) {
                return $this->json($response, ['error' => 'La cédula jurídica ya está registrada.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $proveedor->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de proveedor válido.'], 400);
        }

        $proveedor = Proveedor::find($id);

        if ($proveedor === null) {
            return $this->json($response, ['error' => 'Proveedor no encontrado.'], 404);
        }

        $data = $this->body($request);

        if (isset($data['cedula_juridica'])) {
            $legalId = trim((string) $data['cedula_juridica']);

            if ($legalId === '' || mb_strlen($legalId) > 20) {
                return $this->json($response, ['error' => 'Cédula jurídica inválida.'], 422);
            }

            $proveedor->cedula_juridica = $legalId;
        }

        if (isset($data['nombre'])) {
            $name = trim((string) $data['nombre']);

            if ($name === '' || mb_strlen($name) > 150) {
                return $this->json($response, ['error' => 'Nombre inválido.'], 422);
            }

            $proveedor->nombre = $name;
        }

        foreach (['telefono' => 20, 'correo' => 150, 'direccion' => 255] as $campo => $maximo) {
            if (!array_key_exists($campo, $data)) {
                continue;
            }

            $valor = $this->opcional($data, $campo);

            if ($valor !== null && mb_strlen($valor) > $maximo) {
                return $this->json($response, ['error' => "El campo {$campo} debe tener máximo {$maximo} caracteres."], 422);
            }

            $proveedor->{$campo} = $valor;
        }

        if (array_key_exists('activo', $data)) {
            $proveedor->activo = (bool) $data['activo'];
        }

        try {
            $proveedor->save();
        } catch (QueryException $exception) {
            if ($this->esDuplicado($exception)) {
                return $this->json($response, ['error' => 'La cédula jurídica ya está registrada.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $proveedor->toArray()], 200);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de proveedor válido.'], 400);
        }

        $proveedor = Proveedor::find($id);

        if ($proveedor === null) {
            return $this->json($response, ['error' => 'Proveedor no encontrado.'], 404);
        }

        $productosCount = $eloquent->table('productos')
            ->where('proveedor_id', $proveedor->id)
            ->count();

        if ($productosCount > 0) {
            return $this->json($response, ['error' => 'No se puede eliminar el proveedor porque tiene productos asociados. Desactívelo en su lugar.'], 409);
        }

        $proveedor->delete();

        return $this->json($response, ['message' => 'Proveedor eliminado exitosamente.'], 200);
    }
}
