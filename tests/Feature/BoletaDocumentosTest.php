<?php

namespace Tests\Feature;

use App\Models\{Boleta, DetalleBoleta, User};
use App\Services\{BoletaService, DocumentoBoleta};
use App\Support\{Conducta, BoletaConductaPresentacion};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Exceptions\BoletaStorageException;
use Illuminate\Support\Facades\Log;

class BoletaDocumentosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local'); Storage::fake('boletas_oficiales');
        foreach ([
            'alumnos'=>'id INTEGER PRIMARY KEY, nombre TEXT, apellido TEXT, codigo TEXT',
            'cursos'=>'id INTEGER PRIMARY KEY, nombre TEXT, nivel TEXT, seccion TEXT, anio_lectivo INTEGER',
            'materias'=>'id INTEGER PRIMARY KEY, nombre TEXT, codigo TEXT, activa INTEGER, es_opcional INTEGER',
            'detalle_cursos'=>'id INTEGER PRIMARY KEY, curso_id INTEGER, materia_id INTEGER',
            'inscripciones'=>'id INTEGER PRIMARY KEY, alumno_id INTEGER, curso_id INTEGER, estado TEXT',
            'alumno_materia'=>'alumno_id INTEGER, curso_id INTEGER, materia_id INTEGER',
            'notas'=>'id INTEGER PRIMARY KEY, alumno_id INTEGER, detalle_curso_id INTEGER, laboratorio REAL, examen_teorico REAL, practica REAL, promedio REAL, conducta TEXT',
            'directores'=>'id INTEGER PRIMARY KEY, nombre TEXT, fecha_inicio TEXT, fecha_fin TEXT, firma_path TEXT',
            'boletas'=>'id INTEGER PRIMARY KEY AUTOINCREMENT, alumno_id INTEGER, curso_id INTEGER, director_id INTEGER, inscripcion_id INTEGER, fecha_emision TEXT, alumno_nombre TEXT, alumno_apellido TEXT, alumno_codigo TEXT, curso_nombre TEXT, curso_turno TEXT, curso_seccion TEXT, curso_anio_lectivo INTEGER, director_nombre TEXT, director_firma_snapshot_path TEXT, promedio_general INTEGER, concepto_general TEXT, created_at TEXT, updated_at TEXT, UNIQUE(alumno_id,curso_id)',
            'detalles_boletas'=>'id INTEGER PRIMARY KEY, boleta_id INTEGER, detalle_curso_id INTEGER, materia_id INTEGER, materia_nombre TEXT, materia_codigo TEXT, laboratorio REAL, examen_teorico REAL, practica REAL, conducta TEXT, promedio INTEGER, created_at TEXT, updated_at TEXT',
        ] as $tabla=>$columnas) DB::statement('CREATE TABLE '.$tabla.' ('.$columnas.')');
    }

    private function escenario(): void
    {
        DB::table('alumnos')->insert(['id'=>1,'nombre'=>'Alumno','apellido'=>'Sintético','codigo'=>'PRUEBA']);
        DB::table('cursos')->insert(['id'=>1,'nombre'=>'Curso de prueba','nivel'=>'Matutino','seccion'=>'A','anio_lectivo'=>2026]);
        DB::table('inscripciones')->insert(['id'=>1,'alumno_id'=>1,'curso_id'=>1,'estado'=>'aprobada']);
        foreach (['Microsoft Windows','Microsoft Word','Inglés'] as $i=>$nombre) {
            DB::table('materias')->insert(['id'=>$i+1,'nombre'=>$nombre,'codigo'=>'TEST'.$i,'activa'=>1,'es_opcional'=>$i===2]);
            DB::table('detalle_cursos')->insert(['id'=>$i+1,'curso_id'=>1,'materia_id'=>$i+1]);
            DB::table('notas')->insert(['id'=>$i+1,'alumno_id'=>1,'detalle_curso_id'=>$i+1,'laboratorio'=>8,'examen_teorico'=>8,'practica'=>8,'promedio'=>8,'conducta'=>['MB','E','B'][$i]]);
        }
        Storage::disk('local')->put('directores/prueba.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        DB::table('directores')->insert(['id'=>1,'nombre'=>'Directora Histórica','fecha_inicio'=>now()->subYear()->toDateString(),'firma_path'=>'directores/prueba.png']);
    }

    private function emision(bool $archivo = true): Boleta
    {
        $this->escenario();
        // Las pruebas de archivos no necesitan renderizar Dompdf repetidamente.
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('output')->andReturn("%PDF-1.4\nDocumento sintético inmutable");
        $boleta = app(BoletaService::class)->emitir(1, 1);
        Pdf::clearResolvedInstance('dompdf.wrapper');
        $this->app->forgetInstance('dompdf.wrapper');
        if (!$archivo) Storage::disk('boletas_oficiales')->delete($boleta->id.'/oficial.pdf'); // Solo fixture aislado para simular una emisión antigua.
        return $boleta;
    }

    public function test_ejemplos_mediana_y_valores_pendientes(): void
    {
        foreach ([
            [['B'],'Bueno'], [['MB'],'Muy Bueno'], [['E'],'Excelente'],
            [['B','MB'],'Bueno'], [['MB','E'],'Muy Bueno'], [['B','MB','E'],'Muy Bueno'],
            [['E','E','MB'],'Excelente'], [['B','B','MB','E'],'Bueno'],
            [['B','MB','E','E'],'Muy Bueno'], [['E','E','E','E'],'Excelente'],
            [['MB','P'],'Pendiente de evaluación'], [['MB',null],'Pendiente de evaluación'],
            [['MB',''],'Pendiente de evaluación'], [[], 'Pendiente de evaluación'],
        ] as [$codigos,$esperado]) $this->assertSame($esperado, Conducta::consolidar($codigos));
    }

    public function test_grupos_reales_sin_excel_e_ingles_independiente(): void
    {
        $notas = BoletaEstructuraConductaTest::notasSinteticas(['Inglés','Microsoft Word','Microsoft Windows']);
        $notas[0]->conducta='P'; $notas[1]->conducta='E'; $notas[2]->conducta='MB';
        $antes=$notas->map->getAttributes()->all();
        $bloques=BoletaConductaPresentacion::bloques($notas);
        $this->assertSame('Muy Bueno', $bloques[0]['concepto']);
        $this->assertCount(2,$bloques[0]['notas']);
        $this->assertSame('Sin agrupación definida',$bloques[1]['concepto']);
        $this->assertSame($antes,$notas->map->getAttributes()->all());
    }

    public function test_nueva_emision_conserva_pdf_y_descarga_original_sin_datos_actuales(): void
    {
        $boleta=$this->emision();
        $ruta=$boleta->id.'/oficial.pdf';
        $original=Storage::disk('boletas_oficiales')->get($ruta);
        $this->assertCount(2,$boleta->detalles); // Inglés sin asociar no se emite.
        $datos=app(DocumentoBoleta::class)->datosSnapshots($boleta,true);
        $bloques=BoletaConductaPresentacion::bloques($datos['notas']);
        $this->assertSame('Muy Bueno',$bloques[0]['concepto']);
        DB::table('notas')->update(['promedio'=>0,'conducta'=>'P']);
        DB::table('alumnos')->update(['nombre'=>'CAMBIADO']);
        DB::table('cursos')->update(['nombre'=>'CAMBIADO']);
        DB::listen(function ($q) { if (preg_match('/\b(notas|alumnos|cursos|materias)\b/i',$q->sql)) throw new \RuntimeException('Consulta actual en descarga histórica'); });
        $respuesta=app(DocumentoBoleta::class)->descargar($boleta);
        ob_start(); $respuesta->sendContent(); $contenido=ob_get_clean();
        $this->assertSame($original,$contenido);
        $this->assertSame(1,Boleta::count());
    }

    public function test_reconstruccion_solo_snapshots_sin_mediana_y_con_firma_fecha(): void
    {
        $boleta=$this->emision(false);
        DB::table('notas')->update(['promedio'=>0,'conducta'=>'P']);
        DB::table('alumnos')->update(['nombre'=>'ACTUAL']);
        DB::listen(function ($q) { if (preg_match('/\b(notas|alumnos|cursos|materias)\b/i',$q->sql)) throw new \RuntimeException('Consulta actual prohibida'); });
        $datos=app(DocumentoBoleta::class)->datosSnapshots($boleta->fresh());
        $this->assertSame('Alumno',$datos['alumno']->nombre);
        $this->assertSame('MB',$datos['notas'][0]->conducta);
        $this->assertEquals(8,$datos['notas'][0]->promedio);
        $this->assertSame('Directora Histórica',$datos['boletaEmitida']->director_nombre);
        $this->assertSame($boleta->fecha_emision->toDateTimeString(),$datos['fechaBoleta']->toDateTimeString());
        $this->assertStringStartsWith('data:image/png;base64,',$datos['firmaDirector']);
        $html=view('admin.pdf.boleta-historica',$datos)->render();
        $this->assertStringContainsString('Reconstrucción basada en los datos registrados al momento de la emisión. Puede diferir del documento originalmente generado.',$html);
        $this->assertStringContainsString('No registrado en la emisión',$html);
        $this->assertStringNotContainsString('mediana conservadora',$html);
        Storage::disk('boletas_oficiales')->assertMissing($boleta->id.'/oficial.pdf');
    }

    public function test_snapshot_incompleto_no_se_completa_con_notas_actuales(): void
    {
        $boleta=$this->emision(false);
        DB::table('detalles_boletas')->where('boleta_id',$boleta->id)->update(['promedio'=>null]);
        $this->expectException(ValidationException::class);
        app(DocumentoBoleta::class)->datosSnapshots($boleta->fresh());
    }

    public function test_descargar_reconstruccion_no_registra_emisiones_ni_archiva_original(): void
    {
        $boleta=$this->emision(false);
        $antes=DB::table('detalles_boletas')->get()->toJson();
        $usuario=new User(['role'=>'admin']); $usuario->id=1;
        $this->actingAs($usuario)->get('/admin/boletas/emisiones/'.$boleta->id.'/pdf')
            ->assertOk()->assertDownload('boleta-reconstruida-'.$boleta->id.'.pdf');
        $this->assertSame(1,Boleta::count());
        $this->assertSame($antes,DB::table('detalles_boletas')->get()->toJson());
        Storage::disk('boletas_oficiales')->assertMissing($boleta->id.'/oficial.pdf');
    }

    public function test_prohibe_sobrescribir_un_archivo_y_conserva_historico_p(): void
    {
        $boleta=$this->emision();
        DB::table('detalles_boletas')->where('boleta_id',$boleta->id)->update(['conducta'=>'P']);
        $datos=app(DocumentoBoleta::class)->datosSnapshots($boleta->fresh());
        $this->assertSame('P',$datos['notas'][0]->conducta);
        $this->assertStringContainsString('Sin evaluación válida',view('admin.pdf.boleta-historica',$datos)->render());
        $antes=Storage::disk('boletas_oficiales')->get($boleta->id.'/oficial.pdf');
        try { app(DocumentoBoleta::class)->conservar($boleta); $this->fail('Sobrescritura permitida'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('sobrescribir',$e->getMessage()); }
        $this->assertSame($antes,Storage::disk('boletas_oficiales')->get($boleta->id.'/oficial.pdf'));
    }

    public function test_error_de_archivo_revierte_emision_sin_tocar_notas(): void
    {
        $this->escenario();
        $antes=DB::table('notas')->get()->toJson();
        $this->partialMock(DocumentoBoleta::class, fn ($mock) => $mock->shouldReceive('conservar')->once()->andThrow(new \RuntimeException('Fallo de archivo sintético')));
        try { app(BoletaService::class)->emitir(1,1); $this->fail('Se confirmó una emisión sin PDF'); }
        catch (\RuntimeException $e) { $this->assertSame('Fallo de archivo sintético',$e->getMessage()); }
        $this->assertSame(0,Boleta::count());
        $this->assertSame(0,DetalleBoleta::count());
        $this->assertSame($antes,DB::table('notas')->get()->toJson());
        $this->assertSame([],Storage::disk('local')->allFiles('boletas/firmas'));
    }

    public function test_ruta_historica_exige_administrador(): void
    {
        $boleta=$this->emision();
        $url='/admin/boletas/emisiones/'.$boleta->id.'/pdf';
        $this->get($url)->assertRedirect(route('login'));
        $usuario=new User(['role'=>'otro']); $usuario->id=1;
        $this->actingAs($usuario)->get($url)->assertForbidden();
    }

    public function test_emision_real_dompdf_conserva_documento_valido(): void
    {
        $this->escenario();
        $boleta=app(BoletaService::class)->emitir(1,1);
        $bytes=Storage::disk('boletas_oficiales')->get($boleta->id.'/oficial.pdf');
        $this->assertStringStartsWith('%PDF-',$bytes);
        $this->assertGreaterThan(1000,strlen($bytes));
        $this->assertSame(1,Boleta::count());
        $usuario=new User(['role'=>'admin']); $usuario->id=1;
        $this->actingAs($usuario)->get('/admin/boletas/emisiones/'.$boleta->id.'/pdf')->assertOk()->assertDownload('boleta-emision-'.$boleta->id.'.pdf');
        $this->get('/boletas/1/pdf?curso_id=1')->assertOk()->assertDownload('boleta-emision-'.$boleta->id.'.pdf');
    }

    public function test_colision_de_archivos_previos_no_los_altera_ni_registra_emision(): void
    {
        $this->escenario();
        Storage::disk('boletas_oficiales')->put('1/oficial.pdf','ORIGINAL PREEXISTENTE');
        Storage::disk('local')->put('boletas/firmas/1/firma.png','FIRMA PREEXISTENTE');
        try { app(BoletaService::class)->emitir(1,1); $this->fail('Se permitió reutilizar archivos previos'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('No se sobrescribirán',$e->getMessage()); }
        $this->assertSame(0,Boleta::count());
        $this->assertSame('ORIGINAL PREEXISTENTE',Storage::disk('boletas_oficiales')->get('1/oficial.pdf'));
        $this->assertSame('FIRMA PREEXISTENTE',Storage::disk('local')->get('boletas/firmas/1/firma.png'));
    }

    public function test_colision_http_es_controlada_registrada_y_conserva_original(): void
    {
        $this->escenario();
        $antes = DB::table('notas')->get()->toJson();
        Storage::disk('boletas_oficiales')->put('1/oficial.pdf', 'ORIGINAL PRIVADO');
        Log::spy();
        $usuario = new User(['role'=>'admin']); $usuario->id = 1;
        $this->actingAs($usuario)->post('/admin/boletas/emitir', ['alumno_id'=>1,'curso_id'=>1])
            ->assertRedirect(route('admin.boletas.preview', ['alumno'=>1,'curso'=>1]))
            ->assertSessionHasErrors(['emision'=>BoletaStorageException::MENSAJE_USUARIO]);
        Log::shouldHaveReceived('error')->once()->with('boleta.emision_almacenamiento_fallido', [
            'operacion'=>'colision_emision', 'causa'=>BoletaStorageException::class, 'administrador_id'=>1,
        ]);
        $this->assertSame('ORIGINAL PRIVADO', Storage::disk('boletas_oficiales')->get('1/oficial.pdf'));
        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
        $this->assertSame($antes, DB::table('notas')->get()->toJson());
    }

    public function test_directorio_no_escribible_revierte_y_devuelve_mensaje_controlado(): void
    {
        $this->escenario();
        $this->pdfSintetico();
        $disco = Storage::disk('boletas_oficiales');
        $mock = \Mockery::mock($disco);
        $mock->shouldReceive('makeDirectory')->once()->andThrow(
            \League\Flysystem\UnableToCreateDirectory::atLocation('ruta-privada-sintetica')
        );
        Storage::set('boletas_oficiales', $mock);
        $this->comprobarFalloHttp();
        $this->assertSame([], $disco->allFiles());
    }

    public function test_escritura_parcial_se_limpia_y_revierte_la_emision(): void
    {
        $this->escenario();
        $this->pdfSintetico();
        $documento = new class extends DocumentoBoleta {
            private int $intentos = 0;
            protected function escribir($archivo, string $bytes): int|false
            {
                return ++$this->intentos === 1 ? fwrite($archivo, substr($bytes, 0, 5)) : false;
            }
        };
        $this->app->instance(DocumentoBoleta::class, $documento);
        $this->comprobarFalloHttp();
        Storage::disk('boletas_oficiales')->assertMissing('1/oficial.pdf');
    }

    public function test_fallo_de_sincronizacion_antes_del_commit_no_confirma_emision(): void
    {
        $this->escenario();
        $this->pdfSintetico();
        $documento = new class extends DocumentoBoleta {
            protected function sincronizar($archivo): bool { return false; }
        };
        $this->app->instance(DocumentoBoleta::class, $documento);
        $this->comprobarFalloHttp();
        Storage::disk('boletas_oficiales')->assertMissing('1/oficial.pdf');
    }

    public function test_colision_despues_de_comprobar_existencia_no_sobrescribe(): void
    {
        $this->escenario();
        // Simula otro escritor que gana la creación exclusiva durante el render.
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturnUsing(function () {
            Storage::disk('boletas_oficiales')->put('1/oficial.pdf', 'GANADOR CONCURRENTE');
            return '%PDF-1.4 DOCUMENTO PERDEDOR';
        });
        $this->comprobarFalloHttp();
        $this->assertSame('GANADOR CONCURRENTE', Storage::disk('boletas_oficiales')->get('1/oficial.pdf'));
    }

    public function test_error_de_programacion_no_se_disfraza_como_error_de_almacenamiento(): void
    {
        $this->escenario();
        Pdf::shouldReceive('loadView')->andThrow(new \TypeError('Fallo de programación sintético'));
        $this->withoutExceptionHandling();
        $usuario = new User(['role'=>'admin']); $usuario->id = 1;
        try {
            $this->actingAs($usuario)->post('/admin/boletas/emitir', ['alumno_id'=>1,'curso_id'=>1]);
            $this->fail('El error de programación fue ocultado');
        } catch (\TypeError $e) {
            $this->assertSame('Fallo de programación sintético', $e->getMessage());
        }
        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
    }

    private function pdfSintetico(): void
    {
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('output')->andReturn('%PDF-1.4 DOCUMENTO SINTETICO');
    }

    private function comprobarFalloHttp(): void
    {
        $antes = DB::table('notas')->get()->toJson();
        $usuario = new User(['role'=>'admin']); $usuario->id = 1;
        $this->actingAs($usuario)->post('/admin/boletas/emitir', ['alumno_id'=>1,'curso_id'=>1])
            ->assertRedirect()->assertSessionHasErrors(['emision'=>BoletaStorageException::MENSAJE_USUARIO]);
        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
        $this->assertSame($antes, DB::table('notas')->get()->toJson());
        $this->assertSame([], Storage::disk('local')->allFiles('boletas/firmas'));
    }
}
