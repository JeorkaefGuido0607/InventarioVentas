<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Validators\BaseValidator;
use App\Validators\MovimientoValidator;

/**
 * Movimientos manuales de inventario (Entrada, Salida, Ajuste).
 * Los movimientos son una bitácora: no se modifican ni se eliminan.
 */
final class MovimientoService extends CrudService
{
    protected function recurso(): string
    {
        return 'movimientos';
    }

    protected function validador(): BaseValidator
    {
        return new MovimientoValidator();
    }

    /** Entrada y Salida requieren al menos 1 unidad; Ajuste admite 0 (producto agotado) */
    protected function reglasCrear(array $data, array $usuario): array
    {
        $tipo = $data['tipo'] ?? null;
        $cantidad = $data['cantidad'] ?? null;

        if (in_array($tipo, ['Entrada', 'Salida'], true) && is_int($cantidad) && $cantidad < 1) {
            return ['cantidad' => "Para una {$tipo} la cantidad debe ser mayor a 0."];
        }

        return [];
    }

    protected function prepararCrear(array $data, array $usuario): array
    {
        return [
            'producto_id' => $data['producto_id'],
            'tipo' => $data['tipo'],
            'cantidad' => $data['cantidad'],
            'motivo' => trim($data['motivo']),
            'usuario_id' => $this->usuarioId($usuario),
        ];
    }

    public function actualizar(string $id, array $data, array $usuario = []): ApiResult
    {
        throw ApiException::con(405, 'Los movimientos de inventario no se pueden modificar.');
    }

    public function eliminar(string $id, array $usuario = []): ApiResult
    {
        throw ApiException::con(405, 'Los movimientos de inventario no se pueden eliminar.');
    }
}
