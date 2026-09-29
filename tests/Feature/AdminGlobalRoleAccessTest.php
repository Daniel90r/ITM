<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AdminGlobalRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $extra));
    }

    /**
     * Todas las rutas GET de /admin/* que no exigen parámetros.
     */
    private function paginasAdmin(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin'))
            ->reject(fn ($r) => $r->parameterNames() !== [])
            ->map(fn ($r) => $r->uri())
            ->values()
            ->all();
    }

    private function rutasAdmin(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin'))
            ->values()
            ->all();
    }

    // ── 1. Todas las rutas admin exigen role:admin ───────────────────────────

    #[Test]
    public function test_todas_las_rutas_admin_exigen_role_admin(): void
    {
        $rutas = $this->rutasAdmin();

        $this->assertGreaterThan(30, count($rutas), 'se esperaban todas las rutas /admin');

        foreach ($rutas as $ruta) {
            $mw = app('router')->gatherRouteMiddleware($ruta);

            $this->assertContains('auth', $mw, "{$ruta->uri()} [{$ruta->methods()[0]}] sin auth");
            $this->assertContains('verified', $mw, "{$ruta->uri()} [{$ruta->methods()[0]}] sin verified");
            $this->assertContains('role:admin', $mw, "{$ruta->uri()} [{$ruta->methods()[0]}] sin role:admin");

            $this->assertNotContains('role:alumno', $mw);
            $this->assertNotContains('role:docente', $mw);
            $this->assertNotContains('alumno.portal', $mw);
        }
    }

    // ── 2. Admin entra a todas las páginas /admin ─────────────────────────────

    #[Test]
    public function test_admin_accede_a_todas_las_paginas_admin(): void
    {
        $admin = $this->user('admin');

        foreach ($this->paginasAdmin() as $uri) {
            $estado = $this->actingAs($admin)->get("/{$uri}")->getStatusCode();

            $this->assertNotSame(403, $estado, "el admin debe entrar a /{$uri}");
        }
    }

    #[Test]
    public function test_admin_accede_a_las_paginas_admin_principales(): void
    {
        $admin = $this->user('admin');

        foreach (['dashboard', 'alumnos', 'maestros', 'cursos', 'solicitudes', 'materias'] as $seccion) {
            $this->actingAs($admin)
                ->get(route("admin.{$seccion}"))
                ->assertOk("el admin debe ver /admin/{$seccion}");
        }
    }

    // ── 3 y 4. Alumno y docente reciben 403 ──────────────────────────────────

    #[Test]
    public function test_alumno_recibe_403_en_todas_las_paginas_admin(): void
    {
        $alumno = $this->user('alumno');

        foreach ($this->paginasAdmin() as $uri) {
            $this->actingAs($alumno)
                ->get("/{$uri}")
                ->assertForbidden("el alumno no debe entrar a /{$uri}");
        }
    }

    #[Test]
    public function test_docente_recibe_403_en_todas_las_paginas_admin(): void
    {
        $docente = $this->user('docente');

        foreach ($this->paginasAdmin() as $uri) {
            $this->actingAs($docente)
                ->get("/{$uri}")
                ->assertForbidden("el docente no debe entrar a /{$uri}");
        }
    }

    #[Test]
    public function test_invitado_es_redirigido_a_login(): void
    {
        foreach (['dashboard', 'alumnos', 'cursos'] as $seccion) {
            $this->get(route("admin.{$seccion}"))->assertRedirect(route('login'));
        }
    }

    // ── 5. El middleware exige el rol exacto ──────────────────────────────────

    #[Test]
    public function test_la_bd_solo_acepta_los_tres_roles_validos(): void
    {
        foreach (['Admin', 'ADMIN', 'administrador', 'admin '] as $rolInvalido) {
            try {
                User::factory()->create(['role' => $rolInvalido]);
                $this->fail("la BD aceptó un rol inválido: '{$rolInvalido}'");
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('role', $e->getMessage());
            }
        }
    }

    #[Test]
    public function test_role_middleware_compara_de_forma_estricta(): void
    {
        $middleware = new \App\Http\Middleware\RoleMiddleware();

        foreach (['Admin', 'ADMIN', 'administrador', 'admin ', '', 'null'] as $rolInvalido) {
            $request = \Illuminate\Http\Request::create('/admin/dashboard');
            $request->setUserResolver(fn () => (new User(['role' => $rolInvalido]))->forceFill(['role' => $rolInvalido]));

            $excepcion = null;

            try {
                $middleware->handle($request, fn ($r) => response('ok'), 'admin');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $excepcion = $e;
            }

            $this->assertNotNull($excepcion, "el rol '{$rolInvalido}' no debe ser aceptado");
            $this->assertSame(403, $excepcion->getStatusCode());
            $this->assertSame('Acceso no autorizado.', $excepcion->getMessage());
        }
    }

    #[Test]
    public function test_role_middleware_pasa_con_el_rol_admin_exacto(): void
    {
        $middleware = new \App\Http\Middleware\RoleMiddleware();
        $request = \Illuminate\Http\Request::create('/admin/dashboard');
        $request->setUserResolver(fn () => new User(['role' => 'admin']));

        $respuesta = $middleware->handle($request, fn ($r) => response('ok'), 'admin');

        $this->assertSame('ok', $respuesta->getContent());
    }

    #[Test]
    public function test_role_middleware_soporta_multiples_roles_en_la_misma_ruta(): void
    {
        $middleware = new \App\Http\Middleware\RoleMiddleware();

        $request = \Illuminate\Http\Request::create('/docente/dashboard');
        $request->setUserResolver(fn () => new User(['role' => 'docente']));

        $respuesta = $middleware->handle($request, fn ($r) => response('ok'), 'admin', 'docente');

        $this->assertSame('ok', $respuesta->getContent());
    }

    // ── 6. La verificación de email no bloquea al admin ───────────────────────

    #[Test]
    public function test_admin_sin_email_verificado_puede_entrar(): void
    {
        $admin = $this->user('admin', ['email_verified_at' => null]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.alumnos'))->assertOk();
    }
}
