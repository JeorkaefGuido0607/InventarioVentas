<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\ClienteValidator;
use App\Validators\ProductoValidator;
use App\Validators\ProveedorValidator;
use PHPUnit\Framework\TestCase;

final class ValidadoresTest extends TestCase
{
    public function testClienteValido(): void
    {
        $errores = (new ClienteValidator())->validate([
            'cedula' => '702340567',
            'nombre_completo' => 'Ana Lucía Vargas',
            'telefono' => '8812-3344',
            'correo' => 'ana@correo.cr',
            'latitud' => 9.9913,
            'longitud' => -83.0359,
        ]);

        $this->assertSame([], $errores);
    }

    public function testClienteConCamposOpcionalesVacios(): void
    {
        $errores = (new ClienteValidator())->validate([
            'cedula' => '155812345678',
            'nombre_completo' => 'Roberto López Díaz',
            'telefono' => '',
            'correo' => null,
        ]);

        $this->assertSame([], $errores);
    }

    public function testClienteInvalido(): void
    {
        $errores = (new ClienteValidator())->validate([
            'cedula' => '12345',
            'nombre_completo' => 'Ana',
            'telefono' => '1234-5678',
            'correo' => 'no-es-correo',
            'latitud' => 120,
        ]);

        $this->assertSame(['cedula', 'nombre_completo', 'telefono', 'correo', 'latitud'], array_keys($errores));
    }

    public function testProductoRequiereCamposObligatorios(): void
    {
        $errores = (new ProductoValidator())->validate([]);

        $this->assertSame(['codigo', 'nombre', 'categoria_id', 'precio_compra', 'precio_venta'], array_keys($errores));
    }

    public function testValidacionParcialSoloRevisaLoQueSeEnvia(): void
    {
        $this->assertSame([], (new ProductoValidator())->validate(['nombre' => 'Arroz 1 kg'], true));
    }

    public function testCedulaJuridicaDeProveedor(): void
    {
        $validador = new ProveedorValidator();

        $this->assertSame([], $validador->validate(['cedula_juridica' => '3101123456', 'nombre' => 'Distribuidora Caribe']));
        $this->assertArrayHasKey('cedula_juridica', $validador->validate(['cedula_juridica' => '3-101-123456', 'nombre' => 'Distribuidora Caribe']));
    }
}
