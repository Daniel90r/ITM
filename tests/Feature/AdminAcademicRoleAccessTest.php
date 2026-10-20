<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Nota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAcademicRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function academicRouteNames(): array
    {
        return [
            'admin.asistencias',
            'admin.notas',
            'admin.boletas',
            'admin.boletas.show',
            'admin.boletas.pdf',
        ];
    }

    public function test_guest_is_redirected_to_login_for_academic_routes(): void
    {
        foreach (['admin.asistencias', 'admin.notas', 'admin.boletas'] as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertRedirect(route('login'));
        }
    }

    public function test_docente_receives_403_for_academic_routes(): void
    {
        $docente = $this->user('docente');

        $this->actingAs($docente)->get(route('admin.asistencias'))->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.notas'))->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.boletas'))->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.boletas.show', 9999))->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.boletas.pdf', 9999))->assertStatus(403);
    }

    public function test_alumno_receives_403_for_academic_routes(): void
    {
        $alumno = $this->user('alumno');

        $this->actingAs($alumno)->get(route('admin.asistencias'))->assertStatus(403);
        $this->actingAs($alumno)->get(route('admin.notas'))->assertStatus(403);
        $this->actingAs($alumno)->get(route('admin.boletas'))->assertStatus(403);
        $this->actingAs($alumno)->get(route('admin.boletas.show', 9999))->assertStatus(403);
        $this->actingAs($alumno)->get(route('admin.boletas.pdf', 9999))->assertStatus(403);
    }

    public function test_admin_can_access_academic_routes(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.asistencias'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.notas'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.boletas'))->assertStatus(200);
    }

    // Fase 0: POST /admin/notas (admin.notas.store) fue retirado. Carecía de
    // consumidor (vista admin/notas.blade.php solo lee) y el controlador
    // asignaba `auth()->id()` a la FK `notas.registrado_por`, que referencia
    // `maestros.id`: un admin no posee fila en `maestros`, por lo que el POST
    // producía una FK inválida o un maestro erróneo. El registro real de notas
    // lo realiza el docente (docente.notas.guardar).
    public function test_post_admin_notas_route_is_disabled_and_does_not_create_notas(): void
    {
        $admin = $this->user('admin');

        $this->assertNull(
            Route::getRoutes()->getByName('admin.notas.store'),
            'La ruta admin.notas.store no debe existir.'
        );

        // El GET /admin/notas existe (solo lectura), pero el POST fue retirado:
        // la URI coincide con la ruta GET y el método no esta permitido -> 405.
        $response = $this->actingAs($admin)->post('/admin/notas', [
            'alumno_id' => 1,
            'detalle_curso_id' => 1,
            'laboratorio' => 8,
            'examen_teorico' => 9,
            'practica' => 7,
            'conducta' => 'B',
            'registrado_por' => $admin->id,
        ]);

        $response->assertStatus(405);

        $this->assertSame(0, Nota::count(), 'El POST retirado no debe crear notas.');
    }

    // Fase 0: el middleware `verified` fue retirado de los portales porque User
    // no implementa MustVerifyEmail y no hay flujo operativo de verificacion,
    // por lo que era un no-op que dava apariencia de proteccion inexistente.
    public function test_academic_routes_no_longer_carry_verified_middleware(): void
    {
        $checked = ['admin.notas', 'admin.asistencias', 'admin.boletas', 'dashboard'];

        foreach ($checked as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "La ruta '{$name}' debe existir.");
            $this->assertNotContains(
                'verified',
                $route->gatherMiddleware(),
                "La ruta '{$name}' no debe contener el middleware 'verified'."
            );
        }
    }

    public function test_academic_routes_contain_role_admin_middleware(): void
    {
        foreach ($this->academicRouteNames() as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "La ruta '{$routeName}' no fue encontrada.");

            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'role:admin',
                $middleware,
                "La ruta '{$routeName}' no contiene el middleware 'role:admin'."
            );
        }
    }
}
