<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ServicioNoDisponibleException;
use App\Exceptions\ValidacionException;
use App\Services\ApiResult;
use App\Services\CategoriaService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

/** Comportamiento común de todos los módulos CRUD (se prueba con Categorías) */
final class CrudServiceTest extends TestCase
{
    private FakeDataService $data;
    private CategoriaService $servicio;

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new CategoriaService($this->data);
    }

    public function testFiltrarEnviaPaginacionYFiltrosALaCapaDeDatos(): void
    {
        $this->data->responder('GET', 'categorias/filter/0/10', 200, ['data' => [], 'pagination' => ['offset' => 0, 'limit' => 10, 'total' => 0]]);

        $resultado = $this->servicio->filtrar('0', '10', ['nombre' => 'beb']);

        $this->assertSame(200, $resultado->status);
        $this->assertSame(['nombre' => 'beb'], $this->data->ultimaLlamada()['query']);
    }

    public static function paginacionInvalida(): array
    {
        return [
            'offset negativo' => ['-1', '10', 'offset'],
            'offset texto' => ['abc', '10', 'offset'],
            'límite cero' => ['0', '0', 'limit'],
            'límite mayor a 100' => ['0', '101', 'limit'],
        ];
    }

    #[DataProvider('paginacionInvalida')]
    public function testValidaLaPaginacion(string $offset, string $limit, string $campo): void
    {
        try {
            $this->servicio->filtrar($offset, $limit);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey($campo, $e->getErrores());
            $this->assertSame([], $this->data->llamadas);
        }
    }

    public function testValidaQueElIdSeaEnteroPositivo(): void
    {
        $this->expectException(ValidacionException::class);
        $this->servicio->obtener('0');
    }

    public function testActualizarSinCamposEsUnError(): void
    {
        try {
            $this->servicio->actualizar('1', []);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('body', $e->getErrores());
        }
    }

    public function testActualizacionParcialSoloValidaLosCamposEnviados(): void
    {
        $this->data->responder('PUT', 'categorias/1', 200, ['data' => ['id' => 1]]);

        $resultado = $this->servicio->actualizar('1', ['descripcion' => 'Nueva descripción']);

        $this->assertSame(200, $resultado->status);
    }

    public function testCrearConDatosInvalidosNoLlamaALaCapaDeDatos(): void
    {
        try {
            $this->servicio->crear(['nombre' => 'X']);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('nombre', $e->getErrores());
            $this->assertSame(422, $e->getStatus());
            $this->assertSame([], $this->data->llamadas);
        }
    }

    public function testPropagaLosErroresDeLaCapaDeDatos(): void
    {
        $this->data->responder('POST', 'categorias', 409, ['error' => 'Ya existe una categoría con ese nombre.']);

        $resultado = $this->servicio->crear(['nombre' => 'Bebidas']);

        $this->assertSame(409, $resultado->status);
        $this->assertFalse($resultado->exitoso());
    }

    public function testNormalizaErrorsAErrores(): void
    {
        $resultado = ApiResult::desde(new Response(422, [], json_encode(['errors' => ['nombre' => 'x']])));

        $this->assertSame(['errores' => ['nombre' => 'x']], $resultado->body);
    }

    public function testRespuestaNoJsonDeLaCapaDeDatosEs502(): void
    {
        $resultado = ApiResult::desde(new Response(200, [], '<html>Fatal error</html>'));

        $this->assertSame(502, $resultado->status);
    }

    public function testServicioNoDisponibleResponde502(): void
    {
        $this->assertSame(502, (new ServicioNoDisponibleException())->getStatus());
    }
}
