<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use App\Services\UsuarioService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

final class UsuarioServiceTest extends TestCase
{
    private FakeDataService $data;
    private UsuarioService $servicio;
    private array $admin = ['sub' => '1', 'rol_id' => 1, 'rol' => 'Admin'];

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new UsuarioService($this->data);
    }

    public function testCreaUsuarioConDatosValidos(): void
    {
        $this->data->responder('POST', 'usuarios', 201, ['data' => ['id' => 5]]);

        $resultado = $this->servicio->crear([
            'username' => 'vendedor05',
            'password' => 'Ventas2026#',
            'nombre_completo' => 'Juan Pérez Mora',
            'correo' => 'juan@inventario.cr',
            'rol_id' => 2,
        ], $this->admin);

        $this->assertSame(201, $resultado->status);
    }

    public function testExigeContrasenaSegura(): void
    {
        try {
            $this->servicio->crear([
                'username' => 'vendedor05',
                'password' => 'password',
                'nombre_completo' => 'Juan Pérez Mora',
                'correo' => 'juan@inventario.cr',
                'rol_id' => 2,
            ], $this->admin);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertSame(['password'], array_keys($e->getErrores()));
        }
    }

    public function testNoPuedeEliminarseASiMismo(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(409);

        $this->servicio->eliminar('1', $this->admin);
    }

    public function testNoPuedeQuitarseElRolNiDesactivarse(): void
    {
        try {
            $this->servicio->actualizar('1', ['rol_id' => 2, 'activo' => false], $this->admin);
            $this->fail('Se esperaba ValidacionException');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('rol_id', $e->getErrores());
            $this->assertArrayHasKey('activo', $e->getErrores());
        }
    }

    public function testPuedeDesactivarAOtroUsuario(): void
    {
        $this->data->responder('PUT', 'usuarios/3', 200, ['data' => ['id' => 3, 'activo' => false]]);

        $this->assertSame(200, $this->servicio->actualizar('3', ['activo' => false], $this->admin)->status);
    }
}
