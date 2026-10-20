<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MateriaVisibilidadCursoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Curso $curso;
    private Materia $activa;
    private Materia $inactiva;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->curso = Curso::factory()->create([
            'nombre' => 'Curso B',
            'nivel' => 'Matutino',
            'seccion' => 'B',
            'anio_lectivo' => 2026,
            'activo' => true,
        ]);

        $this->activa = Materia::factory()->create([
            'nombre' => 'Microsoft Word',
            'activa' => true,
            'es_opcional' => false,
        ]);

        $this->inactiva = Materia::factory()->create([
            'nombre' => 'Microsoft Windows',
            'activa' => false,
            'es_opcional' => false,
        ]);
    }

    private static int $secuenciaMaestro = 0;

    private function crearMaestro(string $nombre = 'Carlos'): Maestro
    {
        $user = User::factory()->create(['role' => 'docente']);
        self::$secuenciaMaestro++;

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => $nombre,
            'apellido' => 'Mendoza',
            'codigo' => 'CM' . str_pad((string) self::$secuenciaMaestro, 3, '0', STR_PAD_LEFT),
            'activo' => true,
        ]);
    }

    private function detalle(Materia $materia, array $attrs = []): DetalleCurso
    {
        return DetalleCurso::create(array_merge([
            'curso_id' => $this->curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => null,
        ], $attrs));
    }

    private function crearNota(DetalleCurso $detalle, array $valores = ['laboratorio' => 8]): Nota
    {
        return Nota::create(array_merge([
            'alumno_id' => Alumno::factory()->create()->id,
            'detalle_curso_id' => $detalle->id,
            'registrado_por' => $this->crearMaestro()->id,
        ], $valores));
    }

    private function crearAsistencia(DetalleCurso $detalle): Asistencia
    {
        return Asistencia::create([
            'curso_id' => $this->curso->id,
            'detalle_curso_id' => $detalle->id,
            'alumno_id' => Alumno::factory()->create()->id,
            'fecha' => now()->toDateString(),
            'estado' => 'presente',
            'registrado_por' => $this->crearMaestro()->id,
        ]);
    }

    private function nombresVisibles(): array
    {
        return DetalleCurso::where('curso_id', $this->curso->id)
            ->visibles()
            ->with('materia')
            ->orderBy('id')
            ->get()
            ->map(fn ($d) => $d->materia->nombre)
            ->all();
    }

    private function verDetalle()
    {
        return $this->actingAs($this->admin)->get(route('admin.cursos.show', $this->curso->id));
    }

    // ── 1. Inactiva + 0 notas + 0 asistencias -> NO aparece ──────────────────

    #[Test]
    public function test_inactiva_sin_notas_ni_asistencias_no_aparece(): void
    {
        $this->detalle($this->inactiva);

        $this->assertEquals([], $this->nombresVisibles());
        $this->verDetalle()->assertOk()->assertDontSee('Microsoft Windows');
    }

    // ── 2. Inactiva + notas -> aparece ───────────────────────────────────────

    #[Test]
    public function test_inactiva_con_notas_aparece(): void
    {
        $detalle = $this->detalle($this->inactiva);
        $this->crearNota($detalle, ['examen_teorico' => 9]);

        $this->assertEquals(['Microsoft Windows'], $this->nombresVisibles());
        $this->verDetalle()->assertOk()->assertSee('Microsoft Windows');
    }

    #[Test]
    public function test_inactiva_con_cualquier_evaluacion_capturada_aparece(): void
    {
        $evaluaciones = [
            'laboratorio' => 8,
            'examen_teorico' => 9,
            'practica' => 7,
            'conducta' => 'B',
        ];

        foreach ($evaluaciones as $columna => $valor) {
            $materia = Materia::factory()->create([
                'nombre' => 'Inactiva ' . $columna,
                'activa' => false,
            ]);

            $detalle = DetalleCurso::create([
                'curso_id' => $this->curso->id,
                'materia_id' => $materia->id,
                'maestro_id' => null,
            ]);

            $this->crearNota($detalle, [$columna => $valor]);

            $this->assertContains(
                $materia->nombre,
                $this->nombresVisibles(),
                "una nota en '{$columna}' debe contar como actividad academica"
            );
        }
    }

    // ── 3. Inactiva + asistencias -> aparece ─────────────────────────────────

    #[Test]
    public function test_inactiva_con_asistencias_aparece(): void
    {
        $detalle = $this->detalle($this->inactiva);
        $this->crearAsistencia($detalle);

        $this->assertEquals(['Microsoft Windows'], $this->nombresVisibles());
        $this->verDetalle()->assertOk()->assertSee('Microsoft Windows');
    }

    // ── 4. Activa -> aparece ─────────────────────────────────────────────────

    #[Test]
    public function test_materia_activa_aparece(): void
    {
        $this->detalle($this->activa);

        $this->assertEquals(['Microsoft Word'], $this->nombresVisibles());
        $this->verDetalle()->assertOk()->assertSee('Microsoft Word');
    }

    // ── 5. Inactiva + maestro pero SIN actividad -> se puede quitar ──────────

    #[Test]
    public function test_inactiva_con_maestro_pero_sin_actividad_no_aparece(): void
    {
        $maestro = $this->crearMaestro();
        $this->detalle($this->inactiva, ['maestro_id' => $maestro->id]);

        $this->assertEquals([], $this->nombresVisibles());
        $this->verDetalle()->assertOk()->assertDontSee('Microsoft Windows');
    }

    #[Test]
    public function test_inactiva_con_asignacion_docente_pero_sin_actividad_no_aparece(): void
    {
        $detalle = $this->detalle($this->inactiva);

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $this->crearMaestro()->id,
            'estado' => 'activa',
            'asignado_por' => $this->admin->id,
        ]);

        $this->assertEquals([], $this->nombresVisibles());
    }

    #[Test]
    public function test_inactiva_con_maestro_pero_con_notas_si_aparece(): void
    {
        $detalle = $this->detalle($this->inactiva, ['maestro_id' => $this->crearMaestro()->id]);
        $this->crearNota($detalle);

        $this->assertEquals(['Microsoft Windows'], $this->nombresVisibles());
    }

    // ── 6. No se borra histórico ─────────────────────────────────────────────

    #[Test]
    public function test_ocultar_no_borra_notas_ni_asistencias(): void
    {
        $detalle = $this->detalle($this->inactiva, ['maestro_id' => $this->crearMaestro()->id]);
        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $this->crearMaestro()->id,
            'estado' => 'activa',
            'asignado_por' => $this->admin->id,
        ]);

        $nota = $this->crearNota($detalle);
        $asistencia = $this->crearAsistencia($detalle);

        $this->verDetalle()->assertOk();

        $this->assertDatabaseHas('detalle_cursos', ['id' => $detalle->id, 'maestro_id' => $detalle->maestro_id]);
        $this->assertDatabaseHas('asignaciones_docentes', ['id' => $asignacion->id, 'estado' => 'activa']);
        $this->assertDatabaseHas('notas', ['id' => $nota->id]);
        $this->assertDatabaseHas('asistencias', ['id' => $asistencia->id]);

        $this->assertEquals(1, Nota::count());
        $this->assertEquals(1, Asistencia::count());
        $this->assertEquals(1, AsignacionDocente::count());
    }

    #[Test]
    public function test_desactivar_materia_no_borra_nada(): void
    {
        $detalle = $this->detalle($this->activa, ['maestro_id' => $this->crearMaestro()->id]);
        $nota = $this->crearNota($detalle);

        $this->activa->update(['activa' => false]);

        $this->assertDatabaseHas('detalle_cursos', ['id' => $detalle->id]);
        $this->assertDatabaseHas('notas', ['id' => $nota->id]);
        $this->assertEquals(['Microsoft Word'], $this->nombresVisibles());
    }

    // ── 7. Contador coincide con el listado ──────────────────────────────────

    #[Test]
    public function test_contador_coincide_con_el_listado(): void
    {
        $this->detalle($this->activa);
        $this->detalle($this->inactiva);

        $visibles = DetalleCurso::where('curso_id', $this->curso->id)->visibles()->get();

        $this->assertCount(1, $visibles);
        $this->assertSame(1, $visibles->count());

        $response = $this->verDetalle();
        $response->assertOk();
        $response->assertSee('Microsoft Word');
        $response->assertDontSee('Microsoft Windows');
    }

    #[Test]
    public function test_contador_cubre_las_tres_situaciones(): void
    {
        $this->detalle($this->activa);

        $conNota = $this->detalle($this->inactiva);
        $this->crearNota($conNota);

        $sinActividad = $this->detalle(Materia::factory()->create([
            'nombre' => 'Materia Muerta',
            'activa' => false,
        ]));

        $visibles = DetalleCurso::where('curso_id', $this->curso->id)->visibles()->count();

        $this->assertSame(2, $visibles);
        $this->assertDatabaseHas('detalle_cursos', ['id' => $sinActividad->id]);
    }

    // ── Fila de notas vacía no cuenta como actividad ─────────────────────────

    #[Test]
    public function test_fila_de_notas_sin_calificaciones_no_es_actividad(): void
    {
        $detalle = $this->detalle($this->inactiva);

        // El flujo real usa updateOrCreate y puede dejar filas con todas las notas NULL.
        $this->crearNota($detalle, [
            'laboratorio' => null,
            'examen_teorico' => null,
            'practica' => null,
            'conducta' => null,
        ]);

        $this->assertNull(Nota::first()->promedio, 'el promedio se deriva de las notas');
        $this->assertEquals([], $this->nombresVisibles());
    }

    // ── Regla reutilizable vs scope: deben coincidir ─────────────────────────

    #[Test]
    public function test_la_funcion_reutilizable_coincide_con_el_scope(): void
    {
        $conNota = $this->detalle($this->inactiva);
        $this->crearNota($conNota);

        $soloAsistencia = $this->detalle(Materia::factory()->create(['nombre' => 'Con asistencia', 'activa' => false]));
        $this->crearAsistencia($soloAsistencia);

        $maestroSinNada = $this->detalle(Materia::factory()->create(['nombre' => 'Solo maestro', 'activa' => false]));
        $maestroSinNada->update(['maestro_id' => $this->crearMaestro()->id]);

        $notaVacia = $this->detalle(Materia::factory()->create(['nombre' => 'Nota vacia', 'activa' => false]));
        $this->crearNota($notaVacia, ['laboratorio' => null, 'examen_teorico' => null, 'practica' => null, 'conducta' => null]);

        $cargados = DetalleCurso::where('curso_id', $this->curso->id)
            ->with(['notas', 'asistencias', 'materia'])
            ->get();

        $porFuncion = $cargados->filter(fn ($d) => $d->tieneActividadAcademica())->pluck('id')->sort()->values();
        $porScope = DetalleCurso::where('curso_id', $this->curso->id)
            ->visibles()
            ->whereHas('materia', fn ($q) => $q->where('activa', false))
            ->pluck('id')
            ->sort()
            ->values();

        $this->assertEquals($porScope->all(), $porFuncion->all());
        $this->assertEquals([$conNota->id, $soloAsistencia->id], $porFuncion->all());
    }

    // ── No se borran relaciones: la re reactivacion las reutiliza ────────────

    #[Test]
    public function test_reactivar_devuelve_la_materia_sin_recrear_nada(): void
    {
        $detalle = $this->detalle($this->inactiva);
        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $this->crearMaestro()->id,
            'estado' => 'activa',
            'asignado_por' => $this->admin->id,
        ]);

        $this->assertEquals([], $this->nombresVisibles());

        $this->inactiva->update(['activa' => true]);

        $this->assertEquals(['Microsoft Windows'], $this->nombresVisibles());
        $this->assertDatabaseHas('detalle_cursos', ['id' => $detalle->id]);
        $this->assertDatabaseHas('asignaciones_docentes', ['id' => $asignacion->id]);
    }

    // ── Asignación docente: solo activas ─────────────────────────────────────

    #[Test]
    public function test_materia_inactiva_no_se_ofrece_para_nueva_asignacion(): void
    {
        $this->detalle($this->activa);
        $this->detalle($this->inactiva);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.maestros.materiasPorCurso', $this->curso->id) . '?maestro_id=' . $this->crearMaestro()->id);

        $response->assertOk();

        $nombres = collect($response->json('materias'))->pluck('nombre')->all();

        $this->assertContains('Microsoft Word', $nombres);
        $this->assertNotContains('Microsoft Windows', $nombres);
    }

    #[Test]
    public function test_asignar_materia_activa_si_se_permite(): void
    {
        $this->detalle($this->activa);
        $maestro = $this->crearMaestro();

        $this->actingAs($this->admin)
            ->post(route('admin.maestros.asignar', $maestro->id), [
                'curso_id' => $this->curso->id,
                'materia_ids' => [$this->activa->id],
            ])
            ->assertRedirect(route('admin.maestros.show', $maestro->id));

        $this->assertEquals(1, AsignacionDocente::count());
    }

    #[Test]
    public function test_crear_curso_solo_asigna_materias_activas(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cursos.store'), [
            'nombre' => 'Curso Nuevo',
            'nivel' => 'Matutino',
            'seccion' => 'C',
            'anio_lectivo' => 2026,
            'fecha_inicio' => '2026-09-24',
            'fecha_fin' => '2028-09-24',
        ])->assertRedirect(route('admin.cursos'));

        $nuevo = Curso::where('nombre', 'Curso Nuevo')->firstOrFail();

        $materias = DetalleCurso::where('curso_id', $nuevo->id)
            ->with('materia')
            ->get()
            ->map(fn ($d) => $d->materia->nombre)
            ->all();

        $this->assertContains('Microsoft Word', $materias);
        $this->assertNotContains('Microsoft Windows', $materias);
    }
}
