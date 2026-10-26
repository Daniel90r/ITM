<?php

namespace Tests\Feature;

use App\Http\Controllers\Docente\NotasController;
use App\Models\User;
use App\Support\Conducta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConductaEscalaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        // Esquema mínimo efímero; sin migraciones ni conexiones MySQL.
        foreach ([
            'maestros'=>'id INTEGER PRIMARY KEY, user_id INTEGER, activo INTEGER',
            'cursos'=>'id INTEGER PRIMARY KEY, activo INTEGER',
            'materias'=>'id INTEGER PRIMARY KEY, es_opcional INTEGER',
            'detalle_cursos'=>'id INTEGER PRIMARY KEY, curso_id INTEGER, materia_id INTEGER',
            'asignaciones_docentes'=>'id INTEGER PRIMARY KEY, detalle_curso_id INTEGER, maestro_id INTEGER, estado TEXT',
            'alumnos'=>'id INTEGER PRIMARY KEY, nombre TEXT, apellido TEXT',
            'inscripciones'=>'id INTEGER PRIMARY KEY, curso_id INTEGER, alumno_id INTEGER, estado TEXT, activa INTEGER',
            'notas'=>'id INTEGER PRIMARY KEY, alumno_id INTEGER, detalle_curso_id INTEGER, laboratorio REAL, examen_teorico REAL, practica REAL, promedio REAL, conducta TEXT, registrado_por INTEGER, created_at TEXT, updated_at TEXT',
        ] as $tabla=>$columnas) DB::statement('CREATE TABLE '.$tabla.' ('.$columnas.')');
        DB::table('maestros')->insert(['id'=>1,'user_id'=>1,'activo'=>1]);
        DB::table('cursos')->insert(['id'=>1,'activo'=>1]);
        DB::table('materias')->insert(['id'=>1,'es_opcional'=>0]);
        DB::table('detalle_cursos')->insert(['id'=>1,'curso_id'=>1,'materia_id'=>1]);
        DB::table('asignaciones_docentes')->insert(['id'=>1,'detalle_curso_id'=>1,'maestro_id'=>1,'estado'=>'activa']);
        DB::table('alumnos')->insert(['id'=>1,'nombre'=>'Alumno','apellido'=>'Sintético']);
        DB::table('inscripciones')->insert(['id'=>1,'curso_id'=>1,'alumno_id'=>1,'estado'=>'aprobada','activa'=>1]);
        DB::table('notas')->insert(['id'=>1,'alumno_id'=>1,'detalle_curso_id'=>1,'laboratorio'=>8,'examen_teorico'=>7,'practica'=>9,'promedio'=>8,'conducta'=>'P']);
        $usuario = new User(['role'=>'docente']); $usuario->id=1;
        $this->actingAs($usuario);
    }

    private function guardar($codigo): void
    {
        $request = Request::create('/docente/notas', 'POST', ['detalle_curso_id'=>1, 'notas'=>[1=>[
            'laboratorio'=>8,'examen_teorico'=>7,'practica'=>9,'conducta'=>$codigo,
        ]]]);
        app(NotasController::class)->guardar($request);
    }

    public function test_servidor_rechaza_codigos_ajenos_sin_cambiar_el_registro_anterior(): void
    {
        foreach (['P','R','D','MBX','b','Bueno',42,['B']] as $codigo) {
            try { $this->guardar($codigo); $this->fail('Se aceptó una conducta inválida.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('notas.1.conducta', $e->errors()); }
            $this->assertSame('P', DB::table('notas')->value('conducta'));
            $this->assertEquals(8, DB::table('notas')->value('promedio'));
        }
    }

    public function test_servidor_guarda_codigos_validos_y_permite_pendientes_sin_cambiar_notas(): void
    {
        foreach (['B','MB','E',null,''] as $codigo) {
            $this->guardar($codigo);
            $nota = DB::table('notas')->first();
            $this->assertSame($codigo, $nota->conducta);
            $this->assertEquals([8,7,9,8], [$nota->laboratorio,$nota->examen_teorico,$nota->practica,$nota->promedio]);
        }
    }

    public function test_pdf_distingue_conceptos_validos_invalidos_y_pendientes(): void
    {
        $this->assertSame('Bueno', Conducta::etiqueta('B'));
        $this->assertSame('Muy Bueno', Conducta::etiqueta('MB'));
        $this->assertSame('Excelente', Conducta::etiqueta('E'));
        foreach (['P','R','D','??'] as $codigo) $this->assertSame('Sin evaluación válida', Conducta::etiqueta($codigo));
        $this->assertSame('Pendiente de evaluación', Conducta::etiqueta(null));
    }

    public function test_selector_ofrece_nombres_completos_y_no_ofrece_p_como_opcion(): void
    {
        $alumno = new \App\Models\Alumno(['nombre'=>'Alumno','apellido'=>'Sintético']); $alumno->id=1;
        foreach (['P','MB',null] as $codigo) {
            $html = view('docente.partials.conducta-select', ['alumno'=>$alumno,'nota'=>new \App\Models\Nota(['conducta'=>$codigo]), 'errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
            $dom = new \DOMDocument(); @$dom->loadHTML($html); $x = new \DOMXPath($dom);
            $valores = [];
            foreach ($x->query('//option[not(@hidden)]') as $opcion) $valores[] = $opcion->getAttribute('value');
            $this->assertSame(['','B','MB','E'], $valores);
            foreach (['Bueno','Muy Bueno','Excelente'] as $nombre) $this->assertStringContainsString('>'.$nombre.'</option>', $html);
            if ($codigo === 'P') $this->assertStringContainsString('Sin evaluación válida (registro anterior)', $html);
        }
    }
}
