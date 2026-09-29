<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CursoProximoInscripcionTest extends TestCase
{
    use RefreshDatabase;

    private User $alumnoUser;
    private Alumno $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alumnoUser = User::factory()->create(['role' => 'alumno']);

        $this->alumno = Alumno::factory()->create([
            'user_id' => $this->alumnoUser->id,
            'nombre' => 'Marcos',
            'apellido' => 'Montes',
            'codigo' => 'A001',
        ]);
    }

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

    /** Curso Activo: inicio ya llamando */
    private function cursoActivo(string $nombre = 'Curso B'): Curso
    {
        return $this->crearCurso($nombre, [
            'fecha_inicio' => now()->subWeek(),
        ]);
    }

    private function cursoFinalizado(string $nombre = 'Curso C'): Curso
    {
        return $this->crearCurso($nombre, [
            'fecha_inicio' => now()->subYear(),
            'fecha_fin' => now()->subDay(),
        ]);
    }

    private function selector(): \Illuminate\Support\Collection
    {
        return Curso::proximo()->get();
    }

    private function verFormulario()
    {
        return $this->actingAs($this->alumnoUser)->get(route('alumno.inscripcion'));
    }

    // ── 1. Curso Próximo -> aparece ──────────────────────────────────────────

    #[Test]
    public function test_curso_proximo_aparece_en_el_selector(): void
    {
        $proximo = $this->crearCurso('Curso A');

        $this->assertEquals(Curso::ESTADO_PROXIMO, $proximo->estado_curso);
        $this->assertTrue($proximo->admiteNuevaInscripcion());

        $this->assertSame([$proximo->id], $this->selector()->pluck('id')->all());

        $this->verFormulario()
            ->assertOk()
            ->assertSee('Curso A');
    }

    // ── 2. Curso Activo -> no aparece ────────────────────────────────────────

    #[Test]
    public function test_curso_activo_no_aparece_en_el_selector(): void
    {
        $activo = $this->cursoActivo();

        $this->assertEquals(Curso::ESTADO_ACTIVO, $activo->estado_curso);
        $this->assertFalse($activo->admiteNuevaInscripcion());

        $this->assertSame([], $this->selector()->pluck('id')->all());

        $this->verFormulario()
            ->assertOk()
            ->assertDontSee('Curso B');
    }

    #[Test]
    public function test_curso_activo_por_fecha_no_aparece(): void
    {
        $this->crearCurso('Derivado', ['fecha_inicio' => now()->subDay()]);

        $this->assertSame([], $this->selector()->pluck('id')->all());
    }

    #[Test]
    public function test_curso_activo_por_override_manual_no_aparece(): void
    {
        $this->crearCurso('Con override', [
            'fecha_inicio' => now()->addMonth(),
            'estado' => Curso::ESTADO_ACTIVO,
        ]);

        $this->assertSame([], $this->selector()->pluck('id')->all());
    }

    // ── 3. Curso Finalizado -> no aparece ────────────────────────────────────

    #[Test]
    public function test_curso_finalizado_no_aparece_en_el_selector(): void
    {
        $finalizado = $this->cursoFinalizado();

        $this->assertEquals(Curso::ESTADO_FINALIZADO, $finalizado->estado_curso);
        $this->assertSame([], $this->selector()->pluck('id')->all());

        $this->verFormulario()->assertOk()->assertDontSee('Curso C');
    }

    #[Test]
    public function test_curso_finalizado_por_boton_no_aparece_aunque_tenga_fecha_inicia_futura(): void
    {
        $manual = $this->crearCurso('Finalizado a mano', [
            'fecha_inicio' => now()->addMonth(),
            'finalizado_manualmente' => true,
        ]);

        $this->assertEquals(Curso::ESTADO_FINALIZADO, $manual->estado_curso);
        $this->assertSame([], $this->selector()->pluck('id')->all());
    }

    #[Test]
    public function test_curso_inactivo_no_aparece(): void
    {
        $this->crearCurso('Inactivo', ['activo' => false]);

        $this->assertSame([], $this->selector()->pluck('id')->all());
    }

    #[Test]
    public function test_ejemplo_del_negocio_solo_muestra_el_proximo(): void
    {
        $this->crearCurso('Curso A');
        $this->cursoActivo('Curso B');
        $this->cursoFinalizado('Curso C');

        $this->assertSame(['Curso A'], $this->selector()->pluck('nombre')->all());

        $html = $this->verFormulario()->assertOk()->getContent();
        $this->assertStringContainsString('Curso A', $html);
        $this->assertStringNotContainsString('Curso B', $html);
        $this->assertStringNotContainsString('Curso C', $html);
    }

    // ── 4. Request manipulado con curso Activo -> el backend rechaza ─────────

    #[Test]
    public function test_inscribirse_rechaza_un_curso_activo_manipulado(): void
    {
        $activo = $this->cursoActivo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $activo->id])
            ->assertSessionHasErrors('curso_id');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    #[Test]
    public function test_inscribirse_rechaza_un_curso_finalizado_manipulado(): void
    {
        $finalizado = $this->cursoFinalizado();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $finalizado->id])
            ->assertSessionHasErrors('curso_id');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    #[Test]
    public function test_enviar_solicitud_rechaza_un_curso_activo_manipulado(): void
    {
        $activo = $this->cursoActivo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id' => $activo->id,
                'alumno_dui' => '12345678-9',
                'encargado_nombre' => 'Maria Montes',
                'encargado_parentesco' => 'Madre',
                'encargado_dui' => '87654321-9',
                'encargado_telefo' => '70000000',
                'encargado_telefono' => '70000000',
                'firma_alumno' => 'data:image/png;base64,AAAA',
                'firma_encargado' => 'data:image/png;base64,BBBB',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    #[Test]
    public function test_subir_documento_rechaza_un_curso_activo_manipulado(): void
    {
        $activo = $this->cursoActivo();

        $archivo = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.subir'), [
                'curso_id' => $activo->id,
                'documento' => $archivo,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    #[Test]
    public function test_inscribirse_acepta_un_curso_proximo(): void
    {
        $proximo = $this->crearCurso('Curso A');

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $proximo->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inscripciones', [
            'alumno_id' => $this->alumno->id,
            'curso_id' => $proximo->id,
            'estado' => 'aprobada',
        ]);
    }

    // ── 5. Curso Próximo sin cupo -> no permite inscripción ─────────────────

    #[Test]
    public function test_curso_proximo_lleno_no_permite_inscripcion(): void
    {
        $proximo = $this->crearCurso('Curso Lleno');

        foreach (range(1, Curso::CUPOS_MAXIMO) as $i) {
            Inscripcion::create([
                'alumno_id' => Alumno::factory()->create()->id,
                'curso_id' => $proximo->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => true,
                'estado' => 'aprobada',
            ]);
        }

        $proximo->refresh();
        $this->assertTrue($proximo->esta_lleno);
        $this->assertFalse($proximo->admiteNuevaInscripcion());

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $proximo->id])
            ->assertSessionHasErrors('curso_id');

        $this->assertDatabaseCount('inscripciones', Curso::CUPOS_MAXIMO);
    }

    #[Test]
    public function test_curso_lleno_se_muestra_deshabilitado_en_el_formulario(): void
    {
        $lleno = $this->crearCurso('Curso Lleno');
        $disponible = $this->crearCurso('Curso Libre');

        foreach (range(1, Curso::CUPOS_MAXIMO) as $i) {
            Inscripcion::create([
                'alumno_id' => Alumno::factory()->create()->id,
                'curso_id' => $lleno->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => true,
                'estado' => 'aprobada',
            ]);
        }

        $html = $this->verFormulario()->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/value="' . $lleno->id . '"[^>]*disabled/',
            $html,
            'un curso sin cupo no debe ser seleccionable'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/value="' . $disponible->id . '"[^>]*disabled/',
            $html,
            'un curso con cupo debe seguir siendo seleccionable'
        );
    }

    // ── 6. Próximo -> Activo: desaparece sin tocar lo existente ─────────────

    #[Test]
    public function test_cambiar_de_proximo_a_activo_desaparece_del_selector(): void
    {
        $proximo = $this->crearCurso('Curso A');

        $this->assertSame([$proximo->id], $this->selector()->pluck('id')->all());

        $proximo->update(['estado' => Curso::ESTADO_ACTIVO]);
        $proximo->refresh();

        $this->assertEquals(Curso::ESTADO_ACTIVO, $proximo->estado_curso);
        $this->assertSame([], $this->selector()->pluck('id')->all());

        $this->verFormulario()->assertOk()->assertDontSee('Curso A');
    }

    #[Test]
    public function test_cambiar_de_proximo_a_activo_no_borra_solicitudes_existentes(): void
    {
        $proximo = $this->crearCurso('Curso A');

        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $proximo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $proximo->update(['estado' => Curso::ESTADO_ACTIVO]);

        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
            'curso_id' => $proximo->id,
        ]);

        $this->actingAs($this->alumnoUser)
            ->get(route('alumno.dashboard'))
            ->assertOk();

        $this->assertDatabaseHas('inscripciones', ['id' => $solicitud->id, 'estado' => 'pendiente']);
    }

    #[Test]
    public function test_la_solicitud_pendiente_sigue_visible_para_el_administrador(): void
    {
        $proximo = $this->crearCurso('Curso A');

        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $proximo->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => false,
            'estado' => 'pendiente',
        ]);

        $proximo->update(['estado' => Curso::ESTADO_ACTIVO]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.solicitudes'))
            ->assertOk()
            ->assertSee('Curso A');

        $this->actingAs($admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id))
            ->assertRedirect();

        $this->assertDatabaseHas('inscripciones', ['id' => $solicitud->id, 'estado' => 'aprobada']);
    }

    // ── Coherencia entre el scope SQL y el accessor ─────────────────────────

    #[Test]
    public function test_el_scope_coincide_con_el_accessor_en_todos_los_casos(): void
    {
        $casos = [
            'proximo por fecha' => $this->crearCurso('P1'),
            'proximo por override' => $this->crearCurso('P2', ['estado' => Curso::ESTADO_PROXIMO]),
            'activo por fecha' => $this->crearCurso('A1', ['fecha_inicio' => now()->subDay()]),
            'activo por override' => $this->crearCurso('A2', ['estado' => Curso::ESTADO_ACTIVO]),
            'finalizado por fecha' => $this->cursoFinalizado('F1'),
            'finalizado por boton' => $this->crearCurso('F2', ['finalizado_manualmente' => true]),
            'inactivo' => $this->crearCurso('I1', ['activo' => false]),
            'sin fechas' => $this->crearCurso('S1', [
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'estado' => null,
            ]),
            'override proximo con fechas vencidas' => $this->crearCurso('S2', [
                'fecha_inicio' => now()->subYear(),
                'fecha_fin' => now()->addYear(),
                'estado' => Curso::ESTADO_PROXIMO,
            ]),
            'override activo con inicio futuro' => $this->crearCurso('S3', [
                'fecha_inicio' => now()->addMonth(),
                'estado' => Curso::ESTADO_ACTIVO,
            ]),
        ];

        // El accessor estado_curso solo refleja el estado temporal; la visibilidad
        // la controla 'activo'. La comparación debe hacerse sobre cursos activos.
        $porAccessor = Curso::where('activo', true)
            ->get()
            ->filter(fn ($c) => $c->estado_curso === Curso::ESTADO_PROXIMO)
            ->pluck('id')
            ->sort()
            ->values();

        $porScope = $this->selector()->pluck('id')->sort()->values();

        $this->assertEquals($porAccessor->all(), $porScope->all());
        $this->assertNotEmpty($porScope->all(), 'debe existir al menos un caso PROXIMO');

        // Un curso inactivo nunca es seleccionable, aunque su estado sea PROXIMO.
        $inactivoProximo = Curso::where('nombre', 'I1')->firstOrFail();
        $this->assertEquals(Curso::ESTADO_PROXIMO, $inactivoProximo->estado_curso);
        $this->assertFalse($inactivoProximo->activo);
        $this->assertNotContains($inactivoProximo->id, $porScope->all());
    }

    #[Test]
    public function test_un_override_ignora_un_curso_inactivo(): void
    {
        $this->crearCurso('Inactivo con proximo', [
            'activo' => false,
            'estado' => Curso::ESTADO_PROXIMO,
        ]);

        $this->assertSame([], $this->selector()->pluck('id')->all());
    }

    // ── 8. Fuente única de verdad: Curso::admiteInscripcionPorEstado() ───────

    #[Test]
    public function test_curso_proximo_y_activo_permite_nueva_inscripcion(): void
    {
        $proximo = $this->crearCurso('Curso A');

        $this->assertTrue($proximo->admiteInscripcionPorEstado());
        $this->assertTrue($proximo->admiteNuevaInscripcion());
    }

    #[Test]
    public function test_curso_proximo_pero_inactivo_no_admite_inscripcion(): void
    {
        $inactivo = $this->crearCurso('Curso A inactivo', ['activo' => false]);

        $this->assertSame(Curso::ESTADO_PROXIMO, $inactivo->estado_curso);

        $this->assertFalse($inactivo->admiteInscripcionPorEstado());
        $this->assertFalse($inactivo->admiteNuevaInscripcion());
    }

    #[Test]
    public function test_curso_activo_no_admite_inscripcion(): void
    {
        $activo = $this->cursoActivo();

        $this->assertFalse($activo->admiteInscripcionPorEstado());
        $this->assertFalse($activo->admiteNuevaInscripcion());
    }

    #[Test]
    public function test_curso_finalizado_no_admite_inscripcion(): void
    {
        $finalizado = $this->cursoFinalizado();

        $this->assertFalse($finalizado->admiteInscripcionPorEstado());
        $this->assertFalse($finalizado->admiteNuevaInscripcion());
    }

    #[Test]
    public function test_curso_sin_cupo_no_admite_inscripcion(): void
    {
        $lleno = $this->crearCurso('Curso Lleno');

        foreach (range(1, Curso::CUPOS_MAXIMO) as $i) {
            Inscripcion::create([
                'alumno_id' => Alumno::factory()->create()->id,
                'curso_id' => $lleno->id,
                'fecha_inscripcion' => now()->toDateString(),
                'activa' => true,
                'estado' => 'aprobada',
            ]);
        }

        $this->assertTrue($lleno->admiteInscripcionPorEstado());
        $this->assertFalse($lleno->admiteNuevaInscripcion());
    }

    // ── 9. Los tres flujos reales respetan la regla centralizada ────────────

    #[Test]
    public function test_inscribirse_rechaza_un_curso_proximo_manipulado_pero_inactivo(): void
    {
        $inactivo = $this->crearCurso('Curso A inactivo', ['activo' => false]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $inactivo->id])
            ->assertSessionHasErrors('curso_id');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    #[Test]
    public function test_enviar_solicitud_rechaza_un_curso_proximo_manipulado_pero_inactivo(): void
    {
        $inactivo = $this->crearCurso('Curso A inactivo', ['activo' => false]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id' => $inactivo->id,
                'alumno_dui' => '12345678-9',
                'encargado_nombre' => 'Maria Montes',
                'encargado_parentesco' => 'Madre',
                'encargado_dui' => '87654321-9',
                'encargado_telefono' => '70000000',
                'firma_alumno' => 'data:image/png;base64,AAAA',
                'firma_encargado' => 'data:image/png;base64,BBBB',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    #[Test]
    public function test_subir_documento_rechaza_un_curso_proximo_manipulado_pero_inactivo(): void
    {
        $inactivo = $this->crearCurso('Curso A inactivo', ['activo' => false]);

        $archivo = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.subir'), [
                'curso_id' => $inactivo->id,
                'documento' => $archivo,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 0);
    }
}
