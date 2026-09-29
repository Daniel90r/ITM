<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * El detalle del curso muestra, por alumno, las materias OPCIONALES que lleva.
 *
 * La condición debe salir de `materias.es_opcional`, nunca de un id ni de un
 * nombre hardcodeados, y la vista no debe consultar nada: todo se prepara en
 * SeccionController@show.
 */
class CursoDetalleOpcionalesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function curso(): Curso
    {
        return Curso::create([
            'nombre' => 'Curso A',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
            'activo' => true,
        ]);
    }

    private function materia(string $nombre, string $codigo, bool $opcional = false): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activa' => true,
            'es_opcional' => $opcional,
        ]);
    }

    private function detalle(Curso $curso, Materia $materia): DetalleCurso
    {
        return DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
        ]);
    }

    private function alumnoInscrito(Curso $curso, ?string $nombre = null): Alumno
    {
        $alumno = Alumno::factory()->create([
            'nombre' => $nombre ?? fake()->firstName(),
            'apellido' => fake()->lastName(),
        ]);

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        return $alumno;
    }

    private function anotarAlumnoEnMateria(Alumno $alumno, Curso $curso, Materia $materia): void
    {
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function verDetalle(Curso $curso)
    {
        return $this->actingAs($this->admin)->get(route('admin.cursos.show', $curso->id));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Tests
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_alumno_con_materia_opcional_muestra_el_badge_de_esa_materia(): void
    {
        $curso = $this->curso();
        $ingles = $this->materia('Inglés', 'ING', true);
        $this->detalle($curso, $ingles);

        $marcos = $this->alumnoInscrito($curso, 'Marcos');
        $mario  = $this->alumnoInscrito($curso, 'Mario');

        $this->anotarAlumnoEnMateria($marcos, $curso, $ingles);

        $response = $this->verDetalle($curso);

        $response->assertOk();
        $response->assertSee('Marcos', false);
        $response->assertSee('Inglés', false);

        // Mario no lleva la opcional: no se le puede asociar el badge.
        $badgeIngles = $this->badgeDe($response->getContent(), 'Mario');
        $this->assertStringNotContainsString('Inglés', $badgeIngles);
    }

    #[Test]
    public function test_alumno_sin_materias_opcionales_no_muestra_badge(): void
    {
        $curso = $this->curso();
        $ingles = $this->materia('Inglés', 'ING', true);
        $this->detalle($curso, $ingles);

        $mario = $this->alumnoInscrito($curso, 'Mario');

        $response = $this->verDetalle($curso);

        $response->assertOk();
        $this->assertStringNotContainsString('Inglés', $this->badgeDe($response->getContent(), $mario->nombre));
    }

    #[Test]
    public function test_una_optional_con_id_distinto_de_4_tambien_se_muestra(): void
    {
        $curso = $this->curso();

        // Relleno hasta que la opcional quede claramente lejos del id 4.
        $this->materia('Windows', 'WIN');
        $this->materia('Word', 'WRD');
        $this->materia('Excel', 'EXC');
        $this->materia('Internet', 'INT');
        $this->materia('PowerPoint', 'PPT');

        $computacion = $this->materia('Computación Avanzada', 'CPA', true);
        $this->assertNotEquals(4, $computacion->id);

        $this->detalle($curso, $computacion);

        $luis = $this->alumnoInscrito($curso, 'Luis');
        $this->anotarAlumnoEnMateria($luis, $curso, $computacion);

        $response = $this->verDetalle($curso);

        $response->assertOk();
        $this->assertStringContainsString('Computación Avanzada', $this->badgeDe($response->getContent(), $luis->nombre));
    }

    #[Test]
    public function test_una_materia_normal_con_id_4_no_se_trata_como_opcional(): void
    {
        $curso = $this->curso();

        // Relleno para que la cuarta materia creada sea exactamente la id 4.
        $this->materia('Windows', 'WIN');
        $this->materia('Word', 'WRD');
        $this->materia('Excel', 'EXC');

        $cuarta = $this->materia('PowerPoint', 'PPT');
        $this->assertEquals(4, $cuarta->id);

        $this->detalle($curso, $cuarta);

        $ana = $this->alumnoInscrito($curso, 'Ana');
        // Aunque exista la fila en alumno_materia, la materia NO es opcional.
        $this->anotarAlumnoEnMateria($ana, $curso, $cuarta);

        $response = $this->verDetalle($curso);

        $response->assertOk();
        $this->assertStringNotContainsString('Word', $this->badgeDe($response->getContent(), $ana->nombre));
    }

    #[Test]
    public function test_varios_alumnos_no_generan_consultas_adicionales_en_la_vista(): void
    {
        $curso = $this->curso();
        $ingles = $this->materia('Inglés', 'ING', true);
        $this->detalle($curso, $ingles);

        $alumnos = [];
        for ($i = 0; $i < 3; $i++) {
            $alumno = $this->alumnoInscrito($curso);            $this->anotarAlumnoEnMateria($alumno, $curso, $ingles);
            $alumnos[] = $alumno;
        }

        // warmed up: se descartan las consultas de arranque del framework.
        $this->verDetalle($curso)->assertOk();

        $consultas = [];
        DB::listen(function ($query) use (&$consultas) {
            $consultas[] = $query->sql;
        });

        $this->verDetalle($curso)->assertOk();

        $porAlumno = array_filter($consultas, function ($sql) use ($alumnos) {
            foreach ($alumnos as $alumno) {
                if (str_contains($sql, 'alumno_materia')) {
                    return true;
                }
            }
            return false;
        });

        // Una sola consulta de alumno_materia para todo el curso, no una por alumno.
        $this->assertCount(
            1,
            $porAlumno,
            'La vista no debe consultar alumno_materia dentro del bucle de alumnos.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Utilidad: aísla la línea del alumno en el HTML renderizado
    // ═══════════════════════════════════════════════════════════════════════

    private function badgeDe(string $html, string $nombreAlumno): string
    {
        $this->assertStringContainsString($nombreAlumno, $html);

        $posicion = strpos($html, $nombreAlumno);
        $fragmento = substr($html, $posicion, 700);

        // Nos quedamos solo con el párrafo que contiene al alumno.
        $cierre = strpos($fragmento, '</p>');
        if ($cierre !== false) {
            $fragmento = substr($fragmento, 0, $cierre);
        }

        return $fragmento;
    }
}
