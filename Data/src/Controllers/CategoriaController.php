<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Categoria;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CategoriaController extends BaseController {
    private const COLUMNAS = [
        'categorias.id',
        'categorias.nombre',
        'categorias.descripcion',
        'categorias.activo'
    ];

    public function read(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        if (isset($args['id'])) {
            $id = $this->idValido($args);

            if ($id === null) {
                return $this->json($response, ['error' => 'El identificador debe ser un entero positivo.'], 422);
            }

            $categoria = Categoria::query()
                ->select(self::COLUMNAS)
                ->withCount('productos')
                ->where('categorias.id', $id)
                ->first();

            if ($categoria === null) {
                return $this->json($response, ['error' => 'Categoría no encontrada.'], 404);
            }

            return $this->json($response, ['data' => $categoria->toArray()], 200);
        }

        $categorias = Categoria::query()
            ->select(self::COLUMNAS)
            ->withCount('productos')
            ->orderBy('nombre')
            ->get()
            ->toArray();

        return $this->json($response, ['data' => $categorias], 200);
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
        $active = $params['activo'] ?? null;

        $query = Categoria::query()
            ->select(self::COLUMNAS)
            ->withCount('productos');

        if ($name !== '') {
            $query->where('categorias.nombre', 'like', "%{$name}%");
        }
        if ($active !== null && $active !== '') {
            $query->where('categorias.activo', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('categorias.nombre');

        return $this->paginado($response, $query, $offset, $limit, 'categorias.id');
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $this->body($request);

        $errors = [];
        $name = trim((string) ($data['nombre'] ?? ''));
        $description = $this->opcional($data, 'descripcion');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['nombre'] = 'Es obligatorio y debe tener máximo 100 caracteres.';
        }
        if ($description !== null && mb_strlen($description) > 255) {
            $errors['descripcion'] = 'Debe tener máximo 255 caracteres.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        try {
            $this->eloquent();
            $categoria = Categoria::create([
                'nombre' => $name,
                'descripcion' => $description,
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            ]);
        } catch (QueryException $exception) {
            if ($this->esDuplicado($exception)) {
                return $this->json($response, ['error' => 'Ya existe una categoría con ese nombre.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $categoria->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de categoría válido.'], 400);
        }

        $categoria = Categoria::find($id);

        if ($categoria === null) {
            return $this->json($response, ['error' => 'Categoría no encontrada.'], 404);
        }

        $data = $this->body($request);

        if (isset($data['nombre'])) {
            $name = trim((string) $data['nombre']);

            if ($name === '' || mb_strlen($name) > 100) {
                return $this->json($response, ['error' => 'Nombre inválido.'], 422);
            }

            $categoria->nombre = $name;
        }

        if (array_key_exists('descripcion', $data)) {
            $description = $this->opcional($data, 'descripcion');

            if ($description !== null && mb_strlen($description) > 255) {
                return $this->json($response, ['error' => 'Descripción inválida.'], 422);
            }

            $categoria->descripcion = $description;
        }

        if (array_key_exists('activo', $data)) {
            $categoria->activo = (bool) $data['activo'];
        }

        try {
            $categoria->save();
        } catch (QueryException $exception) {
            if ($this->esDuplicado($exception)) {
                return $this->json($response, ['error' => 'Ya existe una categoría con ese nombre.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $categoria->toArray()], 200);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $eloquent = $this->eloquent();

        $id = $this->idValido($args);
        if ($id === null) {
            return $this->json($response, ['error' => 'Debe indicar un identificador de categoría válido.'], 400);
        }

        $categoria = Categoria::find($id);

        if ($categoria === null) {
            return $this->json($response, ['error' => 'Categoría no encontrada.'], 404);
        }

        // Verificar si la categoría tiene productos asociados
        $productosCount = $eloquent->table('productos')
            ->where('categoria_id', $categoria->id)
            ->count();

        if ($productosCount > 0) {
            return $this->json($response, ['error' => 'No se puede eliminar la categoría porque tiene productos asociados.'], 409);
        }

        $categoria->delete();

        return $this->json($response, ['message' => 'Categoría eliminada exitosamente.'], 200);
    }
}
