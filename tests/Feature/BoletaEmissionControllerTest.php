<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Boleta;
use App\Models\Curso;
use App\Models\DetalleBoleta;
use App\Models\DetalleCurso;
use App\Models\Director;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use App\Exceptions\BoletaEmissionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BoletaEmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function crearEscenarioValido(): array
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);

        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        $materia1 = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $materia2 = Materia::factory()->create(['nombre' => 'Materia Regular B', 'es_opcional' => false, 'activa' => true]);

        $maestro = Maestro::factory()->create();

        $dc1 = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia1->id, 'maestro_id' => $maestro->id]);
        $dc2 = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia2->id, 'maestro_id' => $maestro->id]);

        $maestroRegistra = Maestro::factory()->create();

        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc1->id,
            'laboratorio' => 8.0,
            'examen_teorico' => 8.0,
            'practica' => 8.0,
            'promedio' => 8,
            'conducta' => 'B',
            'registrado_por' => $maestroRegistra->id,
        ]);

        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc2->id,
            'laboratorio' => 9.0,
            'examen_teorico' => 9.0,
            'practica' => 9.0,
            'promedio' => 9,
            'conducta' => 'MB',
            'registrado_por' => $maestroRegistra->id,
        ]);

        return [
            'alumno' => $alumno,
            'curso' => $curso,
            'director' => $director,
            'materias' => [$materia1, $materia2],
            'detalles' => [$dc1, $dc2],
        ];
    }

    #[Test]
    public function test_admin_puede_emitir_boleta_valida(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response->assertRedirect(route('admin.boletas.preview', [
            'alumno' => $escenario['alumno']->id,
            'curso'  => $escenario['curso']->id,
        ]));
        $response->assertSessionHas('success', 'Boleta emitida correctamente.');

        $this->assertSame(1, Boleta::count());
        $this->assertSame(2, DetalleBoleta::count());

        $boleta = Boleta::first();
        $this->assertSame($escenario['alumno']->id, $boleta->alumno_id);
        $this->assertSame($escenario['curso']->id, $boleta->curso_id);
        $this->assertSame($escenario['director']->id, $boleta->director_id);
        $this->assertNotNull($boleta->director_firma_snapshot_path);
        $this->assertTrue(Storage::disk('local')->exists($boleta->director_firma_snapshot_path));
    }

    #[Test]
    public function test_post_no_crea_datos_vivos_nuevos(): void
    {
        $escenario = $this->crearEscenarioValido();

        $countsAntes = [
            'notas' => Nota::count(),
            'detalle_cursos' => DetalleCurso::count(),
            'alumnos' => Alumno::count(),
            'inscripciones' => Inscripcion::count(),
            'directores' => Director::count(),
            'materias' => Materia::count(),
            'maestros' => Maestro::count(),
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $this->assertSame($countsAntes['notas'], Nota::count());
        $this->assertSame($countsAntes['detalle_cursos'], DetalleCurso::count());
        $this->assertSame($countsAntes['alumnos'], Alumno::count());
        $this->assertSame($countsAntes['inscripciones'], Inscripcion::count());
        $this->assertSame($countsAntes['directores'], Director::count());
        $this->assertSame($countsAntes['materias'], Materia::count());
        $this->assertSame($countsAntes['maestros'], Maestro::count());
    }

    #[Test]
    public function test_inscripcion_no_aprobada_rechaza_emision(): void
    {
        $escenario = $this->crearEscenarioValido();

        Inscripcion::where('alumno_id', $escenario['alumno']->id)
            ->where('curso_id', $escenario['curso']->id)
            ->update(['estado' => 'rechazada', 'activa' => false]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response->assertRedirect(route('admin.boletas.preview', [
            'alumno' => $escenario['alumno']->id,
            'curso'  => $escenario['curso']->id,
        ]));
        $response->assertSessionHasErrors('emision');
        $this->assertStringContainsString('inscripción aprobada', session('errors')->get('emision')[0]);

        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
    }

    #[Test]
    public function test_calificaciones_pendientes_rechaza_emision(): void
    {
        $escenario = $this->crearEscenarioValido();

        $materiaNueva = Materia::factory()->create(['nombre' => 'Historia', 'es_opcional' => false, 'activa' => true]);
        $dcNuevo = DetalleCurso::factory()->create(['curso_id' => $escenario['curso']->id, 'materia_id' => $materiaNueva->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response->assertRedirect(route('admin.boletas.preview', [
            'alumno' => $escenario['alumno']->id,
            'curso'  => $escenario['curso']->id,
        ]));
        $response->assertSessionHasErrors('emision');
        $this->assertStringContainsString('calificaciones', session('errors')->get('emision')[0]);

        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
    }

    #[Test]
    public function test_segunda_emision_mismo_alumno_curso_rechaza(): void
    {
        $escenario = $this->crearEscenarioValido();

        $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response->assertRedirect(route('admin.boletas.preview', [
            'alumno' => $escenario['alumno']->id,
            'curso'  => $escenario['curso']->id,
        ]));
        $response->assertSessionHasErrors('emision');
        $this->assertStringContainsString('ya fue emitida', session('errors')->get('emision')[0]);

        $this->assertSame(1, Boleta::count());
    }

    #[Test]
    public function test_validacion_request_falla_sin_alumno_id(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'curso_id' => $escenario['curso']->id,
            ]);

        $response->assertSessionHasErrors('alumno_id');
    }

    #[Test]
    public function test_validacion_request_falla_sin_curso_id(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
            ]);

        $response->assertSessionHasErrors('curso_id');
    }

    #[Test]
    public function test_validacion_request_falla_alumno_inexistente(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => 999999,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response->assertSessionHasErrors('alumno_id');
    }

    #[Test]
    public function test_validacion_request_falla_curso_inexistente(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => 999999,
            ]);

        $response->assertSessionHasErrors('curso_id');
    }

    #[Test]
    public function test_admin_accede_a_emitir(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function test_docente_recibe_403_en_emitir(): void
    {
        $docente = User::factory()->create(['role' => 'docente']);

        $response = $this->actingAs($docente)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => 1,
                'curso_id'  => 1,
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function test_alumno_recibe_403_en_emitir(): void
    {
        $alumnoUser = User::factory()->create(['role' => 'alumno']);

        $response = $this->actingAs($alumnoUser)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => 1,
                'curso_id'  => 1,
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function test_guest_redirigido_a_login_en_emitir(): void
    {
        $response = $this->post(route('admin.boletas.emitir'), [
            'alumno_id' => 1,
            'curso_id'  => 1,
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function test_get_no_emite_boleta(): void
    {
        $escenario = $this->crearEscenarioValido();

        $this->actingAs($this->admin)
            ->get(route('admin.boletas'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]))
            ->assertOk();

        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
    }

    #[Test]
    public function test_preview_explicito_valido(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
    }

    #[Test]
    public function test_preview_curso_no_aprobado_404(): void
    {
        $escenario = $this->crearEscenarioValido();

        Inscripcion::where('alumno_id', $escenario['alumno']->id)
            ->where('curso_id', $escenario['curso']->id)
            ->update(['estado' => 'rechazada', 'activa' => false]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertNotFound();
        $this->assertSame(0, Boleta::count());
        $this->assertSame(0, DetalleBoleta::count());
    }

    #[Test]
    public function test_preview_autorizacion_admin(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
    }

    #[Test]
    public function test_preview_autorizacion_docente_403(): void
    {
        $docente = User::factory()->create(['role' => 'docente']);
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $response = $this->actingAs($docente)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function test_preview_autorizacion_alumno_403(): void
    {
        $alumnoUser = User::factory()->create(['role' => 'alumno']);
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $response = $this->actingAs($alumnoUser)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function test_preview_autorizacion_guest_redirect_login(): void
    {
        $response = $this->get(route('admin.boletas.preview', [
            'alumno' => 1,
            'curso'  => 1,
        ]));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function test_preview_formulario_emitir_visible(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        $response->assertSee('Emitir boleta');
        $response->assertSee(route('admin.boletas.emitir'));
        $response->assertSee('alumno_id');
        $response->assertSee('curso_id');
    }

    #[Test]
    public function test_preview_despues_de_emitir_muestra_estado_emitida(): void
    {
        $escenario = $this->crearEscenarioValido();

        $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $this->assertSame(1, Boleta::count());

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        $response->assertSee('Boleta emitida');
        $response->assertDontSee('Emitir boleta');
    }

    #[Test]
    public function test_preview_redirect_error_mantiene_errores(): void
    {
        $escenario = $this->crearEscenarioValido();

        Inscripcion::where('alumno_id', $escenario['alumno']->id)
            ->where('curso_id', $escenario['curso']->id)
            ->update(['estado' => 'rechazada', 'activa' => false]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.boletas.emitir'), [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]);

        $response->assertRedirect(route('admin.boletas.preview', [
            'alumno' => $escenario['alumno']->id,
            'curso'  => $escenario['curso']->id,
        ]));
        $response->assertSessionHasErrors('emision');
    }

    #[Test]
    public function test_listado_alumno_en_dos_cursos_genera_grupos_separados(): void
    {
        $alumno = Alumno::factory()->create();

        $cursoA = Curso::factory()->create(['nombre' => 'Curso A']);
        $cursoB = Curso::factory()->create(['nombre' => 'Curso B']);

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id'  => $cursoA->id,
            'estado'    => 'aprobada',
            'activa'    => true,
        ]);

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id'  => $cursoB->id,
            'estado'    => 'aprobada',
            'activa'    => false,
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin'    => null,
            'activo'       => true,
            'firma_path'   => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $maestro = Maestro::factory()->create();
        DetalleCurso::factory()->create(['curso_id' => $cursoA->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);
        DetalleCurso::factory()->create(['curso_id' => $cursoB->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);

        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $cursoA->detalleCursos->first()->id,
            'promedio' => 8, 'conducta' => 'B', 'registrado_por' => $maestroRegistra->id,
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $cursoB->detalleCursos->first()->id,
            'promedio' => 9, 'conducta' => 'MB', 'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.boletas'));

        $response->assertOk();

        $urlPreviewA = route('admin.boletas.preview', ['alumno' => $alumno->id, 'curso' => $cursoA->id]);
        $urlPreviewB = route('admin.boletas.preview', ['alumno' => $alumno->id, 'curso' => $cursoB->id]);

        $response->assertSee($urlPreviewA);
        $response->assertSee($urlPreviewB);
    }

    #[Test]
    public function test_listado_cursos_mismo_nombre_no_colisionan(): void
    {
        $alumno = Alumno::factory()->create();

        $cursoA = Curso::factory()->create([
            'nombre' => 'Primero',
            'seccion' => 'A',
            'anio_lectivo' => 2024,
        ]);
        $cursoB = Curso::factory()->create([
            'nombre' => 'Primero',
            'seccion' => 'B',
            'anio_lectivo' => 2024,
        ]);

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id'  => $cursoA->id,
            'estado'    => 'aprobada',
            'activa'    => true,
        ]);

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id'  => $cursoB->id,
            'estado'    => 'aprobada',
            'activa'    => true,
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin'    => null,
            'activo'       => true,
            'firma_path'   => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $maestro = Maestro::factory()->create();
        DetalleCurso::factory()->create(['curso_id' => $cursoA->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);
        DetalleCurso::factory()->create(['curso_id' => $cursoB->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);

        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $cursoA->detalleCursos->first()->id,
            'promedio' => 8, 'conducta' => 'B', 'registrado_por' => $maestroRegistra->id,
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $cursoB->detalleCursos->first()->id,
            'promedio' => 9, 'conducta' => 'MB', 'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.boletas'));

        $response->assertOk();

        $urlPreviewA = route('admin.boletas.preview', ['alumno' => $alumno->id, 'curso' => $cursoA->id]);
        $urlPreviewB = route('admin.boletas.preview', ['alumno' => $alumno->id, 'curso' => $cursoB->id]);

        $response->assertSee($urlPreviewA);
        $response->assertSee($urlPreviewB);

        $this->assertNotEquals($cursoA->id, $cursoB->id);
    }

    // ==================== TESTS DE INTEGRACIÓN CONTROLADOR/VISTA (B3-B1C) ====================

    #[Test]
    public function test_preview_muestra_materias_pendientes_sin_nota(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $materiaConNota = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $materiaSinNota = Materia::factory()->create(['nombre' => 'Historia', 'es_opcional' => false, 'activa' => true]);

        $maestro = Maestro::factory()->create();
        $dc1 = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaConNota->id, 'maestro_id' => $maestro->id]);
        $dc2 = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaSinNota->id, 'maestro_id' => $maestro->id]);

        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc1->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B', 'registrado_por' => $maestroRegistra->id,
        ]);
        // NO crear nota para Historia

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertOk();
        $response->assertSee('Materia Regular A');
        $response->assertSee('Historia');
        $response->assertSee('Pendiente'); // Badge de pendiente para materia sin nota
        $response->assertSee('—'); // Valores vacíos se muestran como em dash
    }

    #[Test]
    public function test_preview_no_muestra_columna_sos(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        $response->assertDontSee('SOS'); // Columna SOS ya no debe aparecer
    }

    #[Test]
    public function test_preview_muestra_conceptos_r_y_d(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        // Materia con promedio 6 -> Concepto R
        $materiaR = Materia::factory()->create(['nombre' => 'Materia R', 'es_opcional' => false, 'activa' => true]);
        // Materia con promedio 5 -> Concepto D
        $materiaD = Materia::factory()->create(['nombre' => 'Materia D', 'es_opcional' => false, 'activa' => true]);

        $maestro = Maestro::factory()->create();
        $dcR = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaR->id, 'maestro_id' => $maestro->id]);
        $dcD = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaD->id, 'maestro_id' => $maestro->id]);

        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcR->id,
            'laboratorio' => 6.0, 'examen_teorico' => 6.0, 'practica' => 6.0,
            'promedio' => 6, 'conducta' => 'R', 'registrado_por' => $maestroRegistra->id,
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcD->id,
            'laboratorio' => 5.0, 'examen_teorico' => 5.0, 'practica' => 5.0,
            'promedio' => 5, 'conducta' => 'D', 'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertOk();
        $response->assertSee('R'); // Concepto R para promedio 6
        $response->assertSee('D'); // Concepto D para promedio 5
    }

    #[Test]
    public function test_preview_boton_emitir_deshabilitado_si_puede_emitir_false(): void
    {
        $escenario = $this->crearEscenarioValido();

        // Agregar una materia sin nota para que puede_emitir_academicamente = false
        $materiaNueva = Materia::factory()->create(['nombre' => 'Historia', 'es_opcional' => false, 'activa' => true]);
        $maestro = Maestro::factory()->create();
        DetalleCurso::factory()->create(['curso_id' => $escenario['curso']->id, 'materia_id' => $materiaNueva->id, 'maestro_id' => $maestro->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        // El botón debe estar deshabilitado y con tooltip
        $response->assertSee('disabled');
        $response->assertSee('Calificaciones pendientes'); // Tooltip/text indicando por qué no se puede emitir
    }

    #[Test]
    public function test_preview_boton_emitir_habilitado_si_todo_completo(): void
    {
        $escenario = $this->crearEscenarioValido();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        // El botón debe estar habilitado (no disabled)
        $response->assertDontSee('disabled');
        $response->assertSee('Emitir boleta');
    }

    #[Test]
    public function test_preview_valores_cero_son_validos(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $materia = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $maestro = Maestro::factory()->create();
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);

        $maestroRegistra = Maestro::factory()->create();
        // Nota con todos los componentes = 0 (válido, no null)
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dc->id,
            'laboratorio' => 0.0,
            'examen_teorico' => 0.0,
            'practica' => 0.0,
            'promedio' => 0,
            'conducta' => 'D',
            'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertOk();
        $response->assertSee('0'); // Valores 0 se muestran
        $response->assertSee('D'); // Concepto D para promedio 0
        $response->assertDontSee('Pendiente'); // No debe aparecer como pendiente
    }

    #[Test]
    public function test_preview_vs_show_datos_consistentes(): void
    {
        $escenario = $this->crearEscenarioValido();

        // Preview (ruta explícita)
        $responsePreview = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        // Show (ruta legacy con curso_id)
        $responseShow = $this->actingAs($this->admin)
            ->get(route('admin.boletas.show', [
                'alumno_id' => $escenario['alumno']->id,
                'curso_id'  => $escenario['curso']->id,
            ]));

        $responsePreview->assertOk();
        $responseShow->assertOk();

        // Ambas deben mostrar las mismas materias
        $responsePreview->assertSee('Materia Regular A');
        $responsePreview->assertSee('Materia Regular B');
        $responseShow->assertSee('Materia Regular A');
        $responseShow->assertSee('Materia Regular B');

        // Ambas deben mostrar los mismos promedios
        $responsePreview->assertSee('8');
        $responsePreview->assertSee('9');
        $responseShow->assertSee('8');
        $responseShow->assertSee('9');
    }

    #[Test]
    public function test_preview_no_escribe_en_bd(): void
    {
        $escenario = $this->crearEscenarioValido();

        $countsAntes = [
            'boletas' => Boleta::count(),
            'detalles_boletas' => DetalleBoleta::count(),
        ];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $escenario['alumno'],
                'curso'  => $escenario['curso'],
            ]));

        $response->assertOk();
        $this->assertSame($countsAntes['boletas'], Boleta::count());
        $this->assertSame($countsAntes['detalles_boletas'], DetalleBoleta::count());
    }

    #[Test]
    public function test_preview_materia_opcional_asignada_se_muestra(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $materiaOpcional = Materia::factory()->create(['nombre' => 'Arte', 'es_opcional' => true, 'activa' => true]);

        $maestro = Maestro::factory()->create();
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id, 'maestro_id' => $maestro->id]);
        $dcOpcional = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaOpcional->id, 'maestro_id' => $maestro->id]);

        // Registrar en alumno_materia (fuente de verdad)
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $materiaOpcional->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B', 'registrado_por' => $maestroRegistra->id,
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcOpcional->id,
            'laboratorio' => 9.0, 'examen_teorico' => 9.0, 'practica' => 9.0,
            'promedio' => 9, 'conducta' => 'MB', 'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertOk();
        $response->assertSee('Materia Regular A');
        $response->assertSee('Arte'); // Opcional asignada debe aparecer
    }

    #[Test]
    public function test_preview_materia_opcional_no_asignada_no_se_muestra(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
        ]);

        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $materiaOpcional = Materia::factory()->create(['nombre' => 'Arte', 'es_opcional' => true, 'activa' => true]);

        $maestro = Maestro::factory()->create();
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id, 'maestro_id' => $maestro->id]);
        $dcOpcional = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaOpcional->id, 'maestro_id' => $maestro->id]);

        // NO registrar en alumno_materia
        // Pero SÍ crear una nota accidental para la opcional no asignada
        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B', 'registrado_por' => $maestroRegistra->id,
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcOpcional->id,
            'laboratorio' => 10.0, 'examen_teorico' => 10.0, 'practica' => 10.0,
            'promedio' => 10, 'conducta' => 'E', 'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertOk();
        $response->assertSee('Materia Regular A');
        $response->assertDontSee('Arte'); // Opcional NO asignada no debe aparecer
    }

    #[Test]
    public function test_preview_ingles_via_alumno_materia(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();

        Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'estado' => 'aprobada',
            'activa' => true,
            'tomara_ingles' => false, // inscripción dice que NO toma inglés
        ]);

        $director = Director::factory()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'firma_path' => 'directores/firmas/director.png',
        ]);
        Storage::disk('local')->put('directores/firmas/director.png', 'fake-signature-content');

        $materiaRegular = Materia::factory()->create(['nombre' => 'Materia Regular A', 'es_opcional' => false, 'activa' => true]);
        $ingles = Materia::factory()->create(['nombre' => 'Inglés', 'es_opcional' => true, 'codigo' => 'ING', 'activa' => true]);

        $maestro = Maestro::factory()->create();
        $dcRegular = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materiaRegular->id, 'maestro_id' => $maestro->id]);
        $dcIngles = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $ingles->id, 'maestro_id' => $maestro->id]);

        // Registrar en alumno_materia (fuente de verdad, no tomara_ingles)
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'materia_id' => $ingles->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $maestroRegistra = Maestro::factory()->create();
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcRegular->id,
            'laboratorio' => 8.0, 'examen_teorico' => 8.0, 'practica' => 8.0,
            'promedio' => 8, 'conducta' => 'B', 'registrado_por' => $maestroRegistra->id,
        ]);
        Nota::factory()->create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $dcIngles->id,
            'laboratorio' => 9.0, 'examen_teorico' => 9.0, 'practica' => 9.0,
            'promedio' => 9, 'conducta' => 'MB', 'registrado_por' => $maestroRegistra->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.boletas.preview', [
                'alumno' => $alumno,
                'curso'  => $curso,
            ]));

        $response->assertOk();
        $response->assertSee('Materia Regular A');
        $response->assertSee('Inglés'); // Inglés aparece por alumno_materia, no por tomara_ingles
    }
}