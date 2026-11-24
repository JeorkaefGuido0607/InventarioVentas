<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\CategoriaService;

class CategoriaController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(CategoriaService::class);
    }
}
