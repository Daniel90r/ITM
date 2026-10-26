<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationSpanishTest extends TestCase
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
        parent::setUp(); $this->withoutVite();
        $this->assertSame('es', app()->getLocale());
    }

    public function test_credenciales_incorrectas_muestran_el_texto_solicitado(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'El correo electrónico o la contraseña son incorrectos.']);
        $this->assertGuest();
    }

    public function test_demasiados_intentos_muestran_segundos_en_espanol(): void
    {
        $email = 'intentos@example.test'; $key = Str::transliterate(Str::lower($email).'|127.0.0.1');
        for ($i = 0; $i < 5; $i++) RateLimiter::hit($key, 60);
        $this->post('/login', ['email' => $email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        $this->assertGuest(); RateLimiter::clear($key);
    }

    public function test_confirmacion_con_contrasena_incorrecta_esta_traducida(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/confirm-password', ['password' => 'incorrecta'])
            ->assertSessionHasErrors(['password' => 'La contraseña es incorrecta.']);
    }

}
