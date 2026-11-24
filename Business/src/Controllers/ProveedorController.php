<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\ProveedorService;

class ProveedorController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(ProveedorService::class);
    }
}
