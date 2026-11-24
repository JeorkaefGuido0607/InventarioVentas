<?php
use App\Services\DataService;
use App\Services\DataServiceInterface;
use App\Services\AuthService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

$container->set('config', function() {
    return (object) [
        'api_data' => rtrim($_ENV['API_DATA'], '/'),
        'key' => $_ENV['KEY'],
        'timeout' => (float) ($_ENV['API_TIMEOUT'] ?? 5),
        'jwt_ttl' => (int) ($_ENV['JWT_TTL'] ?? 3600)
    ];
});
$container->set(DataService::class, function(ContainerInterface $container) {
    return new DataService($container->get('config'));
});

// Los servicios de negocio piden la interfaz; PHP-DI les inyecta DataService
// (autowiring). En las pruebas unitarias se inyecta un doble en su lugar.
$container->set(DataServiceInterface::class, function(ContainerInterface $container) {
    return $container->get(DataService::class);
});
$container->set(AuthService::class, function(ContainerInterface $container) {
    return new AuthService($container->get(DataServiceInterface::class), $container->get('config'));
});
$container->set(ResponseFactoryInterface::class, function() use ($app) {
    return $app->getResponseFactory();
});
