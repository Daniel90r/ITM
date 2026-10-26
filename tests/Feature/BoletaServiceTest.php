<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Boleta;
use App\Models\Curso;
use App\Models\DetalleBoleta;
use App\Models\DetalleCurso;
use App\Models\Director;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Nota;
use App\Services\BoletaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BoletaServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoletaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoletaService();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('boletas_oficiales');
        // Esta suite caracteriza snapshots y transacciones con firmas ficticias.
        // El archivo PDF real se verifica separadamente en BoletaDocumentosTest.
        $this->partialMock(\App\Services\DocumentoBoleta::class, function ($mock) {
            $mock->shouldReceive('conservar')->andReturnUsing(fn ($boleta) => $boleta->id.'/oficial.pdf');
        });
    }

    private function crearEscenarioBasico(): array
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        $inscripcion = Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);

        // Crear firma fake en storage
        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        $materia1 = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $materia2 = Materia::factory()->create(['nombre' => 'Materia Regular B', 'es_opcional' => false]);

        $dc1 = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia1->id]);
        $dc2 = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia2->id]);

        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc1->id,
            'laboratorio' => 8.0,
            'examen_teorico' => 8.0,
            'practica' => 8.0,
            'promedio' => 8,
            'conducta' => 'B',
        ]);

        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc2->id,
            'laboratorio' => 10.0,
            'examen_teorico' => 10.0,
            'practica' => 10.0,
            'promedio' => 10,
            'conducta' => 'E',
        ]);

        return [
            'alumno' => $alumno,
            'curso' => $curso,
            'inscripcion' => $inscripcion,
            'director' => $director,
            'materia1' => $materia1,
            'materia2' => $materia2,
            'dc1' => $dc1,
            'dc2' => $dc2,
        ];
    }

    public function test_emision_valida_crea_boleta_y_detalles(): void
    {
        $data = $this->crearEscenarioBasico();

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $this->assertInstanceOf(Boleta::class, $boleta);
        $this->assertEquals($data['alumno']->id, $boleta->alumno_id);
        $this->assertEquals($data['curso']->id, $boleta->curso_id);
        $this->assertEquals($data['director']->id, $boleta->director_id);
        $this->assertEquals($data['inscripcion']->id, $boleta->inscripcion_id);

        $detalles = DetalleBoleta::where('boleta_id', $boleta->id)->get();
        $this->assertCount(2, $detalles);
    }

    public function test_snapshots_principales_correctos(): void
    {
        $data = $this->crearEscenarioBasico();

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        // Snapshots alumno
        $this->assertEquals($data['alumno']->nombre, $boleta->alumno_nombre);
        $this->assertEquals($data['alumno']->apellido, $boleta->alumno_apellido);
        $this->assertEquals($data['alumno']->codigo, $boleta->alumno_codigo);

        // Snapshots curso
        $this->assertEquals($data['curso']->nombre, $boleta->curso_nombre);
        $this->assertEquals($data['curso']->nivel, $boleta->curso_turno);
        $this->assertEquals($data['curso']->seccion, $boleta->curso_seccion);
        $this->assertEquals($data['curso']->anio_lectivo, $boleta->curso_anio_lectivo);

        // Snapshots director
        $this->assertEquals($data['director']->nombre, $boleta->director_nombre);

        // Promedio y concepto
        // (8 + 10) / 2 = 9 → E
        $this->assertEquals(9, $boleta->promedio_general);
        $this->assertEquals('E', $boleta->concepto_general);
    }

    public function test_snapshot_fisico_firma_existe_y_distinto(): void
    {
        $data = $this->crearEscenarioBasico();

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $this->assertTrue(
            Storage::disk('local')->exists($boleta->director_firma_snapshot_path),
            'El snapshot histórico de la firma debe existir en storage.'
        );

        $this->assertNotEquals(
            $data['director']->firma_path,
            $boleta->director_firma_snapshot_path,
            'La ruta del snapshot debe ser diferente a la firma original del Director.'
        );

        // Verificar contenido copiado
        $contenidoOriginal = Storage::disk('local')->get($data['director']->firma_path);
        $contenidoSnapshot = Storage::disk('local')->get($boleta->director_firma_snapshot_path);
        $this->assertEquals($contenidoOriginal, $contenidoSnapshot);

        // PRIVACIDAD: snapshot existe en local, NO en public
        Storage::disk('local')->assertExists($boleta->director_firma_snapshot_path);
        Storage::disk('public')->assertMissing($boleta->director_firma_snapshot_path);
        Storage::disk('public')->assertMissing($data['director']->firma_path);
    }

    public function test_inscripcion_pendiente_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'pendiente',
        ]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('El alumno no tiene una inscripción aprobada en este curso.');

        $this->service->emitir($alumno->id, $curso->id);
    }

    public function test_inscripcion_rechazada_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'rechazada',
        ]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('El alumno no tiene una inscripción aprobada en este curso.');

        $this->service->emitir($alumno->id, $curso->id);
    }

    public function test_sin_director_vigente_bloquea(): void
    {
        $data = $this->crearEscenarioBasico();

        // Eliminar director vigente
        $data['director']->delete();

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('No existe un Director vigente para la fecha de emisión.');

        $this->service->emitir($data['alumno']->id, $data['curso']->id);
    }

    public function test_director_sin_firma_valida_bloquea(): void
    {
        $data = $this->crearEscenarioBasico();

        // Director sin firma_path
        $data['director']->update(['firma_path' => null]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('El Director vigente no tiene una firma válida registrada.');

        $this->service->emitir($data['alumno']->id, $data['curso']->id);
    }

    public function test_nota_con_laboratorio_null_bloquea(): void
    {
        $data = $this->crearEscenarioBasico();

        // Corromper una nota: laboratorio null
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['laboratorio' => null]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('faltan calificaciones en: Materia Regular A');

        $this->service->emitir($data['alumno']->id, $data['curso']->id);
    }

    public function test_conducta_null_permite_emitir(): void
    {
        $data = $this->crearEscenarioBasico();

        // Establecer conducta null
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['conducta' => null]);

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $detalle = DetalleBoleta::where('boleta_id', $boleta->id)
            ->where('materia_id', $data['materia1']->id)
            ->first();

        $this->assertNull($detalle->conducta);
    }

    public function test_materia_opcional_no_asignada_no_aparece(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake');

        // Materia regular
        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Materia opcional NO asignada al alumno
        $materiaOpcional = Materia::factory()->create(['nombre' => 'Arte', 'es_opcional' => true]);
        $dcOpcional = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaOpcional->id]);
        // NOTA: NO crear alumno_materia para esta materia

        // Pero SÍ crear una nota accidental para la opcional no asignada
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcOpcional->id,
            'laboratorio' => 10.0, 'examen_teorico' => 10.0, 'practica' => 10.0,
            'promedio' => 10, 'conducta' => 'E',
        ]);

        $boleta = $this->service->emitir($alumno->id, $curso->id);

        $detalles = DetalleBoleta::where('boleta_id', $boleta->id)->get();
        $this->assertCount(1, $detalles, 'Solo debe aparecer la materia regular');
        $this->assertEquals('Materia Regular A', $detalles->first()->materia_nombre);
    }

    public function test_nota_de_otro_curso_no_aparece_ni_altera_promedio(): void
    {
        $data = $this->crearEscenarioBasico();

        // Crear otro curso y nota para el mismo alumno
        $otroCurso = Curso::factory()->create();
        $otraMateria = Materia::factory()->create(['nombre' => 'Otra', 'es_opcional' => false]);
        $otroDc = DetalleCurso::factory()->create(['curso_id' => $otroCurso->id, 'materia_id' => $otraMateria->id]);
        Nota::factory()->create([
            'alumno_id' => $data['alumno']->id,
            'detalle_curso_id' => $otroDc->id,
            'laboratorio' => 1.0, 'examen_teorico' => 1.0, 'practica' => 1.0,
            'promedio' => 1, 'conducta' => 'D',
        ]);

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        // Solo 2 detalles del curso original
        $detalles = DetalleBoleta::where('boleta_id', $boleta->id)->get();
        $this->assertCount(2, $detalles);

        // Promedio debe ser 9 (solo del curso original), no alterado por la nota del otro curso
        $this->assertEquals(9, $boleta->promedio_general);
    }

    public function test_segunda_emision_bloquea_y_deja_solo_una(): void
    {
        $data = $this->crearEscenarioBasico();

        // Primera emisión
        $boleta1 = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        // Segunda emisión debe fallar
        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('Esta boleta ya fue emitida.');

        try {
            $this->service->emitir($data['alumno']->id, $data['curso']->id);
        } catch (\App\Exceptions\BoletaEmissionException $e) {
            throw $e;
        }

        // Verificar que solo existe 1 boleta
        $boletas = Boleta::where('alumno_id', $data['alumno']->id)
            ->where('curso_id', $data['curso']->id)
            ->get();
        $this->assertCount(1, $boletas);

        // Verificar que los snapshots no cambiaron
        $this->assertEquals($boleta1->promedio_general, $boletas->first()->promedio_general);
        $this->assertEquals($boleta1->director_firma_snapshot_path, $boletas->first()->director_firma_snapshot_path);
    }

    public function test_directores_solapados_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        // Director A: vigente desde hace 30 días, hasta dentro de 30 días
        $directorA = Director::factory()->create([
            'fecha_inicio' => now()->subDays(30)->toDateString(),
            'fecha_fin' => now()->addDays(30)->toDateString(),
            'activo' => true,
            'firma_path' => 'directores/firmas/directorA.png',
        ]);
        Storage::disk('local')->put('directores/firmas/directorA.png', 'fake');

        // Director B: vigente desde hace 10 días, sin fecha fin
        $directorB = Director::factory()->create([
            'fecha_inicio' => now()->subDays(10)->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/directorB.png',
        ]);
        Storage::disk('local')->put('directores/firmas/directorB.png', 'fake');

$materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('Existen múltiples Directores vigentes para la fecha de emisión. Corrija los períodos.');

        $this->service->emitir($alumno->id, $curso->id);

        // Verificar que no se creó nada
        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_director_inicia_hoy_valido(): void
    {
        Carbon::setTestNow(now()->startOfDay());

        try {
            $alumno = Alumno::factory()->create();
            $curso = Curso::factory()->create();

            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $curso->id,
                'estado' => 'aprobada',
            ]);

            // Director que empieza HOY
            $director = Director::factory()->create([
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => null,
                'activo' => true,
                'firma_path' => 'directores/firmas/director.png',
            ]);
            Storage::disk('local')->put('directores/firmas/director.png', 'fake');

            $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
            $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
            Nota::factory()->create([
                'alumno_id' => $alumno->id,
                'detalle_curso_id' => $dc->id,
                'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
                'promedio' => 8, 'conducta' => 'B',
            ]);

            $boleta = $this->service->emitir($alumno->id, $curso->id);

            $this->assertInstanceOf(Boleta::class, $boleta);
            $this->assertEquals(1, Boleta::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_director_termina_hoy_valido(): void
    {
        Carbon::setTestNow(now()->startOfDay());

        try {
            $alumno = Alumno::factory()->create();
            $curso = Curso::factory()->create();

            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $curso->id,
                'estado' => 'aprobada',
            ]);

            // Director que termina HOY
            $director = Director::factory()->create([
                'fecha_inicio' => now()->subMonth()->toDateString(),
                'fecha_fin' => now()->toDateString(),
                'activo' => true,
                'firma_path' => 'directores/firmas/director.png',
            ]);
            Storage::disk('local')->put('directores/firmas/director.png', 'fake');

            $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
            $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
            Nota::factory()->create([
                'alumno_id' => $alumno->id,
                'detalle_curso_id' => $dc->id,
                'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
                'promedio' => 8, 'conducta' => 'B',
            ]);

            $boleta = $this->service->emitir($alumno->id, $curso->id);

            $this->assertInstanceOf(Boleta::class, $boleta);
            $this->assertEquals(1, Boleta::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_director_fuera_periodo_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        // Director que terminó AYER
        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->subDay()->toDateString(),
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake');

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('No existe un Director vigente para la fecha de emisión.');

        $this->service->emitir($alumno->id, $curso->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_firma_registrada_pero_archivo_inexistente_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        // Director con firma_path que NO existe en storage
        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/no-existe.png',
        ]);
        // NO crear el archivo en Storage

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('El Director vigente no tiene una firma válida registrada.');

        $this->service->emitir($alumno->id, $curso->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_examen_teorico_null_bloquea(): void
    {
        $data = $this->crearEscenarioBasico();

        // Corromper una nota: examen_teorico null
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['examen_teorico' => null]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('faltan calificaciones en: Materia Regular A');

        $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_practica_null_bloquea(): void
    {
        $data = $this->crearEscenarioBasico();

        // Corromper una nota: practica null
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['practica' => null]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('faltan calificaciones en: Materia Regular A');

        $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_boleta_no_requiere_sos(): void
    {
        $data = $this->crearEscenarioBasico();

        // Verify that notes don't have SOS column and emission works
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();

        $this->assertNull($nota->sos ?? null, 'SOS column should not exist or be null');

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $this->assertNotNull($boleta);
        $this->assertEquals(1, Boleta::count());
        $this->assertEquals(2, DetalleBoleta::count());
    }

    public function test_promedio_null_bloquea(): void
    {
        $data = $this->crearEscenarioBasico();

        // Crear Nota válida primero, luego forzar promedio = null via Query Builder
        // para evitar que el modelo lo recalcule automáticamente en saving()
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        DB::table('notas')
            ->where('id', $nota->id)
            ->update(['promedio' => null]);

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('faltan calificaciones en: Materia Regular A');

        $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_conducta_legacy_p_preservada(): void
    {
        $data = $this->crearEscenarioBasico();

        // Establecer conducta legacy 'P'
        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['conducta' => 'P']);

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $detalle = DetalleBoleta::where('boleta_id', $boleta->id)
            ->where('materia_id', $data['materia1']->id)
            ->first();

        $this->assertEquals('P', $detalle->conducta, 'La conducta legacy P debe preservarse exactamente.');
    }

    public function test_conducta_b_preservada(): void
    {
        $data = $this->crearEscenarioBasico();

        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['conducta' => 'B']);

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $detalle = DetalleBoleta::where('boleta_id', $boleta->id)
            ->where('materia_id', $data['materia1']->id)
            ->first();

        $this->assertEquals('B', $detalle->conducta);
    }

    public function test_conducta_mb_preservada(): void
    {
        $data = $this->crearEscenarioBasico();

        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['conducta' => 'MB']);

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $detalle = DetalleBoleta::where('boleta_id', $boleta->id)
            ->where('materia_id', $data['materia1']->id)
            ->first();

        $this->assertEquals('MB', $detalle->conducta);
    }

    public function test_conducta_e_preservada(): void
    {
        $data = $this->crearEscenarioBasico();

        $nota = Nota::where('detalle_curso_id', $data['dc1']->id)
            ->where('alumno_id', $data['alumno']->id)
            ->first();
        $nota->update(['conducta' => 'E']);

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        $detalle = DetalleBoleta::where('boleta_id', $boleta->id)
            ->where('materia_id', $data['materia1']->id)
            ->first();

        $this->assertEquals('E', $detalle->conducta);
    }

    public function test_materia_opcional_asignada_se_incluye(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake');

        // Materia regular
        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Materia opcional ASIGNADA al alumno
        $materiaOpcional = Materia::factory()->create(['nombre' => 'Arte', 'es_opcional' => true]);
        $dcOpcional = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaOpcional->id]);

        // Registrar en alumno_materia
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $materiaOpcional->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Nota completa para la opcional
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcOpcional->id,
            'laboratorio' => 10.0, 'examen_teorico' => 10.0, 'practica' => 10.0,
            'promedio' => 10, 'conducta' => 'E',
        ]);

        $boleta = $this->service->emitir($alumno->id, $curso->id);

        $detalles = DetalleBoleta::where('boleta_id', $boleta->id)->get();
        $this->assertCount(2, $detalles, 'Deben aparecer la materia regular y la opcional asignada');

        $nombres = $detalles->pluck('materia_nombre')->sort()->values()->toArray();
        $this->assertEquals(['Arte', 'Materia Regular A'], $nombres);
    }

    public function test_curso_sin_materias_esperadas_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake');

        // Curso SIN DetalleCurso (o sin materias activas)
        // No crear ninguna materia ni DetalleCurso para este curso

        $this->expectException(\App\Exceptions\BoletaEmissionException::class);
        $this->expectExceptionMessage('No hay materias asignadas al alumno en este curso.');

        $this->service->emitir($alumno->id, $curso->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    public function test_ingles_asignado_se_incluye(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            // tomara_ingles puede ser false para demostrar que alumno_materia decide
            'tomara_ingles' => false,
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake');

        // Materia regular
        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Inglés como materia opcional
        $ingles = Materia::factory()->create(['nombre' => 'Inglés', 'es_opcional' => true, 'codigo' => 'ING']);
        $dcIngles = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $ingles->id]);

        // Registrar en alumno_materia (fuente de verdad para inclusión)
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $ingles->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Nota completa para Inglés
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcIngles->id,
            'laboratorio' => 9.0, 'examen_teorico' => 9.0, 'practica' => 9.0,
            'promedio' => 9, 'conducta' => 'MB',
        ]);

        $boleta = $this->service->emitir($alumno->id, $curso->id);

        $detalles = DetalleBoleta::where('boleta_id', $boleta->id)->get();
        $this->assertCount(2, $detalles, 'Deben aparecer Materia Regular A e Inglés');

        $nombres = $detalles->pluck('materia_nombre')->sort()->values()->toArray();
        $this->assertEquals(['Inglés', 'Materia Regular A'], $nombres);

        // Verificar datos de Inglés en el snapshot
        $detalleIngles = $detalles->firstWhere('materia_nombre', 'Inglés');
        $this->assertNotNull($detalleIngles);
        $this->assertEquals('ING', $detalleIngles->materia_codigo);
        $this->assertEquals(9, $detalleIngles->promedio);
        $this->assertEquals('MB', $detalleIngles->conducta);
    }

    public function test_ingles_no_asignado_no_aparece(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'tomara_ingles' => true, // inscripción sugiere que tomará inglés, pero alumno_materia NO
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake');

        // Materia regular
        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Inglés opcional SIN asignar al alumno
        $ingles = Materia::factory()->create(['nombre' => 'Inglés', 'es_opcional' => true]);
        $dcIngles = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $ingles->id]);
        // NO crear alumno_materia para Inglés

        // Nota accidental para Inglés (no debe afectar)
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcIngles->id,
            'laboratorio' => 10.0, 'examen_teorico' => 10.0, 'practica' => 10.0,
            'promedio' => 10, 'conducta' => 'E',
        ]);

        $boleta = $this->service->emitir($alumno->id, $curso->id);

        $detalles = DetalleBoleta::where('boleta_id', $boleta->id)->get();
        $this->assertCount(1, $detalles, 'Solo debe aparecer Materia Regular A, Inglés excluido por no estar en alumno_materia');
        $this->assertEquals('Materia Regular A', $detalles->first()->materia_nombre);

        // Promedio debe ser solo de Materia Regular A (8), no alterado por la nota de Inglés
        $this->assertEquals(8, $boleta->promedio_general);
        $this->assertEquals('MB', $boleta->concepto_general);
    }

    public function test_firma_historica_inmutable(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        // Firma original con contenido conocido
        Storage::disk('local')->put('directores/firmas/director.png', 'FIRMA_ORIGINAL');

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Emitir boleta
        $boleta = $this->service->emitir($alumno->id, $curso->id);

        $firmaSnapshotPath = $boleta->director_firma_snapshot_path;

        // Verificar que snapshot existe y tiene contenido original
        $this->assertTrue(Storage::disk('local')->exists($firmaSnapshotPath));
        $contenidoHistoricoAntes = Storage::disk('local')->get($firmaSnapshotPath);
        $this->assertEquals('FIRMA_ORIGINAL', $contenidoHistoricoAntes);

        // Sobrescribir SOLO la firma original del Director
        Storage::disk('local')->put('directores/firmas/director.png', 'FIRMA_NUEVA');

        // Verificar que firma original cambió
        $this->assertEquals('FIRMA_NUEVA', Storage::disk('local')->get('directores/firmas/director.png'));

        // Verificar que snapshot histórico NO cambió
        $this->assertTrue(Storage::disk('local')->exists($firmaSnapshotPath));
        $contenidoHistoricoDespues = Storage::disk('local')->get($firmaSnapshotPath);
        $this->assertEquals('FIRMA_ORIGINAL', $contenidoHistoricoDespues);
        $this->assertEquals($contenidoHistoricoAntes, $contenidoHistoricoDespues);
    }

    public function test_es_duplicado_boleta_especifico_mysql(): void
    {
        $service = new \App\Services\BoletaService();
        $method = new \ReflectionMethod($service, 'esDuplicadoBoleta');
        $method->setAccessible(true);

        // MySQL válido: error 1062 + uk_boleta_alumno_curso
        $eMysqlValido = new \Illuminate\Database\QueryException(
            'mysql',
            'INSERT INTO boletas ...',
            [],
            new \Exception('Duplicate entry \'1-1\' for key \'uk_boleta_alumno_curso\'')
        );
        // Simular errorInfo array como MySQL lo provee
        $eMysqlValido->errorInfo = ['23000', 1062, 'Duplicate entry \'1-1\' for key \'uk_boleta_alumno_curso\''];

        $this->assertTrue($method->invoke($service, $eMysqlValido));

        // MySQL otro UNIQUE: error 1062 pero OTRO índice
        $eMysqlOtro = new \Illuminate\Database\QueryException(
            'mysql',
            'INSERT INTO boletas ...',
            [],
            new \Exception('Duplicate entry \'test\' for key \'otro_indice\'')
        );
        $eMysqlOtro->errorInfo = ['23000', 1062, 'Duplicate entry \'test\' for key \'otro_indice\''];

        $this->assertFalse($method->invoke($service, $eMysqlOtro));
    }

    public function test_es_duplicado_boleta_especifico_sqlite(): void
    {
        $service = new \App\Services\BoletaService();
        $method = new \ReflectionMethod($service, 'esDuplicadoBoleta');
        $method->setAccessible(true);

        // SQLite válido: UNIQUE constraint failed con columnas correctas
        $eSqliteValido = new \Illuminate\Database\QueryException(
            'sqlite',
            'INSERT INTO boletas ...',
            [],
            new \Exception('UNIQUE constraint failed: boletas.alumno_id, boletas.curso_id')
        );

        $this->assertTrue($method->invoke($service, $eSqliteValido));

        // SQLite otro UNIQUE: columnas diferentes
        $eSqliteOtro = new \Illuminate\Database\QueryException(
            'sqlite',
            'INSERT INTO detalles_boletas ...',
            [],
            new \Exception('UNIQUE constraint failed: detalles_boletas.boleta_id, detalles_boletas.detalle_curso_id')
        );

        $this->assertFalse($method->invoke($service, $eSqliteOtro));
    }

    public function test_otra_query_exception_no_confundida(): void
    {
        $service = new \App\Services\BoletaService();
        $method = new \ReflectionMethod($service, 'esDuplicadoBoleta');
        $method->setAccessible(true);

        // QueryException genérica (p.ej. FK violation)
        $eFk = new \Illuminate\Database\QueryException(
            'mysql',
            'INSERT INTO boletas ...',
            [],
            new \Exception('Cannot add or update a child row: a foreign key constraint fails')
        );
        $eFk->errorInfo = ['23000', 1452, 'Cannot add or update a child row'];

        $this->assertFalse($method->invoke($service, $eFk));

        // QueryException sin errorInfo típico
        $eGenerica = new \Illuminate\Database\QueryException(
            'mysql',
            'SELECT ...',
            [],
            new \Exception('Error genérico')
        );
        $eGenerica->errorInfo = ['HY000', 999, 'Error genérico'];

        $this->assertFalse($method->invoke($service, $eGenerica));
    }

    public function test_cleanup_post_copia_si_falla_bd(): void
    {
        $data = $this->crearEscenarioBasico();

        // Crear firma original conocida
        Storage::disk('local')->put($data['director']->firma_path, 'FIRMA_ORIGINAL');

        // Verificar que la firma original existe
        $this->assertTrue(Storage::disk('local')->exists($data['director']->firma_path));

        // Forzar fallo de BD DESPUÉS de la copia: eliminar tabla detalles_boletas
        // El servicio copia la firma ANTES de crear los detalles (orden confirmado en BoletaService)
        \Illuminate\Support\Facades\Schema::dropIfExists('detalles_boletas');

        // Emitir debe fallar con excepción de infraestructura (NO "Esta boleta ya fue emitida")
        $this->expectException(\Illuminate\Database\QueryException::class);

        try {
            $this->service->emitir($data['alumno']->id, $data['curso']->id);
        } catch (\Illuminate\Database\QueryException $e) {
            throw $e;
        }

        // ASSERTS DEL CLEANUP:
        // 1. No hay boletas (rollback de transacción)
        $this->assertEquals(0, Boleta::count());

        // 2. Firma original del Director sigue existiendo
        $this->assertTrue(Storage::disk('local')->exists($data['director']->firma_path));

        // 3. Contenido de firma original no cambió
        $this->assertEquals('FIRMA_ORIGINAL', Storage::disk('local')->get($data['director']->firma_path));

        // 4. NO queda snapshot histórico en boletas/firmas/ correspondiente a la emisión fallida
        // (El cleanup del catch elimina el snapshot si se copió)
        $archivosFirmas = Storage::disk('local')->allFiles('boletas/firmas');
        $this->assertEmpty($archivosFirmas);
    }

    public function test_snapshot_path_no_es_temporal_en_exito(): void
    {
        $data = $this->crearEscenarioBasico();

        Storage::disk('local')->put($data['director']->firma_path, 'FIRMA_ORIGINAL');

        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        // director_firma_snapshot_path NO contiene /temp/
        $this->assertStringNotContainsString('/temp/', $boleta->director_firma_snapshot_path);

        // El snapshot existe en storage
        $this->assertTrue(Storage::disk('local')->exists($boleta->director_firma_snapshot_path));
    }

    public function test_fallo_copia_firma_retorna_false(): void
    {
        $data = $this->crearEscenarioBasico();

        // Crear firma original
        Storage::disk('local')->put($data['director']->firma_path, 'FIRMA_ORIGINAL');

        // Mock del Facade Storage para que disk('local') devuelva un mock
        // que simula: exists() = true, copy() = false
        $diskMock = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $diskMock
            ->shouldReceive('exists')
            ->with($data['director']->firma_path)
            ->andReturn(true);
        $diskMock
            ->shouldReceive('copy')
            ->once()
            ->with($data['director']->firma_path, Mockery::type('string'))
            ->andReturn(false);
        $diskMock
            ->shouldReceive('delete')
            ->never();

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($diskMock);
        $diskMock->shouldReceive('exists')
            ->with(Mockery::on(fn ($ruta) => str_starts_with($ruta, 'boletas/firmas/')))
            ->andReturn(false);
        $archivoMock = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $archivoMock->shouldReceive('exists')->andReturn(false);
        Storage::shouldReceive('disk')->with('boletas_oficiales')->andReturn($archivoMock);

        // Emitir debe fallar con RuntimeException (NO BoletaEmissionException::yaEmitida())
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo crear el snapshot histórico de la firma del Director.');

        try {
            $this->service->emitir($data['alumno']->id, $data['curso']->id);
        } catch (\RuntimeException $e) {
            throw $e;
        }

        // ASSERTS: rollback completo
        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
    }

    // ==================== TESTS PARA PREVISUALIZAR() ====================

    public function test_previsualizar_no_escribe_en_bd(): void
    {
        $data = $this->crearEscenarioBasico();

        $preview = $this->service->previsualizar($data['alumno']->id, $data['curso']->id);

        $this->assertEquals(0, Boleta::count());
        $this->assertEquals(0, DetalleBoleta::count());
        $this->assertIsArray($preview);
        $this->assertArrayHasKey('filas', $preview);
        $this->assertArrayHasKey('puede_emitir_academicamente', $preview);
        $this->assertArrayHasKey('promedio_general', $preview);
        $this->assertArrayHasKey('concepto_general', $preview);
    }

    public function test_previsualizar_incluye_materia_regular(): void
    {
        $data = $this->crearEscenarioBasico();

        $preview = $this->service->previsualizar($data['alumno']->id, $data['curso']->id);

        $filas = $preview['filas'];
        $this->assertCount(2, $filas);
        $nombres = collect($filas)->pluck('materia.nombre')->sort()->values()->toArray();
        $this->assertEquals(['Materia Regular A', 'Materia Regular B'], $nombres);
    }

    public function test_previsualizar_incluye_opcional_asignada_via_alumno_materia(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Materia opcional ASIGNADA via alumno_materia
        $materiaOpcional = Materia::factory()->create(['nombre' => 'Arte', 'es_opcional' => true]);
        $dcOpcional = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaOpcional->id]);
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $materiaOpcional->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcOpcional->id,
            'laboratorio' => 10.0, 'examen_teorico' => 10.0, 'practica' => 10.0,
            'promedio' => 10, 'conducta' => 'E',
        ]);

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(2, $preview['filas']);
        $nombres = collect($preview['filas'])->pluck('materia.nombre')->sort()->values()->toArray();
        $this->assertEquals(['Arte', 'Materia Regular A'], $nombres);
    }

    public function test_previsualizar_excluye_opcional_no_asignada_aun_con_nota(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        // Materia opcional NO asignada (sin alumno_materia) pero con Nota accidental
        $materiaOpcional = Materia::factory()->create(['nombre' => 'Arte', 'es_opcional' => true]);
        $dcOpcional = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaOpcional->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcOpcional->id,
            'laboratorio' => 10.0, 'examen_teorico' => 10.0, 'practica' => 10.0,
            'promedio' => 10, 'conducta' => 'E',
        ]);

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(1, $preview['filas']);
        $this->assertEquals('Materia Regular A', $preview['filas'][0]['materia']->nombre);
    }
public function test_previsualizar_ingles_via_alumno_materia_no_tomara_ingles(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'tomara_ingles' => false,
        ]);

        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B',
        ]);

        $ingles = Materia::factory()->create(['nombre' => 'Inglés', 'es_opcional' => true, 'codigo' => 'ING']);
        $dcIngles = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $ingles->id]);

        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $ingles->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcIngles->id,
            'laboratorio' => 9.0, 'examen_teorico' => 9.0, 'practica' => 9.0,
            'promedio' => 9, 'conducta' => 'MB',
        ]);

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(2, $preview['filas']);
        $nombres = collect($preview['filas'])->pluck('materia.nombre')->sort()->values()->toArray();
        $this->assertEquals(['Inglés', 'Materia Regular A'], $nombres);
    }

    public function test_previsualizar_materia_sin_nota_aparece_como_pendiente(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $materia = Materia::factory()->create(['nombre' => 'Historia', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
        // NO crear Nota para esta materia

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(1, $preview['filas']);
        $fila = $preview['filas'][0];
        $this->assertEquals('Historia', $fila['materia']->nombre);
        $this->assertNull($fila['nota']);
        $this->assertFalse($fila['completa']);
        $this->assertNull($fila['concepto']);
    }

    public function test_previsualizar_nota_parcial_incompleta_puede_emitir_false(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

        // Nota con componentes faltantes (laboratorio null)
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => null,
            'examen_teorico' => 8.0,
            'practica' => 8.0,
            'promedio' => null,
            'conducta' => 'B',
        ]);

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(1, $preview['filas']);
        $fila = $preview['filas'][0];
        $this->assertFalse($fila['completa']);
        $this->assertFalse($preview['puede_emitir_academicamente']);
    }

    public function test_previsualizar_valores_cero_son_validos_no_nulos(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

        // Nota con todos los componentes = 0 (válido, no null)
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 0.0,
            'examen_teorico' => 0.0,
            'practica' => 0.0,
            'promedio' => 0,
            'conducta' => 'D',
        ]);

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(1, $preview['filas']);
        $fila = $preview['filas'][0];
        $this->assertTrue($fila['completa']);
        $this->assertEquals('D', $fila['concepto']);
        $this->assertEquals(0, $fila['nota']->promedio);
    }

    public function test_previsualizar_promedio_sin_sos_calcula_correctamente(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

        // Nota con laboratorio=9, examen_teorico=8, practica=7 → promedio esperado = 8 (sin SOS)
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 9.0,
            'examen_teorico' => 8.0,
            'practica' => 7.0,
            'promedio' => 8,
            'conducta' => 'B',
        ]);

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(1, $preview['filas']);
        $fila = $preview['filas'][0];
        $this->assertEquals(8, $fila['nota']->promedio, 'Promedio debe ser 8 sin SOS: (9+8+7)/3 = 8');
    }

    public function test_previsualizar_concepto_fronteras_9_8_7_6_5(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
        ]);

        $casos = [
            ['promedio' => 9, 'esperado' => 'E'],
            ['promedio' => 8, 'esperado' => 'MB'],
            ['promedio' => 7, 'esperado' => 'B'],
            ['promedio' => 6, 'esperado' => 'R'],
            ['promedio' => 5, 'esperado' => 'D'],
        ];

        foreach ($casos as $caso) {
            $materia = Materia::factory()->create(['nombre' => 'Materia'.$caso['promedio'], 'es_opcional' => false]);
            $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);

            Nota::factory()->create([
                'alumno_id' => $alumno->id,
                'detalle_curso_id' => $dc->id,
                'laboratorio' => $caso['promedio'],
                'examen_teorico' => $caso['promedio'],
                'practica' => $caso['promedio'],
                'promedio' => $caso['promedio'],
                'conducta' => 'B',
            ]);
        }

        $preview = $this->service->previsualizar($alumno->id, $curso->id);

        $this->assertCount(5, $preview['filas']);

        foreach ($preview['filas'] as $fila) {
            $promedio = $fila['nota']->promedio;
            $esperado = match($promedio) {
                9 => 'E',
                8 => 'MB',
                7 => 'B',
                6 => 'R',
                5 => 'D',
            };
            $this->assertEquals($esperado, $fila['concepto'], "Concepto incorrecto para promedio $promedio");
        }
    }

    public function test_previsualizar_vs_emitir_datos_identicos(): void
    {
        $data = $this->crearEscenarioBasico();

        // Preview
        $preview = $this->service->previsualizar($data['alumno']->id, $data['curso']->id);

        // Emitir (requiere Director)
        $boleta = $this->service->emitir($data['alumno']->id, $data['curso']->id);

        // Comparar datos académicos generales
        $this->assertEquals($preview['promedio_general'], $boleta->promedio_general);
        $this->assertEquals($preview['concepto_general'], $boleta->concepto_general);
        $this->assertEquals($preview['puede_emitir_academicamente'], true);

        // Comparar materias presentes
        $previewNombres = collect($preview['filas'])->pluck('materia.nombre')->sort()->values()->toArray();
        $emitNombres = DetalleBoleta::where('boleta_id', $boleta->id)
            ->get()
            ->pluck('materia_nombre')
            ->sort()
            ->values()
            ->toArray();
        $this->assertEquals($previewNombres, $emitNombres);

        // Comparar promedios por materia
        foreach ($preview['filas'] as $pf) {
            $materiaNombre = $pf['materia']->nombre;
            $detalleEmitido = DetalleBoleta::where('boleta_id', $boleta->id)
                ->where('materia_nombre', $materiaNombre)
                ->first();
            $this->assertNotNull($detalleEmitido);
            $this->assertEquals($pf['nota']->promedio, $detalleEmitido->promedio);
        }
    }
}
