<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use App\Services\MovimientoService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

final class MovimientoServiceTest extends TestCase
{
    private FakeDataService $data;
    private MovimientoService $servicio;
    private array $bodeguero = ['sub' => '4', 'rol' => 'Bodeguero'];

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new MovimientoService($this->data);
    }

    public function testRegistraEntradaConElUsuarioDelToken(): void
    {
        $this->data->responder('POST', 'movimientos', 201, ['message' => 'Movimiento registrado exitosamente.']);

        $this->servicio->crear(['producto_id' => 3, 'tipo' => 'Entrada', 'cantidad' => 24, 'motivo' => '  Compra a proveedor  '], $this->bodeguero);

        $this->assertSame(
            ['producto_id' => 3, 'tipo' => 'Entrada', 'cantidad' => 24, 'motivo' => 'Compra a proveedor', 'usuario_id' => 4],
            $this->data->ultimaLlamada()['body']
        );
    }

    public function testEntradaOSalidaConCantidadCeroEsInvalida(): void
    {
        $this->expectException(ValidacionException::class);
        $this->servicio->crear(['producto_id' => 3, 'tipo' => 'Salida', 'cantidad' => 0, 'motivo' => 'Merma'], $this->bodeguero);
    }

    public function testAjusteACeroEsValido(): void
    {
        $this->data->responder('POST', 'movimientos', 201, ['message' => 'ok']);

        $resultado = $this->servicio->crear(['producto_id' => 3, 'tipo' => 'Ajuste', 'cantidad' => 0, 'motivo' => 'Conteo físico'], $this->bodeguero);

        $this->assertSame(201, $resultado->status);
    }

    public function testRechazaTiposNoPermitidos(): void
    {
        try {
            $this->servicio->crear(['producto_id' => 3, 'tipo' => 'Venta', 'cantidad' => 1, 'motivo' => 'Manual'], $this->bodeguero);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('tipo', $e->getErrores());
        }
    }

    public function testLaBitacoraNoSePuedeModificar(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(405);
        $this->servicio->eliminar('1', $this->bodeguero);
    }
}
