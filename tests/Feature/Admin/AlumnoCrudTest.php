<?php

namespace Tests\Feature\Admin;

use App\Models\Alumno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlumnoCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    // ── CREAR ALUMNO ──────────────────────────────────

    public function test_puede_crear_alumno_con_correo_personal_valido(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Juan',
            'apellido'         => 'Pérez',
            'fecha_nacimiento' => '2000-05-15',
            'sexo'             => 'M',
            'telefono'         => '7777-8888',
            'direccion'        => 'Calle Principal',
            'dui'              => '12345678-9',
            'email'            => 'juan.perez@gmail.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.alumnos'));

        $this->assertDatabaseHas('users', [
            'email' => 'juan.perez@gmail.com',
            'role' => 'alumno',
        ]);

        $user = User::where('email', 'juan.perez@gmail.com')->first();
        $this->assertNotNull($user);

        $alumno = Alumno::where('user_id', $user->id)->first();
        $this->assertNotNull($alumno);
        $this->assertSame('Juan', $alumno->nombre);
        $this->assertSame('Pérez', $alumno->apellido);
    }

    public function test_rechaza_correo_invalido_al_crear_alumno(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Juan',
            'apellido'         => 'Pérez',
            'fecha_nacimiento' => '2000-05-15',
            'sexo'             => 'M',
            'dui'              => '12345678-9',
            'email'            => 'correo-no-valido',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_rechaza_correo_duplicado_al_crear_alumno(): void
    {
        $admin = $this->admin();

        User::factory()->create([
            'email' => 'existente@gmail.com',
            'role'  => 'alumno',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Juan',
            'apellido'         => 'Pérez',
            'fecha_nacimiento' => '2000-05-15',
            'sexo'             => 'M',
            'dui'              => '12345678-9',
            'email'            => 'existente@gmail.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_rechaza_correo_obligatorio_al_crear_alumno(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Juan',
            'apellido'         => 'Pérez',
            'fecha_nacimiento' => '2000-05-15',
            'sexo'             => 'M',
            'dui'              => '12345678-9',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_normaliza_correo_a_minusculas_al_crear_alumno(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Maria',
            'apellido'         => 'Lopez',
            'fecha_nacimiento' => '1998-03-10',
            'sexo'             => 'F',
            'dui'              => '87654321-0',
            'email'            => ' Maria.Lopez@Outlook.COM ',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'maria.lopez@outlook.com',
        ]);
    }

    // ── EDITAR ALUMNO ─────────────────────────────────

    public function test_puede_editar_alumno_y_conservar_su_correo(): void
    {
        $admin = $this->admin();

        $user = User::factory()->create([
            'email' => 'original@correo.com',
            'role'  => 'alumno',
            'email_verified_at' => now(),
        ]);

        $alumno = Alumno::factory()->create([
            'user_id' => $user->id,
            'nombre'  => 'Original',
            'apellido'=> 'Test',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.alumnos.update', $alumno->id), [
            'nombre'   => 'Juan Carlos',
            'apellido' => 'Pérez',
            'telefono' => '5555-1234',
            'email'    => 'original@correo.com',
        ]);

        $response->assertRedirect(route('admin.alumnos'));

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'original@correo.com',
        ]);
    }

    public function test_rechaza_cambiar_correo_por_correo_existente_al_editar(): void
    {
        $admin = $this->admin();

        $userA = User::factory()->create([
            'email' => 'aluno.a@gmail.com',
            'role'  => 'alumno',
            'email_verified_at' => now(),
        ]);

        $alumnoA = Alumno::factory()->create(['user_id' => $userA->id]);

        User::factory()->create([
            'email' => 'aluno.b@gmail.com',
            'role'  => 'alumno',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.alumnos.update', $alumnoA->id), [
            'nombre'   => 'Aluno A',
            'apellido' => 'Test',
            'email'    => 'aluno.b@gmail.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_puede_cambiar_correo_al_editar_alumno(): void
    {
        $admin = $this->admin();

        $user = User::factory()->create([
            'email' => 'viejo@correo.com',
            'role'  => 'alumno',
            'email_verified_at' => now(),
        ]);

        $alumno = Alumno::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($admin)->put(route('admin.alumnos.update', $alumno->id), [
            'nombre'   => 'Aluno Nuevo',
            'apellido' => 'Test',
            'email'    => 'nuevo@correo.com',
        ]);

        $response->assertRedirect(route('admin.alumnos'));

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'nuevo@correo.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'id'    => $user->id,
            'email' => 'viejo@correo.com',
        ]);
    }

    // ── CUENTA DE ACCESO ASOCIADA ─────────────────────

    public function test_cuenta_de_acceso_queda_asociada_al_correo_ingresado(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Test',
            'apellido'         => 'Alumno',
            'fecha_nacimiento' => '2005-01-01',
            'sexo'             => 'M',
            'dui'              => '11111111-1',
            'email'            => 'testalumno@hotmail.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.alumnos'));

        $user = User::where('email', 'testalumno@hotmail.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('alumno', $user->role);

        $alumno = Alumno::where('user_id', $user->id)->first();
        $this->assertNotNull($alumno);
        $this->assertSame('Test Alumno', $alumno->nombre_completo);
    }

    public function test_puede_iniciar_sesion_con_correo_personal_registrado(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.alumnos.store'), [
            'nombre'           => 'Login',
            'apellido'         => 'Test',
            'fecha_nacimiento' => '2005-01-01',
            'sexo'             => 'M',
            'dui'              => '22222222-2',
            'email'            => 'logintest@gmail.com',
            'password'         => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email'    => 'logintest@gmail.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
    }
}
