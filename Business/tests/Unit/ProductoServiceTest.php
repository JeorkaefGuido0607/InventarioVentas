<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidacionException;
use App\Services\ProductoService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

final class ProductoServiceTest extends TestCase
{
    private FakeDataService $data;
    private ProductoService $servicio;
    private array $bodeguero = ['sub' => '4', 'rol' => 'Bodeguero'];

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new ProductoService($this->data);
    }

    private function productoValido(array $cambios = []): array
    {
        return $cambios + [
            'codigo' => '7441001000011',
            'nombre' => 'Arroz 99% grano entero 2 kg',
            'categoria_id' => 1,
            'proveedor_id' => 1,
            'precio_compra' => 1650,
            'precio_venta' => 2150,
            'stock' => 20,
            'stock_minimo' => 10,
        ];
    }

    public function testCreaElProductoYRegistraQuienCargaElStockInicial(): void
    {
        $this->data->responder('POST', 'productos', 201, ['data' => ['id' => 30]]);

        $resultado = $this->servicio->crear($this->productoValido(), $this->bodeguero);

        $this->assertSame(201, $resultado->status);
        $this->assertSame(4, $this->data->ultimaLlamada()['body']['usuario_id']);
    }

    public function testNoPermitePrecioDeVentaMenorAlCosto(): void
    {
        try {
            $this->servicio->crear($this->productoValido(['precio_venta' => 1000]), $this->bodeguero);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('precio_venta', $e->getErrores());
            $this->assertSame([], $this->data->escrituras());
        }
    }

    public function testAlActualizarUnSoloPrecioLoComparaConElPrecioActual(): void
    {
        $this->data->responder('GET', 'productos/5', 200, ['data' => ['id' => 5, 'precio_compra' => 1500, 'precio_venta' => 1950]]);

        $this->expectException(ValidacionException::class);
        $this->servicio->actualizar('5', ['precio_venta' => 1200], $this->bodeguero);
    }

    public function testActualizaPrecioValido(): void
    {
        $this->data
            ->responder('GET', 'productos/5', 200, ['data' => ['id' => 5, 'precio_compra' => 1500, 'precio_venta' => 1950]])
            ->responder('PUT', 'productos/5', 200, ['data' => ['id' => 5, 'precio_venta' => 2000]]);

        $resultado = $this->servicio->actualizar('5', ['precio_venta' => 2000], $this->bodeguero);

        $this->assertSame(200, $resultado->status);
        $this->assertSame(['precio_venta' => 2000], $this->data->ultimaLlamada()['body']);
    }

    public function testElStockNoSeEditaDirectamente(): void
    {
        try {
            $this->servicio->actualizar('5', ['stock' => 100], $this->bodeguero);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('stock', $e->getErrores());
        }
    }

    public function testBuscaPorCodigoDeBarras(): void
    {
        $this->data->responder('GET', 'productos/codigo/7441001000011', 200, ['data' => ['id' => 1]]);

        $this->assertSame(200, $this->servicio->buscarPorCodigo('7441001000011')->status);
    }

    public function testRechazaCodigoConCaracteresInvalidos(): void
    {
        $this->expectException(ValidacionException::class);
        $this->servicio->buscarPorCodigo('../usuarios');
    }
}
