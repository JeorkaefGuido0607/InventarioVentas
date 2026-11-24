<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidacionException;
use App\Validators\BaseValidator;

/**
 * Lógica común de los módulos CRUD de la capa de negocio:
 *  - valida identificadores, paginación y el cuerpo de la petición,
 *  - aplica las reglas de negocio propias de cada módulo (hooks),
 *  - delega la persistencia a la capa de datos por medio de DataService.
 *
 * $usuario es el payload del JWT (sub, username, rol...) del usuario
 * que hace la petición; se usa para auditoría y reglas por rol.
 */
abstract class CrudService
{
    public const LIMITE_MAXIMO = 100;

    public function __construct(protected DataServiceInterface $dataService) {}

    /** Nombre del recurso en la capa de datos, ej. 'productos' */
    abstract protected function recurso(): string;

    abstract protected function validador(): BaseValidator;

    public function listar(): ApiResult
    {
        return ApiResult::desde($this->dataService->get($this->recurso()));
    }

    public function obtener(string $id): ApiResult
    {
        $this->validarId($id);

        return ApiResult::desde($this->dataService->get($this->recurso() . '/' . rawurlencode($id)));
    }

    public function filtrar(string $offset, string $limit, array $filtros = []): ApiResult
    {
        $this->validarPaginacion($offset, $limit);

        $path = $this->recurso() . '/filter/' . rawurlencode($offset) . '/' . rawurlencode($limit);

        return ApiResult::desde($this->dataService->get($path, $filtros));
    }

    public function crear(array $data, array $usuario = []): ApiResult
    {
        $errores = $this->validador()->validate($data);
        $errores += $this->reglasCrear($data, $usuario);

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        $payload = $this->prepararCrear($data, $usuario);

        return ApiResult::desde($this->dataService->post($this->recurso(), $this->aJson($payload)));
    }

    public function actualizar(string $id, array $data, array $usuario = []): ApiResult
    {
        $this->validarId($id);

        if ($data === []) {
            throw new ValidacionException(['body' => 'Debe enviar al menos un campo para actualizar.']);
        }

        $errores = $this->validador()->validate($data, true);
        $errores += $this->reglasActualizar($id, $data, $usuario);

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        return ApiResult::desde($this->dataService->put($this->recurso() . '/' . rawurlencode($id), $this->aJson($data)));
    }

    public function eliminar(string $id, array $usuario = []): ApiResult
    {
        $this->validarId($id);
        $this->antesDeEliminar($id, $usuario);

        return ApiResult::desde($this->dataService->delete($this->recurso() . '/' . rawurlencode($id)));
    }

    // ------------------------------------------------------------------
    // Hooks: cada servicio los sobrescribe con sus reglas de negocio
    // ------------------------------------------------------------------

    /** @return array<string,string> errores adicionales */
    protected function reglasCrear(array $data, array $usuario): array
    {
        return [];
    }

    /** @return array<string,string> errores adicionales */
    protected function reglasActualizar(string $id, array $data, array $usuario): array
    {
        return [];
    }

    /** Lanza ApiException si la eliminación no está permitida */
    protected function antesDeEliminar(string $id, array $usuario): void {}

    /** Permite agregar datos al cuerpo antes de enviarlo (ej. usuario_id) */
    protected function prepararCrear(array $data, array $usuario): array
    {
        return $data;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    protected function validarId(string $id): void
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new ValidacionException(['id' => 'El identificador debe ser un entero positivo.']);
        }
    }

    protected function validarPaginacion(string $offset, string $limit): void
    {
        $errores = [];

        if (filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $errores['offset'] = 'El offset debe ser un entero mayor o igual a 0.';
        }
        if (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::LIMITE_MAXIMO]]) === false) {
            $errores['limit'] = 'El límite debe ser un entero entre 1 y ' . self::LIMITE_MAXIMO . '.';
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }
    }

    protected function usuarioId(array $usuario): ?int
    {
        $id = filter_var($usuario['sub'] ?? null, FILTER_VALIDATE_INT);
        return $id === false || $id === null ? null : $id;
    }

    protected function aJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
