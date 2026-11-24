<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidacionException;
use App\Validators\BaseValidator;
use App\Validators\ProductoValidator;

final class ProductoService extends CrudService
{
    protected function recurso(): string
    {
        return 'productos';
    }

    protected function validador(): BaseValidator
    {
        return new ProductoValidator();
    }

    /** Búsqueda por código de barras (escáner de la app móvil) */
    public function buscarPorCodigo(string $codigo): ApiResult
    {
        if (preg_match('/^[A-Za-z0-9-]{3,50}$/', $codigo) !== 1) {
            throw new ValidacionException(['codigo' => 'El código debe tener entre 3 y 50 caracteres: letras, números y guiones.']);
        }

        return ApiResult::desde($this->dataService->get('productos/codigo/' . rawurlencode($codigo)));
    }

    /** Regla de negocio: no se vende por debajo del costo */
    protected function reglasCrear(array $data, array $usuario): array
    {
        return $this->reglaMargen($data['precio_compra'] ?? 0, $data['precio_venta'] ?? null);
    }

    protected function reglasActualizar(string $id, array $data, array $usuario): array
    {
        // El stock solo cambia con ventas o movimientos (queda en la bitácora)
        if (array_key_exists('stock', $data)) {
            return ['stock' => 'El stock no se modifica directamente; registre un movimiento de inventario.'];
        }

        $cambiaCompra = array_key_exists('precio_compra', $data);
        $cambiaVenta = array_key_exists('precio_venta', $data);

        if (!$cambiaCompra && !$cambiaVenta) {
            return [];
        }

        // Si solo cambia uno de los dos precios, se compara contra el actual
        if (!$cambiaCompra || !$cambiaVenta) {
            $actual = $this->obtener($id);
            if (!$actual->exitoso()) {
                return [];   // la capa de datos responderá 404
            }
            $producto = $actual->data();
            $data += [
                'precio_compra' => $producto['precio_compra'] ?? 0,
                'precio_venta' => $producto['precio_venta'] ?? 0,
            ];
        }

        return $this->reglaMargen($data['precio_compra'], $data['precio_venta']);
    }

    /** El stock inicial y el usuario que lo registra quedan en la bitácora */
    protected function prepararCrear(array $data, array $usuario): array
    {
        $data['usuario_id'] = $this->usuarioId($usuario);
        return $data;
    }

    private function reglaMargen(mixed $compra, mixed $venta): array
    {
        if (!is_numeric($compra) || !is_numeric($venta)) {
            return [];   // el validador ya reporta el formato
        }

        if ((float) $venta < (float) $compra) {
            return ['precio_venta' => 'El precio de venta no puede ser menor que el precio de compra.'];
        }

        return [];
    }
}
