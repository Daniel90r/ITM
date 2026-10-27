<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Portal público de oferta académica (SCRUM-2, SCRUM-4, SCRUM-88).
 */
class PortalPublicoTest extends TestCase
{
    use RefreshDatabase;

    private function crearCurso(string $nombre, array $attrs = []): Curso
    {
        return Curso::factory()->create(array_merge([
            'nombre' => $nombre,
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
            'finalizado_manualmente' => false,
            'estado' => null,
            'fecha_inicio' => now()->addMonth(),
            'fecha_fin' => now()->addMonths(8),
        ], $attrs));
    }

    #[Test]
    public function la_raiz_muestra_la_oferta_sin_iniciar_sesion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Oferta académica')
            ->assertSee(route('login'), false);
    }

    #[Test]
    public function lista_cursos_visibles_con_sus_materias(): void
    {
        $curso = $this->crearCurso('Informática Básica');
        $materia = Materia::factory()->create(['nombre' => 'Ofimática', 'activa' => true]);
        DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Informática Básica')
            ->assertSee('Sección A')
            ->assertSee('Ofimática')
            ->assertSee('Inscripciones abiertas');
    }

    #[Test]
    public function oculta_cursos_inactivos_y_finalizados(): void
    {
        $this->crearCurso('Curso Oculto', ['activo' => false]);
        $this->crearCurso('Curso Cerrado', ['finalizado_manualmente' => true]);
        $this->crearCurso('Curso Vencido', ['fecha_inicio' => now()->subMonths(8), 'fecha_fin' => now()->subDay()]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Curso Oculto')
            ->assertDontSee('Curso Cerrado')
            ->assertDontSee('Curso Vencido');
    }

    #[Test]
    public function un_curso_ya_iniciado_aparece_sin_inscripcion_abierta(): void
    {
        $this->crearCurso('Inglés Intermedio', ['fecha_inicio' => now()->subWeek()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Inglés Intermedio')
            ->assertSee('En curso')
            ->assertDontSee('Inscripciones abiertas');
    }

    #[Test]
    public function el_enlace_de_inscripcion_lleva_al_login_si_no_hay_sesion(): void
    {
        $this->get('/')->assertSee(route('alumno.inscripcion'), false);

        $this->get(route('alumno.inscripcion'))->assertRedirect(route('login'));
    }

    #[Test]
    public function un_usuario_con_sesion_ve_el_acceso_a_su_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('Ir a mi panel');
    }
}
