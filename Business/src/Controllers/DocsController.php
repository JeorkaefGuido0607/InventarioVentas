<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Documentación de la API (Swagger / OpenAPI).
 *   GET /api/docs               → Swagger UI
 *   GET /api/docs/openapi.yaml  → especificación OpenAPI 3
 */
final class DocsController extends BaseController
{
    private const ESPECIFICACION = __DIR__ . '/../../docs/openapi.yaml';

    public function ui(Request $request, Response $response): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario y Ventas - API</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: '/api/docs/openapi.yaml',
            dom_id: '#swagger-ui',
            persistAuthorization: true
        });
    </script>
</body>
</html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function spec(Request $request, Response $response): Response
    {
        if (!is_file(self::ESPECIFICACION)) {
            return $this->json($response, ['error' => 'No se encontró la especificación OpenAPI.'], 404);
        }

        $response->getBody()->write((string) file_get_contents(self::ESPECIFICACION));
        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }
}
