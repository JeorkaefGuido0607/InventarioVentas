<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\MovimientoService;

class MovimientoController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(MovimientoService::class);
    }
}
