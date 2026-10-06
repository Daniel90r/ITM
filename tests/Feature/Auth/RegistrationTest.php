<?php

namespace Tests\Feature\Auth;

use App\Models\Alumno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 0 — Registro público deshabilitado.
 *
 * Regla del proyecto: los alumnos DEBEN crearse desde Gestion de Alumnos por
 * el administrador. No existe auto-registro público de alumnos, por lo que las
 * rutas GET/POST /register estan retiradas.
 *
 * Estas pruebas garantizan la regresion: el registro publico no esta
 * disponible, no se crean usuarios/alumnos via /register, el administrador
 * sigue pudiendo crear alumnos correctamente y el login continua funcionando.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // ── 1. GET /register no esta disponible ─────────────────────────────────

    public function test_get_register_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_register_route_name_no_longer_exists(): void
    {
        $this->assertNull(
            \Illuminate\Support\Facades\Route::getRoutes()->getByName('register'),
            'La ruta `register` no debe estar registrada.'
        );
    }

    // ── 2. POST /register no crea usuarios ni alumnos ───────────────────────

    public function test_post_register_does_not_create_user_or_alumno(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Invitado',
            'email'                 => 'invitado@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'invitado@example.com']);
        $this->assertSame(0, User::count(), 'No se debe crear ningun usuario via /register.');
        $this->assertSame(0, Alumno::count(), 'No se debe crear ningun alumno via /register.');
    }

    // ── 3. El administrador sigue pudiendo crear alumnos correctamente ─────

    public function test_admin_can_still_create_alumno(): void
    {
        $admin = User::factory()->create([
            'role'                 => 'admin',
            'email_verified_at'    => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'                => 'Ana',
            'apellido'              => 'Lopez',
            'fecha_nacimiento'      => '2000-05-15',
            'sexo'                  => 'M',
            'telefono'              => '7777-8888',
            'direccion'             => 'Calle Real',
            'dui'                   => '12345678-9',
            'email'                 => 'ana.lopez@gmail.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.alumnos'));

        $this->assertDatabaseHas('users', [
            'email' => 'ana.lopez@gmail.com',
            'role'  => 'alumno',
        ]);

        $user = User::where('email', 'ana.lopez@gmail.com')->first();
        $this->assertNotNull($user);

        $alumno = Alumno::where('user_id', $user->id)->first();
        $this->assertNotNull($alumno);
        $this->assertSame('Ana', $alumno->nombre);
        $this->assertSame('Lopez', $alumno->apellido);
    }

    // ── 4. El login continua funcionando ────────────────────────────────────

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'role'                => 'alumno',
            'email_verified_at'   => now(),
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('alumno.dashboard', absolute: false));
    }
}
