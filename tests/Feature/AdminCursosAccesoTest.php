<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AdminCursosAccesoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function alumno(): User
    {
        $user = User::factory()->create(['role' => 'alumno']);
        Alumno::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function docente(): User
    {
        return User::factory()->create(['role' => 'docente']);
    }

    private function curso(): Curso
    {
        return Curso::factory()->create([
            'nombre' => 'Curso A',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
        ]);
    }

    private function crearMaestro(): Maestro
    {
        $user = User::factory()->create(['role' => 'docente']);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'codigo' => 'CM001',
            'activo' => true,
        ]);
    }

    // ── 1. ADMIN -> 200 en /admin/cursos ───────────────────────────────────

    #[Test]
    public function test_admin_accede_a_la_lista_de_cursos(): void
    {
        $this->curso();

        $this->actingAs($this->admin())
            ->get(route('admin.cursos'))
            ->assertOk();
    }

    #[Test]
    public function test_admin_ve_las_tarjetas_de_cursos(): void
    {
        $curso = $this->curso();

        $response = $this->actingAs($this->admin())->get(route('admin.cursos'));

        $response->assertOk();
        $response->assertSee('Curso A');
        $response->assertSee(route('admin.cursos.show', $curso->id), false);
    }

    // ── 2. ADMIN -> detalle y edición ──────────────────────────────────────

    #[Test]
    public function test_admin_accede_al_detalle_y_a_la_edicion_de_un_curso(): void
    {
        $curso = $this->curso();

        $this->actingAs($this->admin())
            ->get(route('admin.cursos.show', $curso->id))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.cursos.edit', $curso->id))
            ->assertOk();
    }

    #[Test]
    public function test_admin_puede_editar_y_guardar_un_curso(): void
    {
        $curso = $this->curso();

        $this->actingAs($this->admin())
            ->put(route('admin.cursos.update', $curso->id), [
                'nombre' => 'Curso Editado',
                'nivel' => 'Vespertino',
                'seccion' => 'C',
                'anio_lectivo' => 2027,
                'fecha_inicio' => '2026-10-01',
                'fecha_fin' => '2028-10-01',
                'estado' => 'PROXIMO',
            ])
            ->assertRedirect(route('admin.cursos'));

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nombre' => 'Curso Editado']);
    }

    // ── 3. ALUMNO -> 403 ───────────────────────────────────────────────────

    #[Test]
    public function test_alumno_no_accede_a_cursos(): void
    {
        $this->curso();

        $this->actingAs($this->alumno())
            ->get(route('admin.cursos'))
            ->assertForbidden();
    }

    // ── 4. DOCENTE -> 403 ──────────────────────────────────────────────────

    #[Test]
    public function test_docente_no_accede_a_cursos(): void
    {
        $this->curso();

        $this->actingAs($this->docente())
            ->get(route('admin.cursos'))
            ->assertForbidden();
    }

    #[Test]
    public function test_visitante_no_accede_a_cursos(): void
    {
        $this->get(route('admin.cursos'))->assertRedirect(route('login'));
    }

    // ── 3.b Editar curso: mismo corte de roles que el listado ──────────────

    #[Test]
    public function test_alumno_no_accede_a_editar_curso(): void
    {
        $curso = $this->curso();

        $this->actingAs($this->alumno())
            ->get(route('admin.cursos.edit', $curso->id))
            ->assertForbidden();
    }

    #[Test]
    public function test_docente_no_accede_a_editar_curso(): void
    {
        $curso = $this->curso();

        $this->actingAs($this->docente())
            ->get(route('admin.cursos.edit', $curso->id))
            ->assertForbidden();
    }

    #[Test]
    public function test_alumno_no_actualiza_curso(): void
    {
        $curso = $this->curso();

        $this->actingAs($this->alumno())
            ->put(route('admin.cursos.update', $curso->id), [
                'nombre' => 'Intruso',
                'nivel' => 'Vespertino',
                'seccion' => 'C',
                'anio_lectivo' => 2027,
                'fecha_inicio' => '2026-10-01',
                'fecha_fin' => '2028-10-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nombre' => 'Curso A']);
    }

    #[Test]
    public function test_docente_no_actualiza_curso(): void
    {
        $curso = $this->curso();

        $this->actingAs($this->docente())
            ->put(route('admin.cursos.update', $curso->id), [
                'nombre' => 'Intruso',
                'nivel' => 'Vespertino',
                'seccion' => 'C',
                'anio_lectivo' => 2027,
                'fecha_inicio' => '2026-10-01',
                'fecha_fin' => '2028-10-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'nombre' => 'Curso A']);
    }

    // ── 3.c Un curso finalizado sigue protegido ───────────────────────────

    #[Test]
    public function test_admin_no_cambia_el_estado_de_un_curso_finalizado(): void
    {
        $curso = $this->curso();
        $curso->update(['finalizado_manualmente' => true]);

        $this->assertSame(Curso::ESTADO_FINALIZADO, $curso->fresh()->estado_curso);

        $this->actingAs($this->admin())
            ->from(route('admin.cursos.edit', $curso->id))
            ->put(route('admin.cursos.update', $curso->id), [
                'nombre' => 'Curso A',
                'nivel' => 'Matutino',
                'seccion' => 'A',
                'anio_lectivo' => (int) now()->year,
                'fecha_inicio' => '2026-11-01',
                'fecha_fin' => '2028-11-26',
                'estado' => Curso::ESTADO_ACTIVO,
            ])
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'finalizado_manualmente' => true,
            'estado' => null,
        ]);
    }

    // ── 5. El resto de rutas admin sigue funcionando para el admin ─────────

    #[Test]
    public function test_admin_sigue_accediendo_a_las_demas_rutas_admin(): void
    {
        $curso = $this->curso();
        $maestro = $this->crearMaestro();

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.alumnos'))->assertOk();
        $this->actingAs($admin)->get(route('admin.maestros'))->assertOk();
        $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.solicitudes'))->assertOk();
        $this->actingAs($admin)->get(route('admin.cursos.show', $curso->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.cursos.edit', $curso->id))->assertOk();
    }

    // ── Coherencia: el middleware de Cursos es el mismo que el de Alumnos ───

    #[Test]
    public function test_cursos_y_alumnos_comparten_el_mismo_middleware(): void
    {
        $cursos = collect(app('router')->getRoutes())->first(
            fn ($r) => $r->uri() === 'admin/cursos' && in_array('GET', $r->methods())
        );
        $alumnos = collect(app('router')->getRoutes())->first(
            fn ($r) => $r->uri() === 'admin/alumnos' && in_array('GET', $r->methods())
        );

        $this->assertNotNull($cursos);
        $this->assertNotNull($alumnos);

        $cursosMw = app('router')->gatherRouteMiddleware($cursos);
        $alumnosMw = app('router')->gatherRouteMiddleware($alumnos);

        $this->assertEquals($alumnosMw, $cursosMw, 'ambas rutas deben tener el mismo middleware');
        $this->assertContains('role:admin', $cursosMw);
        $this->assertContains('auth', $cursosMw);
        $this->assertContains('verified', $cursosMw);
    }

    #[Test]
    public function test_todas_las_rutas_de_cursos_exigen_role_admin(): void
    {
        $rutas = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin/cursos'));

        $this->assertGreaterThan(0, $rutas->count());

        foreach ($rutas as $ruta) {
            $this->assertContains(
                'role:admin',
                app('router')->gatherRouteMiddleware($ruta),
                "la ruta {$ruta->uri()} [{$ruta->methods()[0]}] debe exigir role:admin"
            );
        }
    }
}
