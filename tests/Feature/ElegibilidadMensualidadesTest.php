<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, Mensualidad, Pago, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ElegibilidadMensualidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public static function estados(): array
    {
        return [['pendiente'], ['rechazada'], ['inactiva'], [null]];
    }

    private function inscribir(Alumno $alumno, string $estado, ?Curso $curso = null): Inscripcion
    {
        return Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => ($curso ?? Curso::factory()->create(['activo' => true]))->id,
            'estado' => $estado, 'activa' => $estado === 'aprobada']);
    }

    #[DataProvider('estados')]
    public function test_sin_aprobacion_ni_obligaciones_no_aparece_ni_accede_por_id(?string $estado): void
    {
        $alumno = Alumno::factory()->create(['activo' => true]);
        if ($estado !== null) $this->inscribir($alumno, $estado);
        $this->get(route('admin.pagos.historial.index', ['q' => $alumno->codigo]))->assertOk()
            ->assertViewHas('alumnos', fn ($alumnos) => $alumnos->total() === 0);
        $this->get(route('admin.pagos.estado-cuenta', $alumno))->assertNotFound();
        $this->get(route('admin.mensualidades'))->assertOk()->assertViewHas('listado', fn ($listado) => $listado->total() === 0);
        $this->assertDatabaseCount('mensualidades', 0);
        $this->assertDatabaseCount('pagos', 0);
    }

    #[DataProvider('estados')]
    public function test_generacion_directa_sin_aprobacion_es_rechazada(?string $estado): void
    {
        $alumno = Alumno::factory()->create();
        if ($estado !== null) $this->inscribir($alumno, $estado);
        $this->postJson(route('admin.mensualidades.generar'), ['mes' => 'Octubre', 'anio' => 2026, 'tipo' => 'uno', 'alumno_id' => $alumno->id])
            ->assertUnprocessable()->assertJsonValidationErrors('alumno_id');
        $this->assertDatabaseCount('mensualidades', 0);
    }

    public function test_aprobado_sin_mensualidades_puede_consultar_sin_inventar_deuda(): void
    {
        $alumno = Alumno::factory()->create();
        $this->inscribir($alumno, 'aprobada');
        $this->get(route('admin.pagos.historial.index', ['q' => $alumno->codigo]))->assertOk()
            ->assertViewHas('alumnos', fn ($alumnos) => $alumnos->total() === 1);
        $this->get(route('admin.pagos.estado-cuenta', $alumno))->assertOk()->assertSee('Sin mensualidad registrada')
            ->assertViewHas('resumen', fn ($resumen) => $resumen['pagado'] === 0 && $resumen['pendiente'] === 0);
        $this->assertDatabaseCount('mensualidades', 0);
    }

    public function test_retirado_con_pago_historico_y_nueva_solicitud_conserva_historial(): void
    {
        $alumno = Alumno::factory()->create(['activo' => false]);
        $anterior = $this->inscribir($alumno, 'inactiva');
        $pendiente = $this->inscribir($alumno, 'pendiente');
        $mensualidad = Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $anterior->curso_id,
            'mes' => 'Junio', 'anio' => 2025, 'estado' => 'Pagado', 'monto' => 25]);
        $pago = Pago::create(['mensualidad_id' => $mensualidad->id, 'fecha_pago' => '2025-06-15', 'monto_pagado' => 25]);
        $antes = [$mensualidad->fresh()->getAttributes(), $pago->fresh()->getAttributes()];
        $this->get(route('admin.pagos.historial.index', ['q' => $alumno->codigo]))->assertOk()
            ->assertViewHas('alumnos', fn ($alumnos) => $alumnos->total() === 1);
        $this->get(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2025]))->assertOk()
            ->assertViewHas('resumen', fn ($resumen) => $resumen['pagado'] === 2500)->assertSee('15/06/2025');
        $this->postJson(route('admin.mensualidades.generar'), ['mes' => 'Octubre', 'anio' => 2026, 'tipo' => 'uno', 'alumno_id' => $alumno->id])->assertUnprocessable();
        $this->assertSame($antes, [$mensualidad->fresh()->getAttributes(), $pago->fresh()->getAttributes()]);
        $this->assertSame('pendiente', $pendiente->fresh()->estado);
        $this->assertDatabaseCount('mensualidades', 1);
        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_aprobaciones_multiples_no_duplican_alumno_ni_generacion(): void
    {
        $alumno = Alumno::factory()->create(['activo' => false]);
        $this->inscribir($alumno, 'aprobada');
        $this->inscribir($alumno, 'aprobada');
        $this->inscribir($alumno, 'pendiente');
        $this->get(route('admin.pagos.historial.index'))->assertOk()
            ->assertViewHas('alumnos', fn ($alumnos) => $alumnos->total() === 1);
        foreach (range(1, 2) as $_) {
            $this->post(route('admin.mensualidades.generar'), ['mes' => 'Octubre', 'anio' => 2026, 'tipo' => 'uno', 'alumno_id' => $alumno->id])
                ->assertRedirect(route('admin.mensualidades'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('mensualidades', 1);
        $this->assertDatabaseHas('mensualidades', ['alumno_id' => $alumno->id, 'monto' => 25, 'estado' => 'Pendiente']);
    }

    public function test_aprobada_historica_y_pendiente_nueva_no_ocultan_obligaciones(): void
    {
        $alumno = Alumno::factory()->create();
        $historica = $this->inscribir($alumno, 'aprobada');
        $historica->update(['activa' => false, 'fecha_inscripcion' => '2025-01-15']);
        $this->inscribir($alumno, 'pendiente');
        $mensualidad = Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $historica->curso_id,
            'anio' => 2025, 'mes' => 'Enero', 'estado' => 'Pendiente', 'monto' => 25]);
        $this->get(route('admin.pagos.historial.index'))->assertOk()
            ->assertViewHas('alumnos', fn ($alumnos) => $alumnos->total() === 1);
        $this->get(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2025]))->assertOk()
            ->assertSee('data-obligacion="'.$mensualidad->id.'"', false)
            ->assertViewHas('resumen', fn ($resumen) => $resumen['pendiente'] === 2500);
        $this->assertDatabaseCount('mensualidades', 1);
    }

    public function test_generacion_por_curso_y_masiva_excluyen_solicitudes(): void
    {
        $curso = Curso::factory()->create(['activo' => true]);
        $aprobado = Alumno::factory()->create();
        $this->inscribir($aprobado, 'aprobada', $curso);
        foreach (['pendiente', 'rechazada'] as $estado) $this->inscribir(Alumno::factory()->create(), $estado, $curso);
        foreach (['curso', 'todos'] as $tipo) {
            $this->post(route('admin.mensualidades.generar'), ['mes' => 'Octubre', 'anio' => 2026, 'tipo' => $tipo, 'curso_id' => $curso->id])
                ->assertRedirect(route('admin.mensualidades'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('mensualidades', 1);
        $this->assertDatabaseHas('mensualidades', ['alumno_id' => $aprobado->id]);
    }

    public static function lotesSinAprobacion(): array
    {
        return [['curso', 'curso_id'], ['todos', 'tipo']];
    }

    #[DataProvider('lotesSinAprobacion')]
    public function test_lote_sin_aprobados_no_informa_exito(string $tipo, string $campo): void
    {
        $curso = Curso::factory()->create();
        $this->inscribir(Alumno::factory()->create(), 'pendiente', $curso);
        $this->postJson(route('admin.mensualidades.generar'), ['mes' => 'Octubre', 'anio' => 2026, 'tipo' => $tipo, 'curso_id' => $curso->id])
            ->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertDatabaseCount('mensualidades', 0);
    }
}
