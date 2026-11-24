<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Exceptions\ValidacionException;
use App\Services\AuthService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeDataService;

final class AuthServiceTest extends TestCase
{
    private const CLAVE = 'clave-de-pruebas-con-mas-de-32-caracteres';

    private FakeDataService $data;
    private AuthService $servicio;

    protected function setUp(): void
    {
        $this->data = new FakeDataService();
        $this->servicio = new AuthService($this->data, (object) ['key' => self::CLAVE, 'jwt_ttl' => 3600]);
    }

    public function testEmiteUnJwtConElRolDelUsuario(): void
    {
        $this->data->responder('POST', 'auth/credentials', 200, [
            'id' => 2, 'username' => 'vendedor01', 'rol_id' => 2, 'rol' => 'Vendedor', 'nombre_completo' => 'Daniel Brenes Mora',
        ]);

        $sesion = $this->servicio->login(['username' => 'vendedor01', 'password' => 'Ventas2026#']);
        $token = (array) JWT::decode($sesion['token'], new Key(self::CLAVE, 'HS256'));

        $this->assertSame('Bearer', $sesion['token_type']);
        $this->assertSame('2', $token['sub']);
        $this->assertSame('Vendedor', $token['rol']);
        $this->assertSame(3600, $token['exp'] - $token['iat']);
        $this->assertSame('Vendedor', $sesion['user']['rol']);
    }

    public function testCredencialesInvalidasResponden401(): void
    {
        $this->data->responder('POST', 'auth/credentials', 401, ['error' => 'Credenciales inválidas.']);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(401);
        $this->servicio->login(['username' => 'vendedor01', 'password' => 'incorrecta']);
    }

    public function testExigeUsuarioYContrasena(): void
    {
        $this->expectException(ValidacionException::class);
        $this->servicio->login(['username' => '  ']);
    }

    public function testPerfilSeArmaDesdeElToken(): void
    {
        $perfil = AuthService::perfil(['sub' => '4', 'username' => 'bodeguero01', 'rol_id' => 3, 'rol' => 'Bodeguero', 'nombre' => 'Carlos Mena Rojas']);

        $this->assertSame(4, $perfil['id']);
        $this->assertSame('Bodeguero', $perfil['rol']);
    }
}
