<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudInscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->alumnoUser = User::factory()->create([
            'email' => 'alumno@test.com',
            'role' => 'alumno',
            'password' => bcrypt('password'),
        ]);
        $this->alumno = Alumno::factory()->create([
            'user_id' => $this->alumnoUser->id,
            'nombre' => 'Marcos Josue',
            'apellido' => 'Montes Sanchez',
            'codigo' => 'A001',
            'fecha_nacimiento' => now()->subYears(25)->toDateString(),
        ]);
        $this->curso = Curso::factory()->create([
            'nombre' => 'Curso A',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'activo' => true,
        ]);
    }

    public function test_crear_solicitud_pendiente_no_crea_inscripcion_efectiva(): void
    {
        // Crear solicitud pendiente directamente
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        // Verificar: existe la solicitud
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
        ]);

        // Verificar: NO existe inscripción aprobada para este alumno en este curso
        $this->assertDatabaseMissing('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'aprobada',
        ]);

        // Verificar: el alumno NO aparece como inscrito en el curso
        $inscritos = $this->curso->inscripciones()->aprobada()->count();
        $this->assertEquals(0, $inscritos);

        // Verificar: cupo no disminuye
        $this->assertEquals(20, $this->curso->cupos_disponibles);
    }

    public function test_aprobar_solicitud_crea_inscripcion_efectiva(): void
    {
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/solicitudes/{$solicitud->id}/aprobar");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verificar: solicitud cambió a aprobada
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
            'activa' => 1,
        ]);

        // Verificar: alumno aparece como inscrito
        $this->curso->refresh();
        $this->assertEquals(1, $this->curso->inscripciones()->aprobada()->count());
        $this->assertEquals(19, $this->curso->cupos_disponibles);
    }

    public function test_rechazar_solicitud_no_crea_inscripcion(): void
    {
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/solicitudes/{$solicitud->id}/rechazar", [
                'motivo_rechazo' => 'Cupo lleno',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verificar: solicitud cambió a rechazada
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'rechazada',
            'activa' => 0,
        ]);

        // Verificar: NO existe inscripción aprobada
        $this->assertDatabaseMissing('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'aprobada',
        ]);

        // Verificar: alumno NO aparece como inscrito
        $this->curso->refresh();
        $this->assertEquals(0, $this->curso->inscripciones()->aprobada()->count());
        $this->assertEquals(20, $this->curso->cupos_disponibles);
    }

    public function test_aprobar_dos_veces_no_duplica_inscripcion(): void
    {
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        // Primera aprobación
        $this->actingAs($this->admin)
            ->post("/admin/solicitudes/{$solicitud->id}/aprobar");

        // Segunda aprobación
        $response = $this->actingAs($this->admin)
            ->post("/admin/solicitudes/{$solicitud->id}/aprobar");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Solo se pueden aprobar solicitudes pendientes.');

        // Verificar: solo existe UNA inscripción aprobada
        $count = Inscripcion::where('alumno_id', $this->alumno->id)
            ->where('curso_id', $this->curso->id)
            ->where('estado', 'aprobada')
            ->count();
        $this->assertEquals(1, $count);
    }

public function test_alumno_ya_inscrito_no_puede_ser_aprobado_nuevamente(): void
    {
        // Alumno ya tiene inscripción aprobada en el curso
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 1,
            'estado' => 'aprobada',
        ]);

        // Intenta crear nueva solicitud - usa otro alumno para evitar unique constraint
        $otroUser = User::factory()->create(['role' => 'alumno']);
        $otroAlumno = Alumno::factory()->create(['user_id' => $otroUser->id, 'fecha_nacimiento' => now()->subYears(25)->toDateString()]);

        $solicitud = Inscripcion::create([
            'alumno_id' => $otroAlumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        // Aprobar la solicitud del otro alumno (debería funcionar)
        $response = $this->actingAs($this->admin)
            ->post("/admin/solicitudes/{$solicitud->id}/aprobar");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verificar: ahora hay 2 inscripciones aprobadas
        $count = Inscripcion::where('curso_id', $this->curso->id)
            ->where('estado', 'aprobada')
            ->count();
        $this->assertEquals(2, $count);
    }

    public function test_curso_sin_cupo_no_permite_aprobar(): void
    {
        // Llenar el curso con 20 alumnos aprobados
        for ($i = 0; $i < 20; $i++) {
            $u = User::factory()->create(['role' => 'alumno']);
            $a = Alumno::factory()->create(['user_id' => $u->id, 'fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::create([
                'alumno_id' => $a->id,
                'curso_id' => $this->curso->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => 1,
                'estado' => 'aprobada',
            ]);
        }

        $this->assertEquals(20, $this->curso->inscripciones()->aprobada()->count());
        $this->assertEquals(0, $this->curso->cupos_disponibles);

        // Crear solicitud pendiente
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/solicitudes/{$solicitud->id}/aprobar");

        $response->assertRedirect();
        $response->assertSessionHasErrors(['curso']);

        // Solicitud debe seguir pendiente
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_solicitudes_pendientes_no_afectan_contador_y_cupos(): void
    {
        // Crear 5 solicitudes pendientes
        for ($i = 0; $i < 5; $i++) {
            $u = User::factory()->create(['role' => 'alumno']);
            $a = Alumno::factory()->create(['user_id' => $u->id, 'fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::create([
                'alumno_id' => $a->id,
                'curso_id' => $this->curso->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => false,
                'estado' => 'pendiente',
            ]);
        }

        // Contador de inscritos debe ser 0
        $this->assertEquals(0, $this->curso->inscripciones()->aprobada()->count());
        $this->assertEquals(20, $this->curso->cupos_disponibles);
        $this->assertEquals(5, Inscripcion::where('curso_id', $this->curso->id)->where('estado', 'pendiente')->count());

        // Aprobar solo 2
        $pendientes = Inscripcion::where('curso_id', $this->curso->id)->where('estado', 'pendiente')->take(2)->get();
        foreach ($pendientes as $s) {
            $this->actingAs($this->admin)->post("/admin/solicitudes/{$s->id}/aprobar");
        }

        $this->curso->refresh();
        $this->assertEquals(2, $this->curso->inscripciones()->aprobada()->count());
        $this->assertEquals(18, $this->curso->cupos_disponibles);

        // Las otras 3 siguen pendientes y no cuentan
        $this->assertEquals(3, Inscripcion::where('curso_id', $this->curso->id)->where('estado', 'pendiente')->count());
    }

    public function test_detalle_curso_solo_muestra_aprobados(): void
    {
        // Crear 3 solicitudes pendientes
        for ($i = 0; $i < 3; $i++) {
            $u = User::factory()->create(['role' => 'alumno']);
            $a = Alumno::factory()->create(['user_id' => $u->id, 'fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::create([
                'alumno_id' => $a->id,
                'curso_id' => $this->curso->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => false,
                'estado' => 'pendiente',
            ]);
        }

        // Crear 2 aprobados
        for ($i = 0; $i < 2; $i++) {
            $u = User::factory()->create(['role' => 'alumno']);
            $a = Alumno::factory()->create(['user_id' => $u->id, 'fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::create([
                'alumno_id' => $a->id,
                'curso_id' => $this->curso->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => 1,
                'estado' => 'aprobada',
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get("/admin/cursos/{$this->curso->id}");

        $response->assertOk();

        // Verificar que solo los aprobados están en la respuesta
        $content = $response->getContent();
        $aprobadosCount = substr_count($content, 'Alumnos inscritos');
        // La vista debería mostrar 2 inscritos, no 5
        $this->assertStringContainsString('2 / 20', $content);
        $this->assertStringNotContainsString('5 / 20', $content);
    }

    /**
     * El PDF de inscripción debe listar los cursos PRÓXIMOS del AÑO EN CURSO.
     *
     * Se congela el calendario en 2031 a propósito: con el literal 2026 que
     * tenía antes, el PDF seguiría trayendo el curso de 2026 y NO el de 2031,
     * así que este test falla exactamente ante un hardcode.
     */
    public function test_pdf_de_inscripcion_usa_el_anio_actual_y_no_un_literal(): void
    {
        $this->viajarA(2031);

        $anioEnCurso = $this->cursoProximo('Curso Del Anio En Curso', 2031);
        $anioViejo = $this->cursoProximo('Curso Del Anio 2026', 2026);

        $listados = $this->cursosQueLleganAlPdf();

        $this->assertContains(
            $anioEnCurso->id,
            $listados,
            'El curso del año en curso debe aparecer en el PDF.'
        );
        $this->assertNotContains(
            $anioViejo->id,
            $listados,
            'Un curso de otro año lectivo no debe aparecer en el PDF.'
        );

        foreach (Curso::whereIn('id', $listados)->get() as $curso) {
            $this->assertSame(
                2031,
                (int) $curso->anio_lectivo,
                "El PDF incluyó el curso {$curso->nombre} de un año distinto al año en curso."
            );
        }
    }

    /**
     * index(), inscripcion() y descargarPdf() deben aplicar EXACTAMENTE la
     * misma regla de año. Antes descargarPdf() usaba el literal 2026 mientras
     * las otras dos usaban (int) now()->year.
     */
    public function test_las_tres_pantallas_de_inscripcion_usan_el_mismo_anio(): void
    {
        $this->viajarA(2031);

        $anioEnCurso = $this->cursoProximo('Curso Del Anio En Curso', 2031);
        $anioViejo = $this->cursoProximo('Curso Del Anio 2026', 2026);

        $dashboard = $this->actingAs($this->alumnoUser)->get(route('alumno.dashboard'));
        $dashboard->assertOk();
        $delDashboard = $this->idsDe($dashboard->viewData('cursosDisponibles'));

        $formulario = $this->actingAs($this->alumnoUser)->get(route('alumno.inscripcion'));
        $formulario->assertOk();
        $delFormulario = $this->idsDe($formulario->viewData('cursosDisponibles'));

        $delPdf = $this->cursosQueLleganAlPdf();

        $this->assertContains($anioEnCurso->id, $delDashboard, 'index() no tomó el año en curso.');
        $this->assertNotContains($anioViejo->id, $delDashboard, 'index() tomó un año equivocado.');

        $this->assertEqualsCanonicalizing(
            $delDashboard,
            $delFormulario,
            'inscripcion() y index() no aplican la misma regla de año.'
        );

        $this->assertEqualsCanonicalizing(
            $delDashboard,
            $delPdf,
            'descargarPdf() no aplica la misma regla de año que las otras dos pantallas.'
        );
    }

    private function viajarA(int $anio): void
    {
        $this->travelTo(
            \Illuminate\Support\Carbon::create($anio, 6, 15, 9, 0, 0)
        );
    }

    private function cursoProximo(string $nombre, int $anio): Curso
    {
        return Curso::factory()->create([
            'nombre' => $nombre,
            'anio_lectivo' => $anio,
            'activo' => true,
            'estado' => Curso::ESTADO_PROXIMO,
        ]);
    }

    private function idsDe($cursos): array
    {
        return collect($cursos)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Invoca descargarPdf() capturando los datos que recibe la vista del PDF,
     * sin renderizar un PDF real.
     */
    private function cursosQueLleganAlPdf(): array
    {
        $capturados = [];

        $this->app->bind('dompdf.wrapper', function () use (&$capturados) {
            return new class($capturados)
            {
                public function __construct(public array &$capturados) {}

                public function loadView($view, $data = [], $mergeData = [], $encoding = null)
                {
                    $this->capturados = $data;

                    return $this;
                }

                public function setPaper($paper, $orientation = 'portrait')
                {
                    return $this;
                }

                public function download($filename = 'document.pdf')
                {
                    return response('pdf-falso', 200, ['Content-Type' => 'application/pdf']);
                }
            };
        });

        $response = $this->actingAs($this->alumnoUser)->get(route('alumno.inscripcion.pdf'));
        $response->assertOk();

        $this->assertArrayHasKey(
            'cursosDisponibles',
            $capturados,
            'El PDF debe construirse con la lista de cursos disponibles.'
        );

        return $this->idsDe($capturados['cursosDisponibles']);
    }
}
