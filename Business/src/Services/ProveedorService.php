<?php
declare(strict_types=1);

namespace App\Services;

use App\Validators\BaseValidator;
use App\Validators\ProveedorValidator;

final class ProveedorService extends CrudService
{
    protected function recurso(): string
    {
        return 'proveedores';
    }

    protected function validador(): BaseValidator
    {
        return new ProveedorValidator();
    }
}
