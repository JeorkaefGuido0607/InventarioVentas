<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReporteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReporteController extends BaseController
{
    /** GET /api/reportes/dashboard: indicadores clave (KPI) */
    public function dashboard(Request $request, Response $response, array $args): Response
    {
        return $this->responder($response, $this->container->get(ReporteService::class)->dashboard());
    }

    /**
     * GET /api/reportes/{reporte}?desde=&hasta=[&agrupar=][&limite=][&formato=csv]
     * Con formato=csv el reporte se descarga como archivo para Excel.
     */
    public function generar(Request $request, Response $response, array $args): Response
    {
        $reporte = (string) $args['reporte'];
        $params = $request->getQueryParams();
        $resultado = $this->container->get(ReporteService::class)->generar($reporte, $params);

        if (($params['formato'] ?? 'json') !== 'csv' || !$resultado->exitoso()) {
            return $this->responder($response, $resultado);
        }

        $csv = ReporteService::aCsv(ReporteService::filasExportables($reporte, $resultado));
        $archivo = 'reporte-' . $reporte . '-' . date('Ymd-His') . '.csv';

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $archivo . '"')
            ->withStatus(200);
    }
}
