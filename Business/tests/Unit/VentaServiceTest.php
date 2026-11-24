<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidacionException;
use App\Services\VentaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

final class VentaServiceTest extends TestCase
{
    private FakeDataService $data;
    private VentaService $servicio;
    private array $vendedor = ['sub' => '2', 'username' => 'vendedor01', 'rol' => 'Vendedor'];

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new VentaService($this->data);

        $this->data
            ->responder('GET', 'productos/1', 200, ['data' => $this->producto(1, 'Arroz 2 kg', 2150, 60)])
            ->responder('GET', 'productos/2', 200, ['data' => $this->producto(2, 'Café 500 g', 3400, 3)])
            ->responder('GET', 'productos/3', 200, ['data' => $this->producto(3, 'Producto viejo', 500, 10, false)])
            ->responder('GET', 'productos/99', 404, ['error' => 'Producto no encontrado.'])
            ->responder('GET', 'clientes/1', 200, ['data' => ['id' => 1, 'nombre_completo' => 'Ana Vargas']])
            ->responder('GET', 'clientes/50', 404, ['error' => 'Cliente no encontrado.'])
            ->responder('POST', 'ventas', 201, ['data' => ['id' => 141]]);
    }

    private function producto(int $id, string $nombre, float $precio, int $stock, bool $activo = true): array
    {
        return ['id' => $id, 'codigo' => "COD-{$id}", 'nombre' => $nombre, 'precio_venta' => $precio, 'stock' => $stock, 'activo' => $activo];
    }

    public function testCalculaSubtotalIvaYTotalSinDescuento(): void
    {
        $totales = VentaService::calcularTotales([
            ['cantidad' => 2, 'precio_unitario' => 2150],
            ['cantidad' => 1, 'precio_unitario' => 3400],
        ]);

        $this->assertSame(['subtotal' => 7700, 'descuento' => 0, 'impuesto' => 1001, 'total' => 8701], $totales);
    }

    public function testAplicaDescuentoAntesDelIva(): void
    {
        $totales = VentaService::calcularTotales([['cantidad' => 1, 'precio_unitario' => 10000]], 10);

        $this->assertEquals(10000, $totales['subtotal']);
        $this->assertEquals(1000, $totales['descuento']);
        $this->assertEquals(1170, $totales['impuesto']);   // 13 % de 9000
        $this->assertEquals(10170, $totales['total']);
    }

    public function testRedondeaACentimosSinErroresDeComaFlotante(): void
    {
        $totales = VentaService::calcularTotales([['cantidad' => 3, 'precio_unitario' => 0.1]]);

        $this->assertSame(0.3, $totales['subtotal']);
        $this->assertSame(0.04, $totales['impuesto']);
        $this->assertSame(0.34, $totales['total']);
    }

    public function testRegistraLaVentaConPreciosDelCatalogoYElUsuarioDelToken(): void
    {
        $resultado = $this->servicio->crear([
            'cliente_id' => 1,
            'metodo_pago' => 'SINPE Móvil',
            'detalle' => [
                ['producto_id' => 1, 'cantidad' => 2, 'precio_unitario' => 1],   // el precio enviado se ignora
                ['producto_id' => 2, 'cantidad' => 1],
            ],
        ], $this->vendedor);

        $this->assertSame(201, $resultado->status);

        $enviado = $this->data->ultimaLlamada();
        $this->assertSame('POST', $enviado['metodo']);
        $this->assertSame(2, $enviado['body']['usuario_id']);
        $this->assertSame('SINPE Móvil', $enviado['body']['metodo_pago']);
        $this->assertEquals(2150, $enviado['body']['detalle'][0]['precio_unitario']);
        $this->assertEquals(8701, $enviado['body']['total']);
    }

    public function testMetodoDePagoPorDefectoEsEfectivo(): void
    {
        $this->servicio->crear(['detalle' => [['producto_id' => 1, 'cantidad' => 1]]], $this->vendedor);

        $this->assertSame('Efectivo', $this->data->ultimaLlamada()['body']['metodo_pago']);
        $this->assertNull($this->data->ultimaLlamada()['body']['cliente_id']);
    }

    public function testRechazaStockInsuficiente(): void
    {
        $errores = $this->erroresAlCrear(['detalle' => [['producto_id' => 2, 'cantidad' => 5]]]);

        $this->assertStringContainsString('Stock insuficiente', $errores['detalle.0']);
        $this->assertSame([], $this->data->escrituras(), 'No debe guardar nada si falla una regla');
    }

    public function testRechazaProductoInactivoOInexistente(): void
    {
        $errores = $this->erroresAlCrear(['detalle' => [
            ['producto_id' => 3, 'cantidad' => 1],
            ['producto_id' => 99, 'cantidad' => 1],
        ]]);

        $this->assertStringContainsString('inactivo', $errores['detalle.0']);
        $this->assertStringContainsString('no existe', $errores['detalle.1']);
    }

    public function testRechazaClienteInexistente(): void
    {
        $errores = $this->erroresAlCrear(['cliente_id' => 50, 'detalle' => [['producto_id' => 1, 'cantidad' => 1]]]);

        $this->assertArrayHasKey('cliente_id', $errores);
    }

    public static function detallesInvalidos(): array
    {
        return [
            'sin detalle' => [[], 'detalle'],
            'detalle vacío' => [['detalle' => []], 'detalle'],
            'cantidad cero' => [['detalle' => [['producto_id' => 1, 'cantidad' => 0]]], 'detalle.0'],
            'cantidad como texto' => [['detalle' => [['producto_id' => 1, 'cantidad' => '2']]], 'detalle.0'],
            'producto repetido' => [['detalle' => [['producto_id' => 1, 'cantidad' => 1], ['producto_id' => 1, 'cantidad' => 2]]], 'detalle.1'],
            'descuento excesivo' => [['descuento_porcentaje' => 60, 'detalle' => [['producto_id' => 1, 'cantidad' => 1]]], 'descuento_porcentaje'],
            'método de pago inválido' => [['metodo_pago' => 'Bitcoin', 'detalle' => [['producto_id' => 1, 'cantidad' => 1]]], 'metodo_pago'],
        ];
    }

    #[DataProvider('detallesInvalidos')]
    public function testValidaLaEstructuraDeLaVenta(array $venta, string $campo): void
    {
        $this->assertArrayHasKey($campo, $this->erroresAlCrear($venta));
    }

    public function testCotizarCalculaSinGuardar(): void
    {
        $resultado = $this->servicio->cotizar([
            'descuento_porcentaje' => 10,
            'detalle' => [['producto_id' => 1, 'cantidad' => 2]],
        ]);

        $this->assertSame(200, $resultado->status);
        $this->assertEquals(4300, $resultado->body['data']['subtotal']);
        $this->assertEquals(430, $resultado->body['data']['descuento']);
        $this->assertEquals(503.1, $resultado->body['data']['impuesto']);
        $this->assertEquals(4373.1, $resultado->body['data']['total']);
        $this->assertSame([], $this->data->escrituras());
    }

    public function testSoloPermiteEditarCamposQueNoAfectanMontos(): void
    {
        $this->expectException(ValidacionException::class);

        $this->servicio->actualizar('10', ['total' => 1], $this->vendedor);
    }

    public function testAnularEnviaElUsuarioQueAnula(): void
    {
        $this->data->responder('DELETE', 'ventas/10', 200, ['message' => 'Venta anulada exitosamente.']);

        $resultado = $this->servicio->eliminar('10', ['sub' => '1', 'rol' => 'Admin']);

        $this->assertSame(200, $resultado->status);
        $this->assertSame(['usuario_id' => 1], $this->data->ultimaLlamada()['query']);
    }

    private function erroresAlCrear(array $venta): array
    {
        try {
            $this->servicio->crear($venta, $this->vendedor);
        } catch (ValidacionException $e) {
            return $e->getErrores();
        }

        $this->fail('Se esperaba una ValidacionException');
    }
}
