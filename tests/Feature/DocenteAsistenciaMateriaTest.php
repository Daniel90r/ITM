<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Maestro;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * La asistencia es POR MATERIA: Curso + Materia + Maestro.
 *
 * Un curso no pertenece a un maestro; cada materia puede impartirla alguien
 * distinto. Estas pruebas fijan esa regla en el backend, no solo en la vista.
 */
class DocenteAsistenciaMateriaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Maestro $maestro;

    private Curso $cursoA;

    private Curso $cursoB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create(['role' => 'docente']);
        $this->maestro = Maestro::create([
            'user_id' => $this->user->id,
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'codigo' => 'CM001',
            'activo' => true,
        ]);

        $this->cursoA = $this->curso('Curso A', 'A');
        $this->cursoB = $this->curso('Curso B', 'B');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function curso(string $nombre, string $seccion): Curso
    {
        return Curso::create([
            'nombre' => $nombre,
            'nivel' => 'Matutino',
            'seccion' => $seccion,
            'anio_lectivo' => 2026,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
            'activo' => true,
        ]);
    }

    private function materia(string $nombre, string $codigo, bool $opcional = false): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activa' => true,
            'es_opcional' => $opcional,
        ]);
    }

    /**
     * Detalle de curso SIN tocar detalle_cursos.maestro_id a propósito:
     * la fuente de verdad debe ser asignaciones_docentes.
     */
    private function detalle(Curso $curso, Materia $materia, ?Maestro $maestroLegacy = null): DetalleCurso
    {
        return DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $maestroLegacy?->id,
        ]);
    }

    private function asignar(DetalleCurso $detalle, Maestro $maestro, string $estado = 'activa'): AsignacionDocente
    {
        return AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $maestro->id,
            'estado' => $estado,
            'fecha_inicio' => now()->subMonth()->toDateString(),
        ]);
    }

    private function otroMaestro(string $codigo = 'CM002'): Maestro
    {
        $user = User::factory()->create(['role' => 'docente']);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'codigo' => $codigo,
            'activo' => true,
        ]);
    }

    private function alumnoEnCurso(Curso $curso, ?Materia $opcional = null): Alumno
    {
        $alumno = Alumno::factory()->create();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        if ($opcional) {
            DB::table('alumno_materia')->insert([
                'alumno_id' => $alumno->id,
                'curso_id' => $curso->id,
                'materia_id' => $opcional->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $alumno;
    }

    private function cursoIdsVisibles(): array
    {
        $response = $this->actingAs($this->user)->get(route('docente.asistencia'));

        $response->assertOk();

        return collect($response->viewData('cursos'))->pluck('id')->all();
    }

    private function guardarAsistencia(array $payload)
    {
        return $this->actingAs($this->user)
            ->post(route('docente.asistencia.guardar'), $payload);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CASO 1 y 3: el maestro solo ve cursos donde tiene asignación vigente
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function caso_1_maestro_ve_su_curso_y_su_materia_pero_no_la_de_otro(): void
    {
        $word = $this->materia('Microsoft Word', 'MAT00001');
        $ingles = $this->materia('Ingles', 'MAT00002');

        $detalleWord = $this->detalle($this->cursoA, $word, $this->maestro);
        $detalleIngles = $this->detalle($this->cursoA, $ingles, $this->otroMaestro());

        $this->asignar($detalleWord, $this->maestro);
        $this->asignar($detalleIngles, $this->otroMaestro('CM003'));

        $this->assertSame([$this->cursoA->id], $this->cursoIdsVisibles());

        $response = $this->actingAs($this->user)->get(route('docente.asistencia', [
            'curso_id' => $this->cursoA->id,
        ]));

        $response->assertOk();
        $materias = collect($response->viewData('materias'));
        $this->assertCount(1, $materias, 'solo debe ver su materia');
        $this->assertSame($detalleWord->id, $materias->first()->id);
    }

    #[Test]
    public function caso_3_curso_sin_asignacion_no_aparece(): void
    {
        $this->asignar(
            $this->detalle($this->cursoA, $this->materia('Word', 'MAT00001')),
            $this->maestro
        );

        $this->asignar(
            $this->detalle($this->cursoB, $this->materia('Excel', 'MAT00002')),
            $this->otroMaestro()
        );

        $visibles = $this->cursoIdsVisibles();

        $this->assertNotContains($this->cursoB->id, $visibles);
        $this->assertContains($this->cursoA->id, $visibles);
    }

    #[Test]
    public function caso_3_detalle_cursos_maestro_id_no_autoriza_sin_asignacion(): void
    {
        // El campo legacy apunta al maestro, pero NO hay asignación vigente.
        $detalle = $this->detalle(
            $this->cursoA,
            $this->materia('Word legacy', 'MAT00001'),
            $this->maestro
        );

        $this->assertSame([], $this->cursoIdsVisibles());
        $this->assertFalse($detalle->esMateriaDelMaestro($this->maestro->id));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CASO 4: manipulación del request
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function caso_4_no_puede_registrar_materia_de_otro_maestro(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $excel = $this->materia('Excel', 'MAT00002');

        $this->asignar($this->detalle($this->cursoA, $word, $this->maestro), $this->maestro);

        $detalleAjeno = $this->detalle($this->cursoA, $excel, $this->otroMaestro('CM004'));
        $this->asignar($detalleAjeno, Maestro::where('codigo', 'CM004')->firstOrFail());

        $alumno = $this->alumnoEnCurso($this->cursoA);

        $this->guardarAsistencia([
            'detalle_curso_id' => $detalleAjeno->id,
            'fecha' => now()->toDateString(),
            'asistencias' => [$alumno->id => ['estado' => 'ausente']],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
    }

    #[Test]
    public function caso_4_no_puede_registrar_alumno_ajeno_a_la_materia(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $detalle = $this->detalle($this->cursoA, $word, $this->maestro);
        $this->asignar($detalle, $this->maestro);

        $legitimo = $this->alumnoEnCurso($this->cursoA);
        $ajeno = Alumno::factory()->create();

        $this->guardarAsistencia([
            'detalle_curso_id' => $detalle->id,
            'fecha' => now()->toDateString(),
            'asistencias' => [
                $legitimo->id => ['estado' => 'presente'],
                $ajeno->id => ['estado' => 'ausente'],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CASO 5: materia opcional
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function caso_5_materia_opcional_solo_muestra_alumnos_inscritos_en_ella(): void
    {
        $ingles = $this->materia('Ingles', 'MAT00009', opcional: true);
        $detalle = $this->detalle($this->cursoA, $ingles, $this->maestro);
        $this->asignar($detalle, $this->maestro);

        $conIngles = $this->alumnoEnCurso($this->cursoA, opcional: $ingles);
        $sinIngles = $this->alumnoEnCurso($this->cursoA);

        $response = $this->actingAs($this->user)->get(route('docente.asistencia', [
            'curso_id' => $this->cursoA->id,
            'detalle_curso_id' => $detalle->id,
        ]));

        $response->assertOk();

        $ids = collect($response->viewData('alumnos'))->pluck('id')->all();

        $this->assertContains($conIngles->id, $ids);
        $this->assertNotContains($sinIngles->id, $ids);
        $response->assertSee($conIngles->nombre_completo);
        $response->assertDontSee($sinIngles->nombre_completo);
    }

    #[Test]
    public function caso_5_no_se_puede_guardar_asistencia_de_alumno_sin_la_materia_opcional(): void
    {
        $ingles = $this->materia('Ingles', 'MAT00009', opcional: true);
        $detalle = $this->detalle($this->cursoA, $ingles, $this->maestro);
        $this->asignar($detalle, $this->maestro);

        $this->alumnoEnCurso($this->cursoA);
        $sinIngles = $this->alumnoEnCurso($this->cursoA);

        $this->guardarAsistencia([
            'detalle_curso_id' => $detalle->id,
            'fecha' => now()->toDateString(),
            'asistencias' => [$sinIngles->id => ['estado' => 'presente']],
        ])->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CASO 6: asignación histórica
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function caso_6_asignacion_finalizada_no_autoriza_nuevas_asistencias(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $detalle = $this->detalle($this->cursoA, $word, $this->maestro);
        $this->asignar($detalle, $this->maestro, 'finalizada');

        $this->assertSame([], $this->cursoIdsVisibles());

        $alumno = $this->alumnoEnCurso($this->cursoA);

        $this->guardarAsistencia([
            'detalle_curso_id' => $detalle->id,
            'fecha' => now()->toDateString(),
            'asistencias' => [$alumno->id => ['estado' => 'presente']],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);

        // El historial NO se elimina.
        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $this->maestro->id,
            'estado' => 'finalizada',
        ]);
    }

    #[Test]
    public function caso_6_cambio_de_maestro_habilita_al_nuevo_y_deshabilita_al_anterior(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $detalle = $this->detalle($this->cursoA, $word, $this->maestro);

        $this->asignar($detalle, $this->maestro, 'finalizada');

        $nuevo = $this->otroMaestro('CM005');
        $this->asignar($detalle, $nuevo, 'activa');

        $alumno = $this->alumnoEnCurso($this->cursoA);

        // El anterior ya no puede.
        $this->guardarAsistencia([
            'detalle_curso_id' => $detalle->id,
            'fecha' => now()->toDateString(),
            'asistencias' => [$alumno->id => ['estado' => 'ausente']],
        ])->assertForbidden();

        // El actual sí.
        $this->actingAs($nuevo->user)
            ->post(route('docente.asistencia.guardar'), [
                'detalle_curso_id' => $detalle->id,
                'fecha' => now()->toDateString(),
                'asistencias' => [$alumno->id => ['estado' => 'presente']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('asistencias', [
            'detalle_curso_id' => $detalle->id,
            'alumno_id' => $alumno->id,
            'registrado_por' => $nuevo->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CASO 7: varios maestros en un mismo curso
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function caso_7_varios_maestros_comparten_curso_con_materias_propias(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $excel = $this->materia('Excel', 'MAT00002');
        $ingles = $this->materia('Ingles', 'MAT00003');

        $detWord = $this->detalle($this->cursoA, $word);
        $detExcel = $this->detalle($this->cursoA, $excel);
        $detIngles = $this->detalle($this->cursoA, $ingles);

        $this->asignar($detWord, $this->maestro);
        $this->asignar($detIngles, $this->maestro);

        $ana = $this->otroMaestro('CM006');
        $this->asignar($detExcel, $ana);

        // Los dos ven el mismo curso.
        $this->assertSame([$this->cursoA->id], $this->cursoIdsVisibles());

        $anaUser = $ana->user;
        $this->actingAs($anaUser)->get(route('docente.asistencia'))->assertOk();

        $anaMaterias = collect(
            $this->actingAs($anaUser)
                ->get(route('docente.asistencia', ['curso_id' => $this->cursoA->id]))
                ->viewData('materias')
        )->pluck('id');

        $this->assertSame([$detExcel->id], $anaMaterias->all());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Persistencia: la asistencia guarda la materia y el docente
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function la_asistencia_guarda_detalle_curso_alumno_fecha_y_maestro(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $detalle = $this->detalle($this->cursoA, $word, $this->maestro);
        $this->asignar($detalle, $this->maestro);

        $alumno = $this->alumnoEnCurso($this->cursoA);
        $fecha = now()->toDateString();

        $this->guardarAsistencia([
            'detalle_curso_id' => $detalle->id,
            'fecha' => $fecha,
            'asistencias' => [$alumno->id => ['estado' => 'permiso', 'observacion' => 'Cita medica']],
        ])->assertRedirect();

        $this->assertDatabaseHas('asistencias', [
            'detalle_curso_id' => $detalle->id,
            'curso_id' => $this->cursoA->id,
            'alumno_id' => $alumno->id,
            'estado' => 'permiso',
            'observacion' => 'Cita medica',
            'registrado_por' => $this->maestro->id,
        ]);

        $guardada = Asistencia::where('detalle_curso_id', $detalle->id)->firstOrFail();
        $this->assertSame($fecha, $guardada->fecha->toDateString());
    }

    #[Test]
    public function una_materia_no_autoriza_a_registrar_sobre_curso_inactivo(): void
    {
        $detalle = $this->detalle($this->cursoA, $this->materia('Word', 'MAT00001'), $this->maestro);
        $this->asignar($detalle, $this->maestro);

        $this->cursoA->update(['activo' => false]);

        $alumno = $this->alumnoEnCurso($this->cursoA);

        $this->guardarAsistencia([
            'detalle_curso_id' => $detalle->id,
            'fecha' => now()->toDateString(),
            'asistencias' => [$alumno->id => ['estado' => 'presente']],
        ])->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ÍNDICE: una fila por detalle_curso + alumno + fecha
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function el_indice_permite_varias_materias_del_mismo_curso_el_mismo_dia(): void
    {
        $this->assertTrue(
            Schema::hasColumns('asistencias', ['detalle_curso_id']),
            'asistencias debe tener detalle_curso_id'
        );

        $word = $this->detalle($this->cursoA, $this->materia('Word', 'MAT00001'));
        $excel = $this->detalle($this->cursoA, $this->materia('Excel', 'MAT00002'));

        $alumno = $this->alumnoEnCurso($this->cursoA);
        $fecha = now()->toDateString();

        foreach ([$word, $excel] as $detalle) {
            Asistencia::create([
                'detalle_curso_id' => $detalle->id,
                'curso_id' => $this->cursoA->id,
                'alumno_id' => $alumno->id,
                'fecha' => now()->startOfDay(),
                'estado' => 'presente',
                'registrado_por' => $this->maestro->id,
            ]);
        }

        $this->assertDatabaseCount('asistencias', 2);
    }

    #[Test]
    public function el_indice_rechaza_duplicado_de_la_misma_materia_alumno_y_fecha(): void
    {
        $detalle = $this->detalle($this->cursoA, $this->materia('Word', 'MAT00001'));
        $alumno = $this->alumnoEnCurso($this->cursoA);
        $fecha = now()->toDateString();

        $base = [
            'detalle_curso_id' => $detalle->id,
            'curso_id' => $this->cursoA->id,
            'alumno_id' => $alumno->id,
            'fecha' => now()->startOfDay(),
            'estado' => 'presente',
            'registrado_por' => $this->maestro->id,
        ];

        Asistencia::create($base);

        // El guardado es idempotente (updateOrCreate), no duplica.
        Asistencia::updateOrCreate(
            ['detalle_curso_id' => $detalle->id, 'alumno_id' => $alumno->id, 'fecha' => now()->startOfDay()],
            ['curso_id' => $this->cursoA->id, 'estado' => 'ausente', 'registrado_por' => $this->maestro->id]
        );

        $this->assertDatabaseCount('asistencias', 1);

        // Y la base sí lo rechaza si se intenta un INSERT duplicado.
        $error = null;

        try {
            DB::table('asistencias')->insert($base + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $error = $e;
        }

        $this->assertNotNull($error, 'el índice único debe rechazar el duplicado');
        $this->assertStringContainsString('UNIQUE', strtoupper($error->getMessage()));
        $this->assertDatabaseCount('asistencias', 1);
    }

    #[Test]
    public function la_migracion_deja_el_indice_por_detalle_curso_instalado(): void
    {
        $indices = collect(DB::select('PRAGMA index_list(asistencias)'))
            ->pluck('name')
            ->all();

        $this->assertContains('asistencias_detalle_curso_alumno_fecha_unique', $indices);
        $this->assertNotContains('asistencias_curso_id_alumno_id_fecha_unique', $indices);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Notas: misma regla de autorización
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function notas_solo_permiten_materias_con_asignacion_vigente(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $excel = $this->materia('Excel', 'MAT00002');

        $detWord = $this->detalle($this->cursoA, $word, $this->maestro);
        $detExcel = $this->detalle($this->cursoA, $excel, $this->maestro);

        $this->asignar($detWord, $this->maestro);
        // $detExcel queda sin asignación.

        $alumno = $this->alumnoEnCurso($this->cursoA);

        $this->actingAs($this->user)
            ->post(route('docente.notas.guardar'), [
                'detalle_curso_id' => $detExcel->id,
                'notas' => [$alumno->id => ['laboratorio' => 8]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('notas', 0);

        $this->actingAs($this->user)
            ->post(route('docente.notas.guardar'), [
                'detalle_curso_id' => $detWord->id,
                'notas' => [$alumno->id => ['laboratorio' => 8]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notas', [
            'detalle_curso_id' => $detWord->id,
            'alumno_id' => $alumno->id,
            'laboratorio' => 8,
        ]);

        $this->assertSame(1, Nota::count());
    }

    #[Test]
    public function notas_no_muestran_materias_sin_asignacion_vigente(): void
    {
        $word = $this->materia('Word', 'MAT00001');
        $this->asignar($this->detalle($this->cursoA, $word, $this->maestro), $this->maestro);
        $this->asignar(
            $this->detalle($this->cursoA, $this->materia('Excel', 'MAT00002'), $this->maestro),
            $this->maestro,
            'finalizada'
        );

        $response = $this->actingAs($this->user)->get(route('docente.notas'));

        $response->assertOk();
        $response->assertSee('Word', false);
        $response->assertDontSee('Excel', false);
    }
}
