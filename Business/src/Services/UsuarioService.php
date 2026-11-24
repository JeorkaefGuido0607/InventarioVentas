<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Validators\BaseValidator;
use App\Validators\UsuarioValidator;

final class UsuarioService extends CrudService
{
    protected function recurso(): string
    {
        return 'usuarios';
    }

    protected function validador(): BaseValidator
    {
        return new UsuarioValidator();
    }

    public function roles(): ApiResult
    {
        return ApiResult::desde($this->dataService->get('roles'));
    }

    /** Un administrador no puede quitarse su propio rol ni desactivarse */
    protected function reglasActualizar(string $id, array $data, array $usuario): array
    {
        if ((int) $id !== $this->usuarioId($usuario)) {
            return [];
        }

        $errores = [];
        if (array_key_exists('rol_id', $data) && $data['rol_id'] !== ($usuario['rol_id'] ?? null)) {
            $errores['rol_id'] = 'No puede cambiar su propio rol.';
        }
        if (array_key_exists('activo', $data) && $data['activo'] === false) {
            $errores['activo'] = 'No puede desactivar su propio usuario.';
        }

        return $errores;
    }

    protected function antesDeEliminar(string $id, array $usuario): void
    {
        if ((int) $id === $this->usuarioId($usuario)) {
            throw ApiException::con(409, 'No puede eliminar su propio usuario.');
        }
    }
}
