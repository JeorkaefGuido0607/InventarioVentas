<?php
namespace App\Controllers;

use App\Middleware\RoleMiddleware;
use Slim\Routing\RouteCollectorProxy;

/*
 * Roles del sistema:
 *   Admin     → todo
 *   Vendedor  → ventas y clientes (consulta productos y catálogos)
 *   Bodeguero → productos, proveedores, categorías y movimientos de inventario
 *
 * $permitir('Admin', 'Vendedor') crea el middleware que deja pasar solo a esos roles.
 */
$permitir = fn (string ...$roles) => new RoleMiddleware($app->getResponseFactory(), $roles);

$app->group('/api', function(RouteCollectorProxy $api) use ($permitir) {
    $todos = $permitir('Admin', 'Vendedor', 'Bodeguero');

    // ---- Autenticación y documentación (login y docs son públicos) ----
    $api->post('/auth/login', AuthController::class . ':login');
    $api->get('/auth/perfil', AuthController::class . ':perfil')->add($todos);
    $api->get('/docs', DocsController::class . ':ui');
    $api->get('/docs/openapi.yaml', DocsController::class . ':spec');

    // ---- Usuarios y roles (solo Admin) ----
    $api->get('/roles', UsuarioController::class . ':roles')->add($permitir('Admin'));
    $api->group('/usuarios', function(RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', UsuarioController::class . ':filter');
        $endpoint->get('[/{id}]', UsuarioController::class . ':read');
        $endpoint->post('', UsuarioController::class . ':create');
        $endpoint->put('/{id}', UsuarioController::class . ':update');
        $endpoint->delete('/{id}', UsuarioController::class . ':delete');
    })->add($permitir('Admin'));

    // ---- Categorías ----
    $api->group('/categorias', function(RouteCollectorProxy $endpoint) use ($permitir, $todos) {
        $endpoint->get('/filter/{offset}/{limit}', CategoriaController::class . ':filter')->add($todos);
        $endpoint->get('[/{id}]', CategoriaController::class . ':read')->add($todos);
        $endpoint->post('', CategoriaController::class . ':create')->add($permitir('Admin', 'Bodeguero'));
        $endpoint->put('/{id}', CategoriaController::class . ':update')->add($permitir('Admin', 'Bodeguero'));
        $endpoint->delete('/{id}', CategoriaController::class . ':delete')->add($permitir('Admin'));
    });

    // ---- Proveedores ----
    $api->group('/proveedores', function(RouteCollectorProxy $endpoint) use ($permitir, $todos) {
        $endpoint->get('/filter/{offset}/{limit}', ProveedorController::class . ':filter')->add($todos);
        $endpoint->get('[/{id}]', ProveedorController::class . ':read')->add($todos);
        $endpoint->post('', ProveedorController::class . ':create')->add($permitir('Admin', 'Bodeguero'));
        $endpoint->put('/{id}', ProveedorController::class . ':update')->add($permitir('Admin', 'Bodeguero'));
        $endpoint->delete('/{id}', ProveedorController::class . ':delete')->add($permitir('Admin'));
    });

    // ---- Productos (módulo principal) ----
    $api->group('/productos', function(RouteCollectorProxy $endpoint) use ($permitir, $todos) {
        $endpoint->get('/filter/{offset}/{limit}', ProductoController::class . ':filter')->add($todos);
        $endpoint->get('/codigo/{codigo}', ProductoController::class . ':readByCodigo')->add($todos);
        $endpoint->get('[/{id}]', ProductoController::class . ':read')->add($todos);
        $endpoint->post('', ProductoController::class . ':create')->add($permitir('Admin', 'Bodeguero'));
        $endpoint->put('/{id}', ProductoController::class . ':update')->add($permitir('Admin', 'Bodeguero'));
        $endpoint->delete('/{id}', ProductoController::class . ':delete')->add($permitir('Admin'));
    });

    // ---- Clientes ----
    $api->group('/clientes', function(RouteCollectorProxy $endpoint) use ($permitir) {
        $endpoint->get('/filter/{offset}/{limit}', ClienteController::class . ':filter');
        $endpoint->get('[/{id}]', ClienteController::class . ':read');
        $endpoint->post('', ClienteController::class . ':create');
        $endpoint->put('/{id}', ClienteController::class . ':update');
        $endpoint->delete('/{id}', ClienteController::class . ':delete')->add($permitir('Admin'));
    })->add($permitir('Admin', 'Vendedor'));

    // ---- Ventas (módulo principal) ----
    $api->group('/ventas', function(RouteCollectorProxy $endpoint) use ($permitir) {
        $endpoint->get('/filter/{offset}/{limit}', VentaController::class . ':filter');
        $endpoint->get('[/{id}]', VentaController::class . ':read');
        $endpoint->post('/cotizar', VentaController::class . ':cotizar');
        $endpoint->post('', VentaController::class . ':create');
        $endpoint->put('/{id}', VentaController::class . ':update');
        $endpoint->delete('/{id}', VentaController::class . ':delete')->add($permitir('Admin'));
    })->add($permitir('Admin', 'Vendedor'));

    // ---- Movimientos de inventario (bitácora: sin PUT ni DELETE) ----
    $api->group('/movimientos', function(RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', MovimientoController::class . ':filter');
        $endpoint->get('[/{id}]', MovimientoController::class . ':read');
        $endpoint->post('', MovimientoController::class . ':create');
    })->add($permitir('Admin', 'Bodeguero'));

    // ---- Reportes ----
    $api->group('/reportes', function(RouteCollectorProxy $endpoint) use ($permitir, $todos) {
        $endpoint->get('/dashboard', ReporteController::class . ':dashboard')->add($todos);
        $endpoint->get('/inventario', ReporteController::class . ':generar')
            ->setArgument('reporte', 'inventario')
            ->add($permitir('Admin', 'Bodeguero'));
        $endpoint->get('/{reporte}', ReporteController::class . ':generar')->add($permitir('Admin'));
    });
});
