<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InscripcionReintentoTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ConEncargadoOficial;

    private User $alumnoUser;
    private User $admin;
    private Alumno $alumno;
    private Curso $cursoA;
    private Curso $cursoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->alumnoUser = User::factory()->create(['role' => 'alumno']);
        $this->alumno = Alumno::factory()->create([
            'user_id' => $this->alumnoUser->id,
            'codigo' => 'ALU-REINT-001',
        ]);
        $this->prepararEncargadoOficial($this->alumno);

        $this->cursoA = Curso::factory()->create([
            'nombre' => 'Curso A',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
            'estado' => Curso::ESTADO_PROXIMO,
        ]);

        $this->cursoB = Curso::factory()->create([
            'nombre' => 'Curso B',
            'nivel' => 'Matutino',
            'seccion' => 'B',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
            'estado' => Curso::ESTADO_PROXIMO,
        ]);
    }

    // ── Visibilidad del rechazo ──────────────────────────────────────────

    #[Test]
    public function test_alumno_puede_ver_estado_rechazada(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'motivo_rechazo' => 'Documento incompleto',
        ]);

        $response = $this->actingAs($this->alumnoUser)
            ->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('Solicitud rechazada');
    }

    #[Test]
    public function test_alumno_puede_ver_motivo_rechazo(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'motivo_rechazo' => 'Falta constancia de estudio',
        ]);

        $response = $this->actingAs($this->alumnoUser)
            ->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('Falta constancia de estudio');
    }

    #[Test]
    public function test_rechazada_muestra_formulario_para_volver_a_solicitar(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'motivo_rechazo' => 'Cupo lleno',
        ]);

        $response = $this->actingAs($this->alumnoUser)
            ->get(route('alumno.inscripcion'));

        $response->assertOk();
        $response->assertSee('name="curso_id"', false);
        $response->assertSee('Enviar solicitud de inscripción');
    }

    // ── Reintento mismo curso ────────────────────────────────────────────

    #[Test]
    public function test_reintento_mismo_curso_reutiliza_misma_fila(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'motivo_rechazo' => 'Motivo anterior',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), [
                'curso_id' => $this->cursoA->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'pendiente',
            'activa' => false,
            'motivo_rechazo' => null,
        ]);
    }

    #[Test]
    public function test_reintento_establece_activa_false(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), [
                'curso_id' => $this->cursoA->id,
            ]);

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'activa' => false,
        ]);
    }

    #[Test]
    public function test_reintento_no_autoaprueba(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), [
                'curso_id' => $this->cursoA->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseMissing('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'estado' => 'aprobada',
        ]);
    }

    // ── Casos de bloqueo ────────────────────────────────────────────────

    #[Test]
    public function test_rechazada_en_a_con_pendiente_en_b_bloquea_reapertura(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoB->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $this->cursoA->id])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'estado' => 'rechazada',
        ]);
    }

    #[Test]
    public function test_rechazada_en_a_con_aprobada_en_b_bloquea_reapertura(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoB->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $this->cursoA->id])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'estado' => 'rechazada',
        ]);
    }

    #[Test]
    public function test_rechazada_en_a_pero_curso_ya_no_admite_inscripcion_bloquea_reapertura(): void
    {
        $cursoCerrado = Curso::factory()->create([
            'nombre' => 'Cerrado',
            'nivel' => 'Matutino',
            'seccion' => 'C',
            'anio_lectivo' => (int) now()->year,
            'activo' => true,
            'estado' => Curso::ESTADO_ACTIVO,
        ]);

        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $cursoCerrado->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $cursoCerrado->id])
            ->assertSessionHasErrors('curso_id');

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $cursoCerrado->id,
            'estado' => 'rechazada',
        ]);
    }

    // ── Reintento con PDF (enviarSolicitud) ──────────────────────────────

    #[Test]
    public function test_reintento_reemplaza_documento_path_con_pdf_nuevo(): void
    {
        Storage::fake('public');

        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'documento_path' => 'solicitudes/old.pdf',
        ]);

        $this->app->bind('dompdf.wrapper', function () {
            return new class([]) {
                public function loadView($view, $data = [], $mergeData = [], $encoding = null) { return $this; }
                public function setPaper($paper, $orientation = null) { return $this; }
                public function output() { return 'pdf-nuevo-contenido'; }
            };
        });

        $response = $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id' => $this->cursoA->id,
                'tomara_ingles' => false,
                'alumno_dui' => '12345678-9',
                'encargado_nombre' => 'Encargado', 'encargado_apellido' => 'Test',
                'encargado_parentesco' => 'Padre',
                'encargado_dui' => '12345678-9',
                'encargado_telefono' => '7777-8888',
                'firma_alumno' => 'firma-alumno',
                'firma_encargado' => 'firma-encargado',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'pendiente',
            'motivo_rechazo' => null,
        ]);

        $nuevo = $inscripcion->fresh()->documento_path;
        $this->assertStringStartsWith('solicitudes/solicitud-ALU-REINT-001-', $nuevo);
        $this->assertStringContainsString('.pdf', $nuevo);
    }

    #[Test]
    public function test_pdf_anterior_se_elimina_despues_del_exito(): void
    {
        Storage::fake('public');

        $oldPdf = 'solicitudes/old-' . $this->alumno->codigo . '.pdf';
        Storage::disk('public')->put($oldPdf, 'old-pdf');

        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'documento_path' => $oldPdf,
        ]);

        $this->app->bind('dompdf.wrapper', function () {
            return new class([]) {
                public function loadView($view, $data = [], $mergeData = [], $encoding = null) { return $this; }
                public function setPaper($paper, $orientation = null) { return $this; }
                public function output() { return 'pdf-nuevo'; }
            };
        });

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id' => $this->cursoA->id,
                'tomara_ingles' => false,
                'alumno_dui' => '12345678-9',
                'encargado_nombre' => 'Encargado', 'encargado_apellido' => 'Test',
                'encargado_parentesco' => 'Padre',
                'encargado_dui' => '12345678-9',
                'encargado_telefono' => '7777-8888',
                'firma_alumno' => 'firma-alumno',
                'firma_encargado' => 'firma-encargado',
            ]);

        $this->assertDatabaseMissing('inscripciones', [
            'id' => $inscripcion->id,
            'documento_path' => $oldPdf,
        ]);

        Storage::disk('public')->assertMissing($oldPdf);
    }

    // ── Rollback: fallo después de generar PDF ───────────────────────────

    #[Test]
    public function test_rollback_enviarSolicitud_elimina_pdf_nuevo_y_conserva_anterior(): void
    {
        Storage::fake('public');

        $oldPdf = 'solicitudes/old.pdf';
        Storage::disk('public')->put($oldPdf, 'old-pdf');

        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'documento_path' => $oldPdf,
        ]);

        $GLOBALS['pdf_output_content'] = null;

        $this->app->bind('dompdf.wrapper', function () {
            return new class([]) {
                public function loadView($view, $data = [], $mergeData = [], $encoding = null) { return $this; }
                public function setPaper($paper, $orientation = null) { return $this; }
                public function output()
                {
                    Inscripcion::create([
                        'alumno_id' => 1,
                        'curso_id' => 2,
                        'fecha_inscripcion' => now()->toDateString(),
                        'activa' => false,
                        'estado' => 'pendiente',
                    ]);

                    $GLOBALS['pdf_output_content'] = '%PDF-test';

                    return '%PDF-test';
                }
            };
        });

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id' => $this->cursoA->id,
                'tomara_ingles' => false,
                'alumno_dui' => '12345678-9',
                'encargado_nombre' => 'Encargado', 'encargado_apellido' => 'Test',
                'encargado_parentesco' => 'Padre',
                'encargado_dui' => '12345678-9',
                'encargado_telefono' => '7777-8888',
                'firma_alumno' => 'firma-alumno',
                'firma_encargado' => 'firma-encargado',
            ]);

        // Evidencia directa del flujo: GENERADO → ESCRITO → ELIMINADO.
        $this->assertNotNull($GLOBALS['pdf_output_content'], 'Pdf::output() debe ejecutarse y retornar contenido');

        // Evidencia directa del flujo: GENERADO → ESCRITO → ELIMINADO.
        $this->assertNotNull($GLOBALS['pdf_output_content'], 'Pdf::output() debe ejecutarse y retornar contenido');

        $archivos = Storage::disk('public')->allFiles('solicitudes');
        $pdfNuevoExiste = false;
        foreach ($archivos as $archivo) {
            if (Storage::disk('public')->get($archivo) === $GLOBALS['pdf_output_content']) {
                $pdfNuevoExiste = true;
                break;
            }
        }

        $this->assertFalse($pdfNuevoExiste, 'PDF nuevo debe ser eliminado por el catch');
        Storage::disk('public')->assertExists($oldPdf);

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'rechazada',
            'documento_path' => $oldPdf,
        ]);

        $this->assertSame(1, Inscripcion::where('alumno_id', $this->alumno->id)
            ->where('curso_id', $this->cursoA->id)
            ->count());
    }

    // ── subirDocumento con rechazada ─────────────────────────────────────

    #[Test]
    public function test_subir_documento_con_rechazada_reutiliza_misma_fila(): void
    {
        Storage::fake('public');

        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'documento_path' => 'solicitudes/old.pdf',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.subir'), [
                'curso_id' => $this->cursoA->id,
                'documento' => UploadedFile::fake()->create('doc.pdf', 500),
                'tomara_ingles' => false,
            ]);

        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'pendiente',
            'motivo_rechazo' => null,
        ]);
    }

    #[Test]
    public function test_rollback_subirDocumento_elimina_archivo_nuevo_y_conserva_anterior(): void
    {
        Storage::fake('public');

        $oldPdf = 'solicitudes/old.pdf';
        Storage::disk('public')->put($oldPdf, 'old-pdf');

        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'documento_path' => $oldPdf,
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'itm_upload_');
        file_put_contents(
            $tmpFile,
            "%PDF-1.4\n% ITM test PDF\n1 0 obj\n<<>>\nendobj\n%%EOF"
        );

        $probe = (object) [
            'capturedPath' => null,
            'existedAfterStore' => false,
        ];

        $alumnoId = $this->alumno->id;
        $cursoBId = $this->cursoB->id;

        $uploadedFile = new class($tmpFile, $alumnoId, $cursoBId, $probe) extends \Illuminate\Http\UploadedFile {
            private int $alumnoId;
            private int $cursoBId;
            private object $probe;

            public function __construct(
                string $tmpFile,
                int $alumnoId,
                int $cursoBId,
                object $probe
            ) {
                parent::__construct(
                    $tmpFile,
                    'documento.pdf',
                    'application/pdf',
                    UPLOAD_ERR_OK,
                    true
                );

                $this->alumnoId = $alumnoId;
                $this->cursoBId = $cursoBId;
                $this->probe = $probe;
            }

            public function store($path = '', $options = [])
            {
                $storedPath = parent::store($path, $options);

                $disk = is_string($options)
                    ? $options
                    : ($options['disk'] ?? 'public');

                $this->probe->capturedPath = $storedPath;

                $this->probe->existedAfterStore =
                    is_string($storedPath)
                    && \Illuminate\Support\Facades\Storage::disk($disk)
                        ->exists($storedPath);

                \App\Models\Inscripcion::create([
                    'alumno_id' => $this->alumnoId,
                    'curso_id' => $this->cursoBId,
                    'fecha_inscripcion' => now()->toDateString(),
                    'activa' => false,
                    'estado' => 'pendiente',
                ]);

                return $storedPath;
            }
        };

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.subir'), [
                'curso_id' => $this->cursoA->id,
                'documento' => $uploadedFile,
                'tomara_ingles' => false,
            ]);

        $this->assertNotNull(
            $probe->capturedPath,
            'El UploadedFile controlado debe capturar el path almacenado.'
        );

        $this->assertTrue(
            $probe->existedAfterStore,
            'El archivo nuevo debe existir inmediatamente después de store().'
        );

        Storage::disk('public')->assertMissing($probe->capturedPath);
        Storage::disk('public')->assertExists($oldPdf);

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'estado' => 'rechazada',
            'documento_path' => $oldPdf,
        ]);

        $this->assertSame(
            1,
            Inscripcion::where('alumno_id', $this->alumno->id)
                ->where('curso_id', $this->cursoA->id)
                ->count()
        );

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoB->id,
            'estado' => 'pendiente',
        ]);

        @unlink($tmpFile);
    }

    // ── Casos de permitido / bloqueo ────────────────────────────────────

    #[Test]
    public function test_rechazada_en_curso_a_permite_solicitar_curso_b(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $this->cursoB->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoB->id,
            'estado' => 'pendiente',
        ]);
    }

    #[Test]
    public function test_pendiente_en_curso_a_bloquea_curso_b(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $this->cursoB->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 1);
    }

    #[Test]
    public function test_aprobada_en_curso_a_bloquea_curso_b(): void
    {
        Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $this->cursoB->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 1);
    }

    // ── Admin reabrir() sigue vigente ────────────────────────────────────

    #[Test]
    public function test_admin_reabrir_sigue_funcionando(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->cursoA->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'rechazada',
            'motivo_rechazo' => 'Motivo',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.reabrir', $inscripcion->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'pendiente',
            'motivo_rechazo' => null,
        ]);
    }
}
