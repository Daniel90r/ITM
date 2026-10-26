<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BoletaIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function maestroData(string $nombre, int $n): array
    {
        return [
            'user_id' => User::factory()->create(['role' => 'admin'])->id,
            'nombre' => $nombre,
            'apellido' => $nombre,
            'codigo' => 'MAE-' . $nombre . '-' . $n,
        ];
    }

    /**
     * Fixture base: un alumno con N inscripciones APROBADAS (una por curso),
     * una materia distinta por curso, su detalle_curso (maestro actual) y una
     * nota del primer curso registrada por otro maestro.
     */
    private function fixture(int $cursosAprobados = 1, array $notaOverrides = []): array
    {
        $alumno = Alumno::factory()->create();

        $maestroActual = Maestro::create($this->maestroData('Actual', 1));
        $maestroRegistra = Maestro::create($this->maestroData('Registra', 2));

        $cursos = Curso::factory()->count($cursosAprobados)->create();

        $detalles = [];
        foreach ($cursos as $i => $c) {
            $materia = Materia::factory()->create(['nombre' => 'Materia Regular A ' . ($i + 1)]);
            $detalles[$c->id] = DetalleCurso::create([
                'curso_id' => $c->id,
                'materia_id' => $materia->id,
                'maestro_id' => $maestroActual->id,
            ]);
        }

        foreach ($cursos as $c) {
            \DB::table('inscripciones')->insert([
                'alumno_id' => $alumno->id,
                'curso_id' => $c->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => true,
                'estado' => 'aprobada',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $curso = $cursos->first();
        $nota = Nota::create(array_merge([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $detalles[$curso->id]->id,
            'laboratorio' => 9,
            'examen_teorico' => 9,
            'practica' => 9,
            'conducta' => 'MB',
            'registrado_por' => $maestroRegistra->id,
        ], $notaOverrides));

        $nota->load('detalleCurso.materia', 'detalleCurso.maestro', 'maestro');

        return [
            'alumno' => $alumno,
            'curso' => $curso,
            'cursos' => $cursos,
            'detalles' => $detalles,
            'nota' => $nota,
            'maestroActual' => $maestroActual,
            'maestroRegistra' => $maestroRegistra,
        ];
    }

    private function pdfHtml(array $viewData): string
    {
        return View::make('admin.pdf.boleta', $viewData)->render();
    }

    #[Test]
    public function test_pdf_no_imprime_conducta_fija_excelente(): void
    {
        $f = $this->fixture(1, ['conducta' => 'MB', 'laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);
        $html = $this->pdfHtml([
            'alumno' => $f['alumno'],
            'curso' => $f['curso'],
            'notas' => collect([$f['nota']]),
            'promedio_general' => 9.0,
            'concepto' => null,
        ]);

        $this->assertStringNotContainsString('Excelente', $html);
    }

    #[Test]
    public function test_pdf_muestra_conducta_registrada_por_materia(): void
    {
        $f = $this->fixture(1, ['conducta' => 'E', 'laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);
        $html = $this->pdfHtml([
            'alumno' => $f['alumno'],
            'curso' => $f['curso'],
            'notas' => collect([$f['nota']]),
            'promedio_general' => 9.0,
            'concepto' => null,
        ]);

        $this->assertStringContainsString('Conceptos de conducta', $html);
        $this->assertSame(1, substr_count($html, 'class="td-concepto-conducta"'),
            'una materia sin correspondencia conserva una celda neutral de concepto');
        $this->assertStringContainsString('>Sin agrupación definida</td>', $html);
        $this->assertStringContainsString('>Excelente</strong>', $html);
    }

    #[Test]
    public function test_detalle_filtra_notas_al_curso_seleccionado(): void
    {
        $f = $this->fixture(2, ['laboratorio' => 8, 'examen_teorico' => 8, 'practica' => 8, 'conducta' => 'B']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', $f['alumno']->id) . '?curso_id=' . $f['curso']->id);

        $response->assertOk();
        $response->assertSee('Materia Regular A 1');
        $response->assertDontSee('Materia Regular A 2');
    }

    #[Test]
    public function test_detalle_distingue_maestro_actual_de_registrado_por(): void
    {
        $f = $this->fixture(1, ['laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', $f['alumno']->id) . '?curso_id=' . $f['curso']->id);

        $response->assertOk();
        $response->assertSee('Maestro actual');
        $response->assertSee('Registrado por');
        $response->assertSee($f['maestroActual']->nombre . ' ' . $f['maestroActual']->apellido);
        $response->assertSee($f['maestroRegistra']->nombre . ' ' . $f['maestroRegistra']->apellido);
    }

    #[Test]
    public function test_detalle_un_solo_curso_asume_sin_mostrar_selector(): void
    {
        $f = $this->fixture(1, ['laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', $f['alumno']->id));

        $response->assertOk();
        $response->assertDontSee('Seleccionar curso / ciclo');
        $response->assertSee('Detalle de Notas');
    }

    #[Test]
    public function test_detalle_con_varios_cursos_abiene_selector(): void
    {
        $f = $this->fixture(2, ['laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', $f['alumno']->id));

        $response->assertOk();
        $response->assertSeeText('Seleccionar curso / ciclo');
        $response->assertSeeText('Aplicar');
        $response->assertSeeText($f['cursos'][0]->nombre);
        $response->assertSeeText($f['cursos'][1]->nombre);
        $response->assertDontSee('Detalle de Notas');
    }

    #[Test]
    public function test_detalle_rechaza_curso_no_aprobado(): void
    {
        $f = $this->fixture(1, ['laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);

        // Marcar la inscripción como rechazada (misma clave alumno+curso).
        \DB::table('inscripciones')
            ->where('alumno_id', $f['alumno']->id)
            ->where('curso_id', $f['curso']->id)
            ->update(['estado' => 'rechazada', 'activa' => false]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', $f['alumno']->id) . '?curso_id=' . $f['curso']->id);

        $response->assertNotFound();
    }

    #[Test]
    public function test_pdf_rechaza_sin_curso_resuelto(): void
    {
        $f = $this->fixture(2, ['laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.pdf', $f['alumno']->id));

        $response->assertNotFound();
    }

    #[Test]
    public function test_no_hay_colision_por_nombre_de_materia_entre_cursos(): void
    {
        // El mismo nombre de materia en dos cursos distintos comparte
        // detalle_curso_id diferente. La boleta PDF debe renderizar UNA fila
        // por cada detalle_curso_id, no colapsarlas por nombre de materia.
        $alumno = Alumno::factory()->create();

        $m1 = Maestro::create($this->maestroData('M1', 1));
        $m2 = Maestro::create($this->maestroData('M2', 2));

        $cursos = Curso::factory()->count(2)->create();
        $materia = Materia::factory()->create(['nombre' => 'Materia Compartida']);

        $dc1 = DetalleCurso::create(['curso_id' => $cursos[0]->id, 'materia_id' => $materia->id, 'maestro_id' => $m1->id]);
        $dc2 = DetalleCurso::create(['curso_id' => $cursos[1]->id, 'materia_id' => $materia->id, 'maestro_id' => $m2->id]);

        foreach ($cursos as $c) {
            \DB::table('inscripciones')->insert([
                'alumno_id' => $alumno->id,
                'curso_id' => $c->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => true,
                'estado' => 'aprobada',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $n1 = Nota::create([
            'alumno_id' => $alumno->id, 'detalle_curso_id' => $dc1->id,
            'laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9,
            'conducta' => 'E', 'registrado_por' => $m1->id,
        ]);
        $n2 = Nota::create([
            'alumno_id' => $alumno->id, 'detalle_curso_id' => $dc2->id,
            'laboratorio' => 4, 'examen_teorico' => 4, 'practica' => 4,
            'conducta' => 'B', 'registrado_por' => $m2->id,
        ]);

        $n1->load('detalleCurso.materia', 'detalleCurso.maestro', 'maestro');
        $n2->load('detalleCurso.materia', 'detalleCurso.maestro', 'maestro');

        $html = $this->pdfHtml([
            'alumno' => $alumno,
            'curso' => $cursos[0],
            'notas' => collect([$n1, $n2]),
            'promedio_general' => 6.5,
            'concepto' => null,
        ]);

        // Una fila de materia por cada detalle_curso_id → no colapsadas.
        $this->assertSame(2, substr_count($html, 'class="td-left td-materia"'),
            'no debe colapsarse una materia por coincidencia de nombre entre cursos');
    }

    #[Test]
    public function test_pdf_filtra_promedio_general_al_curso(): void
    {
        $f = $this->fixture(2, ['laboratorio' => 9, 'examen_teorico' => 9, 'practica' => 9, 'conducta' => 'E']);

        // Nota adicional en el curso 2 con promedio distinto; no debe mezclarse.
        $dc2 = $f['detalles'][$f['cursos'][1]->id];
        Nota::create([
            'alumno_id' => $f['alumno']->id, 'detalle_curso_id' => $dc2->id,
            'laboratorio' => 4, 'examen_teorico' => 4, 'practica' => 4,
            'conducta' => null, 'registrado_por' => $f['maestroRegistra']->id,
        ]);

        $f['nota']->load('detalleCurso.materia', 'detalleCurso.maestro', 'maestro');

        $html = $this->pdfHtml([
            'alumno' => $f['alumno'],
            'curso' => $f['curso'],
            'notas' => collect([$f['nota']]),
            'promedio_general' => 9.0,
            'concepto' => null,
        ]);

        $this->assertStringContainsString('PROMEDIO FINAL', $html);
        $this->assertSame(1, substr_count($html, '>9<'),
            'el promedio general del PDF corresponde solo al curso seleccionado');
    }
}
