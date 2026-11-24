<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use App\Services\ReporteService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

final class ReporteServiceTest extends TestCase
{
    private FakeDataService $data;
    private ReporteService $servicio;

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new ReporteService($this->data);
    }

    public function testPorDefectoUsaLosUltimos30Dias(): void
    {
        $filtros = $this->servicio->filtros([], new DateTimeImmutable('2026-11-12'));

        $this->assertSame(['desde' => '2026-10-14', 'hasta' => '2026-11-12'], $filtros);
    }

    public function testAceptaRangoAgrupacionYLimite(): void
    {
        $filtros = $this->servicio->filtros(['desde' => '2026-01-01', 'hasta' => '2026-06-30', 'agrupar' => 'mes', 'limite' => '5']);

        $this->assertSame(['desde' => '2026-01-01', 'hasta' => '2026-06-30', 'agrupar' => 'mes', 'limite' => 5], $filtros);
    }

    public function testRechazaRangoInvertidoOFechasInvalidas(): void
    {
        $this->assertArrayHasKey('rango', $this->errores(['desde' => '2026-11-12', 'hasta' => '2026-11-01']));
        $this->assertArrayHasKey('desde', $this->errores(['desde' => '2026-02-30', 'hasta' => '2026-03-01']));
        $this->assertArrayHasKey('hasta', $this->errores(['hasta' => '12/11/2026']));
    }

    public function testRechazaRangosDeMasDeUnAno(): void
    {
        $this->assertArrayHasKey('rango', $this->errores(['desde' => '2024-01-01', 'hasta' => '2026-01-01']));
    }

    public function testRechazaAgrupacionYLimiteInvalidos(): void
    {
        $errores = $this->errores(['agrupar' => 'semana', 'limite' => '500']);

        $this->assertArrayHasKey('agrupar', $errores);
        $this->assertArrayHasKey('limite', $errores);
    }

    public function testGenerarConsultaLaCapaDeDatosConLosFiltros(): void
    {
        $this->data->responder('GET', 'reportes/ventas-periodo', 200, ['data' => []]);

        $this->servicio->generar('ventas-periodo', ['desde' => '2026-11-01', 'hasta' => '2026-11-12', 'agrupar' => 'dia']);

        $this->assertSame(
            ['desde' => '2026-11-01', 'hasta' => '2026-11-12', 'agrupar' => 'dia'],
            $this->data->ultimaLlamada()['query']
        );
    }

    public function testReporteInexistenteEs404(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);
        $this->servicio->generar('nomina', []);
    }

    public function testExportaACsvCompatibleConExcel(): void
    {
        $csv = ReporteService::aCsv([
            ['producto' => 'Café "Premium"', 'unidades' => 3, 'ingresos' => 10200.5, 'activo' => true],
            ['producto' => 'Té; verde', 'unidades' => 1, 'ingresos' => 950.0, 'activo' => false],
        ]);

        $lineas = explode("\n", trim($csv));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Debe incluir BOM UTF-8 para Excel');
        $this->assertSame("\xEF\xBB\xBFproducto;unidades;ingresos;activo", $lineas[0]);
        $this->assertSame('"Café ""Premium""";3;10200.50;Sí', $lineas[1]);
        $this->assertSame('"Té; verde";1;950.00;No', $lineas[2]);
    }

    private function errores(array $params): array
    {
        try {
            $this->servicio->filtros($params, new DateTimeImmutable('2026-11-12'));
        } catch (ValidacionException $e) {
            return $e->getErrores();
        }

        $this->fail('Se esperaba ValidacionException');
    }
}
