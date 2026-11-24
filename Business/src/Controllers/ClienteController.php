<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CrudService;
use App\Services\ClienteService;

class ClienteController extends CrudController {

    protected function servicio(): CrudService {
        return $this->container->get(ClienteService::class);
    }
}
