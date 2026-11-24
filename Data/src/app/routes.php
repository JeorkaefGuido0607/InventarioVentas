<?php
use App\Controllers\AuthController;
use App\Controllers\CategoriaController;
use App\Controllers\ClienteController;
use App\Controllers\MovimientoController;
use App\Controllers\ProductoController;
use App\Controllers\ProveedorController;
use App\Controllers\ReporteController;
use App\Controllers\UsuarioController;
use App\Controllers\VentaController;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api', function (RouteCollectorProxy $api) {
    $api->post('/auth/credentials', AuthController::class . ':credentials');

    $api->get('/roles', UsuarioController::class . ':roles');

    $api->group('/usuarios', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', UsuarioController::class . ':filter');
        $endpoint->get('[/{id}]', UsuarioController::class . ':read');
        $endpoint->post('', UsuarioController::class . ':create');
        $endpoint->put('/{id}', UsuarioController::class . ':update');
        $endpoint->delete('/{id}', UsuarioController::class . ':delete');
    });

    $api->group('/categorias', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', CategoriaController::class . ':filter');
        $endpoint->get('[/{id}]', CategoriaController::class . ':read');
        $endpoint->post('', CategoriaController::class . ':create');
        $endpoint->put('/{id}', CategoriaController::class . ':update');
        $endpoint->delete('/{id}', CategoriaController::class . ':delete');
    });

    $api->group('/proveedores', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', ProveedorController::class . ':filter');
        $endpoint->get('[/{id}]', ProveedorController::class . ':read');
        $endpoint->post('', ProveedorController::class . ':create');
        $endpoint->put('/{id}', ProveedorController::class . ':update');
        $endpoint->delete('/{id}', ProveedorController::class . ':delete');
    });

    $api->group('/productos', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', ProductoController::class . ':filter');
        $endpoint->get('/codigo/{codigo}', ProductoController::class . ':readByCodigo');
        $endpoint->get('[/{id}]', ProductoController::class . ':read');
        $endpoint->post('', ProductoController::class . ':create');
        $endpoint->put('/{id}', ProductoController::class . ':update');
        $endpoint->delete('/{id}', ProductoController::class . ':delete');
    });

    $api->group('/clientes', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', ClienteController::class . ':filter');
        $endpoint->get('[/{id}]', ClienteController::class . ':read');
        $endpoint->post('', ClienteController::class . ':create');
        $endpoint->put('/{id}', ClienteController::class . ':update');
        $endpoint->delete('/{id}', ClienteController::class . ':delete');
    });

    $api->group('/ventas', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', VentaController::class . ':filter');
        $endpoint->get('[/{id}]', VentaController::class . ':read');
        $endpoint->post('', VentaController::class . ':create');
        $endpoint->put('/{id}', VentaController::class . ':update');
        $endpoint->delete('/{id}', VentaController::class . ':delete');
    });

    $api->group('/movimientos', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', MovimientoController::class . ':filter');
        $endpoint->get('[/{id}]', MovimientoController::class . ':read');
        $endpoint->post('', MovimientoController::class . ':create');
    });

    $api->group('/reportes', function (RouteCollectorProxy $endpoint) {
        $endpoint->get('/dashboard', ReporteController::class . ':dashboard');
        $endpoint->get('/ventas-periodo', ReporteController::class . ':ventasPeriodo');
        $endpoint->get('/productos-mas-vendidos', ReporteController::class . ':productosMasVendidos');
        $endpoint->get('/ventas-categoria', ReporteController::class . ':ventasCategoria');
        $endpoint->get('/ventas-vendedor', ReporteController::class . ':ventasVendedor');
        $endpoint->get('/ventas-metodo-pago', ReporteController::class . ':ventasMetodoPago');
        $endpoint->get('/inventario', ReporteController::class . ':inventario');
    });
});
