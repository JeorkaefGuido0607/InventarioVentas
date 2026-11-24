<?php
declare(strict_types=1);

namespace App\Services;

use App\Validators\BaseValidator;
use App\Validators\CategoriaValidator;

final class CategoriaService extends CrudService
{
    protected function recurso(): string
    {
        return 'categorias';
    }

    protected function validador(): BaseValidator
    {
        return new CategoriaValidator();
    }
}
