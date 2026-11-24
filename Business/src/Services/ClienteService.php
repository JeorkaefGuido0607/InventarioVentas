<?php
declare(strict_types=1);

namespace App\Services;

use App\Validators\BaseValidator;
use App\Validators\ClienteValidator;

final class ClienteService extends CrudService
{
    protected function recurso(): string
    {
        return 'clientes';
    }

    protected function validador(): BaseValidator
    {
        return new ClienteValidator();
    }

    /** La latitud y la longitud (GPS de la app móvil) van siempre juntas */
    protected function reglasCrear(array $data, array $usuario): array
    {
        return $this->reglaCoordenadas($data);
    }

    protected function reglasActualizar(string $id, array $data, array $usuario): array
    {
        return $this->reglaCoordenadas($data);
    }

    private function reglaCoordenadas(array $data): array
    {
        $tieneLatitud = ($data['latitud'] ?? null) !== null && $data['latitud'] !== '';
        $tieneLongitud = ($data['longitud'] ?? null) !== null && $data['longitud'] !== '';

        if ($tieneLatitud !== $tieneLongitud) {
            return ['ubicacion' => 'La latitud y la longitud deben enviarse juntas.'];
        }

        return [];
    }
}
