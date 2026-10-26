<?php

namespace Tests\Feature\Auth;

use App\Models\{Alumno, Maestro, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Notification, Password, Route};
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite(); Notification::fake();
    }

    public function test_login_no_ofrece_recuperacion_y_no_existen_las_rutas_nombradas(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('/forgot-password')->assertDontSee('Olvidaste');
        foreach (['password.request','password.email','password.reset','password.store'] as $name) $this->assertFalse(Route::has($name));
        $this->assertTrue(Route::has('password.update'));
    }

    public function test_antiguas_paginas_no_son_accesibles(): void
    {
        foreach (['/forgot-password','/reset-password','/reset-password/token-antiguo'] as $url) {
            $this->get($url)->assertNotFound();
            $this->call('HEAD', $url)->assertNotFound();
        }
        Notification::assertNothingSent();
    }

    public function test_no_se_pueden_solicitar_enlaces_ni_crear_tokens(): void
    {
        $user = User::factory()->create(['role'=>'admin']);
        $this->post('/forgot-password',['email'=>$user->email])->assertNotFound();
        $this->assertDatabaseCount('password_reset_tokens',0);
        Notification::assertNothingSent();
    }

    public function test_no_se_puede_utilizar_un_token_valido_ni_cambiar_la_contrasena(): void
    {
        $user = User::factory()->create(['role'=>'admin']);
        $token = Password::createToken($user); // Solo en SQLite desechable.
        $hash = $user->password;
        $this->post('/reset-password',['email'=>$user->email,'token'=>$token,'password'=>'changed-password','password_confirmation'=>'changed-password'])->assertNotFound();
        $this->get('/reset-password/'.$token)->assertNotFound();
        $this->assertSame($hash,$user->fresh()->password);
        $this->assertTrue(Hash::check('password',$user->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_autenticacion_de_todos_los_roles_permanece_disponible(): void
    {
        foreach (['admin','docente','alumno'] as $role) {
            $user = User::factory()->create(['role'=>$role]);
            if ($role==='docente') Maestro::factory()->create(['user_id'=>$user->id,'activo'=>true]);
            if ($role==='alumno') Alumno::factory()->create(['user_id'=>$user->id,'activo'=>true]);
            $destino = $role === 'docente' ? 'docente.notas' : $role.'.dashboard';
            $this->post('/login',['email'=>$user->email,'password'=>'password'])->assertRedirect(route($destino,absolute:false));
            $this->assertAuthenticatedAs($user);
            $this->get('/forgot-password')->assertNotFound();
            $this->post('/reset-password',['email'=>$user->email])->assertNotFound();
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_docente_conserva_cambio_autenticado_de_contrasena(): void
    {
        $user = User::factory()->create(['role'=>'docente']);
        Maestro::factory()->create(['user_id'=>$user->id,'activo'=>true]);
        $this->actingAs($user)->from('/profile')->put('/password',['current_password'=>'password','password'=>'new-password','password_confirmation'=>'new-password'])
            ->assertRedirect('/profile')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password',$user->fresh()->password));
    }
}
