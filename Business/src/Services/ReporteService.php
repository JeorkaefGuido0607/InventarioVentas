<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use DateTimeImmutable;

/**
 * Módulo de reportes: valida los filtros (rango de fechas, agrupación,
 * límite), consulta la capa de datos y permite exportar a CSV.
 */
final class ReporteService
{
    public const DIAS_MAXIMOS = 366;
    public const DIAS_POR_DEFECTO = 30;

    /** Reportes disponibles: nombre en la API => [ruta en Data, filas a exportar, usa rango de fechas] */
    public const REPORTES = [
        'ventas-periodo' => ['reportes/ventas-periodo', 'data', true],
        'productos-mas-vendidos' => ['reportes/productos-mas-vendidos', 'data', true],
        'ventas-categoria' => ['reportes/ventas-categoria', 'data', true],
        'ventas-vendedor' => ['reportes/ventas-vendedor', 'data', true],
        'ventas-metodo-pago' => ['reportes/ventas-metodo-pago', 'data', true],
        'inventario' => ['reportes/inventario', 'stock_bajo', false],
    ];

    public function __construct(private DataServiceInterface $dataService) {}

    public function dashboard(): ApiResult
    {
        return ApiResult::desde($this->dataService->get('reportes/dashboard'));
    }

    public function generar(string $reporte, array $params): ApiResult
    {
        if (!isset(self::REPORTES[$reporte])) {
            throw ApiException::con(404, 'El reporte solicitado no existe.');
        }

        [$ruta, , $usaRango] = self::REPORTES[$reporte];

        return ApiResult::desde($this->dataService->get($ruta, $usaRango ? $this->filtros($params) : []));
    }

    /**
     * Valida y normaliza los filtros. Por defecto: últimos 30 días.
     * @return array{desde:string, hasta:string, agrupar?:string, limite?:int}
     */
    public function filtros(array $params, ?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today');
        $errores = [];

        $hasta = $this->fecha($params['hasta'] ?? null) ?? ($this->vacio($params['hasta'] ?? null) ? $hoy : null);
        if ($hasta === null) {
            $errores['hasta'] = 'La fecha "hasta" debe tener el formato AAAA-MM-DD.';
        }

        $desde = $this->fecha($params['desde'] ?? null)
            ?? ($this->vacio($params['desde'] ?? null) && $hasta !== null ? $hasta->modify('-' . (self::DIAS_POR_DEFECTO - 1) . ' days') : null);
        if ($desde === null && !isset($errores['hasta'])) {
            $errores['desde'] = 'La fecha "desde" debe tener el formato AAAA-MM-DD.';
        }

        if ($desde !== null && $hasta !== null) {
            if ($desde > $hasta) {
                $errores['rango'] = 'La fecha "desde" no puede ser posterior a "hasta".';
            } elseif ($desde->diff($hasta)->days + 1 > self::DIAS_MAXIMOS) {
                $errores['rango'] = 'El rango máximo es de ' . self::DIAS_MAXIMOS . ' días.';
            }
        }

        $filtros = [
            'desde' => $desde?->format('Y-m-d'),
            'hasta' => $hasta?->format('Y-m-d'),
        ];

        if (!$this->vacio($params['agrupar'] ?? null)) {
            if (!in_array($params['agrupar'], ['dia', 'mes'], true)) {
                $errores['agrupar'] = 'El parámetro agrupar debe ser "dia" o "mes".';
            }
            $filtros['agrupar'] = $params['agrupar'];
        }

        if (!$this->vacio($params['limite'] ?? null)) {
            $limite = filter_var($params['limite'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($limite === false) {
                $errores['limite'] = 'El límite debe ser un entero entre 1 y 50.';
            }
            $filtros['limite'] = $limite;
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        return $filtros;
    }

    /**
     * Convierte filas asociativas a CSV (separador ';' y BOM UTF-8 para
     * que Excel en español abra bien las tildes y las columnas).
     */
    public static function aCsv(array $filas): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");

        if ($filas !== []) {
            $columnas = array_keys(reset($filas));
            fputcsv($salida, $columnas, ';', '"', '');

            foreach ($filas as $fila) {
                $valores = array_map(static function (mixed $v): string {
                    if (is_bool($v)) {
                        return $v ? 'Sí' : 'No';
                    }
                    if (is_float($v)) {
                        return number_format($v, 2, '.', '');
                    }
                    return (string) ($v ?? '');
                }, array_map(static fn (string $c) => $fila[$c] ?? null, $columnas));

                fputcsv($salida, $valores, ';', '"', '');
            }
        }

        rewind($salida);
        $csv = (string) stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    /** Filas que se exportan de un reporte ya generado */
    public static function filasExportables(string $reporte, ApiResult $resultado): array
    {
        $clave = self::REPORTES[$reporte][1] ?? 'data';
        $filas = $resultado->body[$clave] ?? [];

        return is_array($filas) ? $filas : [];
    }

    private function fecha(mixed $valor): ?DateTimeImmutable
    {
        if (!is_string($valor) || $valor === '') {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return ($fecha !== false && $fecha->format('Y-m-d') === $valor) ? $fecha : null;
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || $valor === '';
    }
}
