<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlumnoSinPerfilTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_alumno_no_puede_consultar_actualizar_ni_eliminar_su_perfil(): void
    {
        $usuario = User::factory()->create(['role' => 'alumno']);
        $antes = $usuario->fresh()->getRawOriginal();
        $this->actingAs($usuario);
        $datos = ['name' => 'Cambio no autorizado', 'email' => 'cambio@ejemplo.test'];
        $password = ['current_password' => 'password', 'password' => 'nueva-password', 'password_confirmation' => 'nueva-password'];
        $this->get(route('profile.edit'))->assertForbidden();
        $this->patch(route('profile.update'), $datos)->assertForbidden();
        $this->put(route('password.update'), $password)->assertForbidden();
        $this->delete(route('profile.destroy'))->assertForbidden();
        // También bloquear formularios POST con el método HTTP sobrescrito.
        $this->post('/profile', array_merge($datos, ['_method' => 'PATCH']))->assertForbidden();
        $this->post('/profile', ['_method' => 'DELETE'])->assertForbidden();
        $this->post('/password', array_merge($password, ['_method' => 'PUT']))->assertForbidden();
        // La autorización precede a la validación, incluso con datos vacíos.
        $this->patch('/profile', [])->assertForbidden();
        $this->put('/password', [])->assertForbidden();
        $this->assertSame($antes, $usuario->fresh()->getRawOriginal());
        $this->assertAuthenticatedAs($usuario);
    }

    public static function rolesPermitidos(): array
    {
        return [['admin'], ['docente']];
    }

    #[DataProvider('rolesPermitidos')]
    public function test_otros_roles_conservan_consulta_y_actualizacion(string $role): void
    {
        $this->withoutVite();
        $usuario = User::factory()->create(['role' => $role]);
        $this->actingAs($usuario)->get(route('profile.edit'))->assertOk();
        $this->patch(route('profile.update'), ['name' => 'Nombre de prueba', 'email' => $role.'@ejemplo.test'])
            ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertSame($role.'@ejemplo.test', $usuario->fresh()->email);
        $this->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => 'password', 'password' => 'nueva-password', 'password_confirmation' => 'nueva-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertTrue(Hash::check('nueva-password', $usuario->fresh()->password));
        // La prohibición anterior de autoeliminar cuentas sigue intacta.
        $this->delete(route('profile.destroy'))->assertForbidden();
        $this->assertNotNull($usuario->fresh());
    }

    public function test_alumno_conserva_inicio_y_cierre_de_sesion(): void
    {
        $usuario = User::factory()->create(['role' => 'alumno']);
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect(route('alumno.dashboard'));
        $this->assertAuthenticatedAs($usuario);
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
        $this->assertNotNull($usuario->fresh());
    }
}
