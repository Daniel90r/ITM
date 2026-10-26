<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BoletaController;
use App\Models\{AsignacionDocente, Maestro};
use App\Services\BoletaService;
use App\Support\BoletaPdfPresentacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoletaPdfProduccionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    private function escenario(): void
    {
        // Esquema mínimo DESECHABLE: no ejecuta migraciones ni toca la estructura real.
        foreach ([
            'alumnos' => 'id INTEGER PRIMARY KEY, nombre TEXT, apellido TEXT, codigo TEXT',
            'cursos' => 'id INTEGER PRIMARY KEY, nombre TEXT',
            'inscripciones' => 'id INTEGER PRIMARY KEY, alumno_id INTEGER, curso_id INTEGER, estado TEXT',
            'materias' => 'id INTEGER PRIMARY KEY, nombre TEXT, activa INTEGER, es_opcional INTEGER',
            'detalle_cursos' => 'id INTEGER PRIMARY KEY, curso_id INTEGER, materia_id INTEGER, maestro_id INTEGER',
            'notas' => 'id INTEGER PRIMARY KEY, alumno_id INTEGER, detalle_curso_id INTEGER, laboratorio REAL, examen_teorico REAL, practica REAL, promedio REAL, conducta TEXT',
            'alumno_materia' => 'alumno_id INTEGER, curso_id INTEGER, materia_id INTEGER',
            'asignaciones_docentes' => 'id INTEGER PRIMARY KEY, detalle_curso_id INTEGER, maestro_id INTEGER, estado TEXT, fecha_inicio TEXT, fecha_fin TEXT',
            'boletas' => 'id INTEGER PRIMARY KEY, alumno_id INTEGER, curso_id INTEGER',
        ] as $tabla => $columnas) DB::statement('CREATE TABLE '.$tabla.' ('.$columnas.')');
        DB::table('alumnos')->insert(['id'=>1, 'nombre'=>'Alumno', 'apellido'=>'Sintético', 'codigo'=>'PRUEBA']);
        DB::table('cursos')->insert(['id'=>1, 'nombre'=>'Curso sintético']);
        DB::table('inscripciones')->insert(['id'=>1, 'alumno_id'=>1, 'curso_id'=>1, 'estado'=>'aprobada']);
        foreach (['Microsoft Windows', 'Microsoft Word', 'Inglés'] as $i => $nombre) {
            DB::table('materias')->insert(['id'=>$i+1, 'nombre'=>$nombre, 'activa'=>1, 'es_opcional'=>$i===2]);
            DB::table('detalle_cursos')->insert(['id'=>$i+1, 'curso_id'=>1, 'materia_id'=>$i+1]);
            DB::table('notas')->insert(['id'=>$i+1, 'alumno_id'=>1, 'detalle_curso_id'=>$i+1, 'laboratorio'=>8, 'examen_teorico'=>8, 'practica'=>8, 'promedio'=>8, 'conducta'=>'P']);
        }
    }

    private function capturarPdf(): array
    {
        $capturados = [];
        $documento = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $documento->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $documento->shouldReceive('download')->once()->andReturn(response('PDF sintético'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($vista, $datos) use (&$capturados) {
            $this->assertSame('admin.pdf.boleta', $vista);
            $capturados = $datos;
            return true;
        })->andReturn($documento);
        $this->app->instance('request', Request::create('/admin/boletas/1/pdf', 'GET', ['curso_id'=>1]));
        app(BoletaController::class)->generarPdf(1);
        return $capturados;
    }

    public function test_pdf_reutiliza_elegibilidad_y_no_incluye_ingles_sin_asociacion(): void
    {
        $this->escenario();
        $antes = DB::table('notas')->get()->toJson();
        $datos = $this->capturarPdf();
        $this->assertCount(2, $datos['notas']);
        $this->assertEquals(8, $datos['promedio_general']);
        $html = view('admin.pdf.boleta', $datos)->render();
        $this->assertStringNotContainsString('Inglés', $html);
        $this->assertStringContainsString('Microsoft Windows', $html);
        $this->assertStringContainsString('>Sin evaluación válida</strong>', $html);
        $this->assertStringNotContainsString('>P</strong>', $html);
        $this->assertSame($antes, DB::table('notas')->get()->toJson());
        $this->assertSame(0, DB::table('boletas')->count());
        $this->assertTrue($datos['calificacionesCompletas']);
        $this->assertStringContainsString('Estado académico del curso:', $html);
        $this->assertStringContainsString('Aprobado', $html);
    }

    public function test_pdf_incluye_opcional_asociada_y_materias_reales_sin_nota(): void
    {
        $this->escenario();
        DB::table('alumno_materia')->insert(['alumno_id'=>1, 'curso_id'=>1, 'materia_id'=>3]);
        DB::table('notas')->where('id', 2)->delete(); // Solo fixture SQLite en memoria.
        $datos = $this->capturarPdf();
        $this->assertCount(3, $datos['notas']);
        $this->assertNull($datos['notas'][1]->promedio);
        $html = view('admin.pdf.boleta', $datos)->render();
        $this->assertStringContainsString('Inglés', $html);
        $this->assertStringContainsString('Sin nota', $html);
        $this->assertStringNotContainsString('dos años', $html);
        $this->assertStringNotContainsString('Santos David Rodas', $html);
        $this->assertStringNotContainsString('carrera técnica', $html);
        $this->assertStringNotContainsString('Concepto por definir', $html);
        $this->assertFalse($datos['calificacionesCompletas']);
        $this->assertStringContainsString('Pendiente de completar calificaciones', $html);
        $this->assertStringNotContainsString('Aprobado', $html);
    }

    public function test_docentes_salen_de_asignaciones_vigentes_no_de_un_nombre_fijo(): void
    {
        $this->travelTo(now()->startOfDay());
        $detalle = BoletaEstructuraConductaTest::notasSinteticas(['Microsoft Windows'])->first()->detalleCurso;
        $asignaciones = collect();
        foreach ([['activa', -10, 'Ana'], ['activa', -5, 'Luis'], ['programada', 10, 'Futuro'], ['finalizada', -20, 'Anterior']] as [$estado, $dias, $nombre]) {
            $asignacion = new AsignacionDocente(['estado'=>$estado, 'fecha_inicio'=>now()->addDays($dias)]);
            $asignacion->setRelation('maestro', new Maestro(['nombre'=>$nombre, 'apellido'=>'Sintético']));
            $asignaciones->push($asignacion);
        }
        $detalle->setRelation('asignacionesDocentes', $asignaciones);
        $this->assertSame('Ana Sintético / Luis Sintético', BoletaPdfPresentacion::docentesActuales($detalle));
        $this->travelBack();
    }

    public function test_promedio_existente_no_declara_aprobado_si_falta_un_componente(): void
    {
        $this->escenario();
        DB::table('notas')->where('id', 1)->update(['examen_teorico'=>null]);
        $datos = $this->capturarPdf();
        $this->assertEquals(8, $datos['promedio_general']);
        $this->assertFalse($datos['calificacionesCompletas']);
        $html = view('admin.pdf.boleta', $datos)->render();
        $this->assertStringContainsString('Pendiente de completar calificaciones', $html);
        $this->assertStringNotContainsString('Aprobado', $html);
    }

    public function test_cero_es_calificacion_real_y_no_se_presenta_como_ausencia(): void
    {
        $notas = BoletaEstructuraConductaTest::notasSinteticas(['Microsoft Windows']);
        $notas->first()->promedio = 0;
        $datos = BoletaEstructuraConductaTest::datos($notas);
        $datos['promedio_general'] = 0;
        $datos['estado_academico'] = 'Reprobado';
        $html = view('admin.pdf.boleta', $datos)->render();
        $this->assertStringContainsString('>0</td>', $html);
        $this->assertStringContainsString('>D</td>', $html);
        $this->assertStringContainsString('Reprobado', $html);
        $datos['promedio_general'] = null;
        $this->assertStringContainsString('Sin calificaciones', view('admin.pdf.boleta', $datos)->render());
    }
}
