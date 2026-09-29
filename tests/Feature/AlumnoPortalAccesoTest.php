<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AlumnoPortalAccesoTest extends TestCase
{
    use RefreshDatabase;

    private function alumnoValido(): array
    {
        $user = User::factory()->create(['role' => 'alumno']);
        $alumno = Alumno::factory()->create(['user_id' => $user->id]);

        return [$user, $alumno];
    }

    private function cursoProximo(): Curso
    {
        return Curso::factory()->create([
            'nombre' => 'Curso Proximo',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
            'finalizado_manualmente' => false,
            'estado' => null,
            'fecha_inicio' => now()->addMonth(),
            'fecha_fin' => now()->addMonths(8),
        ]);
    }

    // ── 1. Usuario alumno válido -> 200 ─────────────────────────────────────

    #[Test]
    public function test_alumno_valido_abre_la_pagina_de_inscripcion(): void
    {
        [$user] = $this->alumnoValido();
        $this->cursoProximo();

        $this->actingAs($user)
            ->get(route('alumno.inscripcion'))
            ->assertOk();
    }

    #[Test]
    public function test_alumno_valido_todas_las_rutas_del_portal_responden_200(): void
    {
        [$user] = $this->alumnoValido();
        $this->cursoProximo();

        foreach ([
            'alumno.dashboard',
            'alumno.inscripcion',
            'alumno.notas',
            'alumno.pagos',
        ] as $ruta) {
            $this->actingAs($user)->get(route($ruta))->assertOk();
        }
    }

    // ── 2 y 3. Admin y docente NO entran al portal del alumno ───────────────

    #[Test]
    public function test_administrador_no_entra_al_portal_del_alumno(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('alumno.inscripcion'));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function test_docente_no_entra_al_portal_del_alumno(): void
    {
        $docente = User::factory()->create(['role' => 'docente']);

        $response = $this->actingAs($docente)->get(route('alumno.inscripcion'));

        $response->assertRedirect(route('docente.notas'));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function test_administrador_no_entra_a_ninguna_ruta_del_portal_alumno(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['alumno.dashboard', 'alumno.inscripcion', 'alumno.notas', 'alumno.pagos'] as $ruta) {
            $this->actingAs($admin)
                ->get(route($ruta))
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    #[Test]
    public function test_usuario_sin_expediente_no_ve_contenido_del_portal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('alumno.inscripcion'));

        $response->assertDontSee('Selección de Sección');
        $this->assertDatabaseCount('alumnos', 0);
    }

    #[Test]
    public function test_el_mensaje_explica_la_causa_del_bloqueo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('alumno.inscripcion'))
            ->assertSessionHas('error', function ($mensaje) {
                return str_contains($mensaje, 'expediente de alumno');
            });
    }

    #[Test]
    public function test_visitante_sin_sesion_es_redirigido_al_login(): void
    {
        $this->get(route('alumno.inscripcion'))
            ->assertRedirect(route('login'));
    }

    // ── 4. Alumno sin cursos Próximos -> pantalla válida, no 403 ────────────

    #[Test]
    public function test_alumno_sin_cursos_proximos_ve_mensaje_y_no_403(): void
    {
        [$user] = $this->alumnoValido();

        // Curso Activo: no debe aparecer
        Curso::factory()->create([
            'nombre' => 'Curso Activo',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonths(6),
            'estado' => null,
        ]);

        $response = $this->actingAs($user)->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('No hay cursos disponibles para inscripción.');
        $response->assertDontSee('Curso Activo');
    }

    #[Test]
    public function test_alumno_sin_ningun_curso_ve_mensaje_y_no_403(): void
    {
        [$user] = $this->alumnoValido();

        $response = $this->actingAs($user)->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('No hay cursos disponibles para inscripción.');
    }

    // ── 5. Alumno con curso Próximo -> lo muestra ───────────────────────────

    #[Test]
    public function test_alumno_con_curso_proximo_ve_el_curso(): void
    {
        [$user] = $this->alumnoValido();
        $curso = $this->cursoProximo();

        $response = $this->actingAs($user)->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('Curso Proximo');
        $response->assertDontSee('No hay cursos disponibles');
        $response->assertSee('value="' . $curso->id . '"', false);
    }

    // ── 6. Solicitud pendiente -> comportamiento controlado ────────────────

    #[Test]
    public function test_alumno_con_solicitud_pendiente_no_ve_el_selector(): void
    {
        [$user, $alumno] = $this->alumnoValido();
        $curso = $this->cursoProximo();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($user)->get(route('alumno.inscripcion'));

        $response->assertOk();
        $this->assertStringNotContainsString('name="curso_id"', $response->getContent());
    }

    #[Test]
    public function test_alumno_con_solicitud_pendiente_no_puede_crear_otra(): void
    {
        [$user, $alumno] = $this->alumnoValido();
        $cursoA = $this->cursoProximo();
        $cursoB = $this->cursoProximo();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($user)
            ->post(route('alumno.inscribirse'), ['curso_id' => $cursoB->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 1);
    }

    // ── 7. Inscripción activa -> comportamiento controlado ──────────────────

    #[Test]
    public function test_alumno_con_inscripcion_aprobada_no_ve_el_selector(): void
    {
        [$user, $alumno] = $this->alumnoValido();
        $curso = $this->cursoProximo();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        $response = $this->actingAs($user)->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('¡Inscripción aprobada!');
        $this->assertStringNotContainsString('name="curso_id"', $response->getContent());
    }

    #[Test]
    public function test_alumno_con_inscripcion_aprobada_no_puede_crear_otra(): void
    {
        [$user, $alumno] = $this->alumnoValido();
        $cursoA = $this->cursoProximo();
        $cursoB = $this->cursoProximo();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        $this->actingAs($user)
            ->post(route('alumno.inscribirse'), ['curso_id' => $cursoB->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 1);
    }

    #[Test]
    public function test_alumno_con_solicitud_rechazada_vuelve_a_ver_el_selector(): void
    {
        [$user, $alumno] = $this->alumnoValido();
        $curso = $this->cursoProximo();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        $response = $this->actingAs($user)->get(route('alumno.inscripcion'));

        $response->assertOk();
        $this->assertStringContainsString('name="curso_id"', $response->getContent());
    }

    // ── La autorización sigue vigente: la ruta no es pública ────────────────

    #[Test]
    public function test_la_ruta_sigue_siendo_protegida(): void
    {
        $this->get(route('alumno.inscripcion'))->assertRedirect(route('login'));
        $this->post(route('alumno.inscribirse'), ['curso_id' => 1])
            ->assertRedirect(route('login'));
    }
}
