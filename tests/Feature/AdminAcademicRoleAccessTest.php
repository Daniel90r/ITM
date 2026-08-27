<?php

namespace Tests\Feature;

use App\Models\User;
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
            'admin.notas.store',
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
        $this->actingAs($docente)->post(route('admin.notas.store'), [])->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.boletas'))->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.boletas.show', 9999))->assertStatus(403);
        $this->actingAs($docente)->get(route('admin.boletas.pdf', 9999))->assertStatus(403);
    }

    public function test_alumno_receives_403_for_academic_routes(): void
    {
        $alumno = $this->user('alumno');

        $this->actingAs($alumno)->get(route('admin.asistencias'))->assertStatus(403);
        $this->actingAs($alumno)->get(route('admin.notas'))->assertStatus(403);
        $this->actingAs($alumno)->post(route('admin.notas.store'), [])->assertStatus(403);
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

    public function test_admin_store_notas_empty_data_passes_middleware_and_returns_validation_errors(): void
    {
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->post(route('admin.notas.store'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['alumno_id', 'detalle_curso_id']);
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
