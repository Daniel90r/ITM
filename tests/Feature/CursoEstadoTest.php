<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CursoEstadoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function datosUpdate(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Curso Modificado',
            'nivel' => 'Vespertino',
            'seccion' => 'C',
            'anio_lectivo' => 2027,
            'fecha_inicio' => '2026-09-24',
            'fecha_fin' => '2028-09-24',
        ], $overrides);
    }

    #[Test]
    public function test_edit_form_muestra_selector_de_estado_con_proximo_y_activo(): void
    {
        $curso = Curso::factory()->create([
            'nivel' => 'Matutino',
            'seccion' => 'B',
            'anio_lectivo' => 2026,
            'fecha_inicio' => '2026-09-24',
            'fecha_fin' => '2028-09-24',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cursos.edit', $curso->id));

        $response->assertOk();
        $response->assertSee('name="estado"', false);
        $response->assertSee('<option value="PROXIMO"', false);
        $response->assertSee('<option value="ACTIVO"', false);
        $response->assertSee('Próximo', false);
        $response->assertSee('Activo', false);
        $response->assertDontSee('<option value="FINALIZADO"', false);
        $response->assertDontSee('Inactivo', false);
    }

    #[Test]
    public function test_edit_form_no_expone_el_booleano_activo(): void
    {
        $curso = Curso::factory()->create([
            'nivel' => 'Matutino',
            'seccion' => 'B',
            'anio_lectivo' => 2026,
            'fecha_inicio' => '2026-09-24',
            'fecha_fin' => '2028-09-24',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cursos.edit', $curso->id));

        $response->assertOk();
        $response->assertDontSee('name="activo"', false);
    }

    #[Test]
    public function test_store_crea_curso_sin_estado_forzado(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cursos.store'), [
                'nombre' => 'Curso Test',
                'nivel' => 'Matutino',
                'seccion' => 'A',
                'anio_lectivo' => 2026,
                'fecha_inicio' => '2026-09-24',
                'fecha_fin' => '2028-09-24',
            ]);

        $curso = Curso::where('nombre', 'Curso Test')->first();

        $response->assertRedirect(route('admin.cursos'));
        $this->assertNotNull($curso);
        $this->assertTrue($curso->activo);
        $this->assertNull($curso->estado, 'un curso nuevo debe depender de las fechas, sin override');
    }

    #[Test]
    public function test_update_no_cambia_activo_y_preserva_datos(): void
    {
        $curso = Curso::factory()->create([
            'nombre' => 'Curso Original',
            'nivel' => 'Matutino',
            'seccion' => 'B',
            'anio_lectivo' => 2026,
            'fecha_inicio' => '2026-09-24',
            'fecha_fin' => '2028-09-24',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate());

        $response->assertRedirect(route('admin.cursos'));
        $response->assertSessionHas('success');

        $curso->refresh();
        $this->assertEquals('Curso Modificado', $curso->nombre);
        $this->assertEquals('Vespertino', $curso->nivel);
        $this->assertEquals('C', $curso->seccion);
        $this->assertEquals(2027, $curso->anio_lectivo);
        $this->assertTrue($curso->activo, 'activo debe conservarse en true tras editar');
    }

    #[Test]
    public function test_estado_curso_es_calculado_por_fechas(): void
    {
        $proximo = Curso::factory()->create([
            'fecha_inicio' => now()->addDays(10),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
        ]);

        $activo = Curso::factory()->create([
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
        ]);

        $this->assertNull($proximo->estado);
        $this->assertEquals('PROXIMO', $proximo->estado_curso);
        $this->assertEquals('ACTIVO', $activo->estado_curso);
    }

    #[Test]
    public function test_update_persiste_proximo_desde_editar_curso(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
        ]);

        $this->assertEquals('ACTIVO', $curso->estado_curso);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'fecha_inicio' => now()->subDays(5)->format('Y-m-d'),
                'fecha_fin' => now()->addMonths(3)->format('Y-m-d'),
                'estado' => 'PROXIMO',
            ]));

        $response->assertRedirect(route('admin.cursos'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'estado' => 'PROXIMO',
        ]);

        $curso->refresh();
        $this->assertEquals('PROXIMO', $curso->estado_curso);

        $listado = $this->actingAs($this->admin)->get(route('admin.cursos'));
        $listado->assertOk();
        $listado->assertSee('Próximo', false);

        $edicion = $this->actingAs($this->admin)->get(route('admin.cursos.edit', $curso->id));
        $edicion->assertOk();
        $edicion->assertSee('<option value="PROXIMO" selected>', false);
        $edicion->assertDontSee('<option value="ACTIVO" selected>', false);
    }

    #[Test]
    public function test_update_persiste_activo(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->addDays(10),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
        ]);

        $this->assertEquals('PROXIMO', $curso->estado_curso);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'fecha_inicio' => now()->addDays(10)->format('Y-m-d'),
                'fecha_fin' => now()->addMonths(3)->format('Y-m-d'),
                'estado' => 'ACTIVO',
            ]));

        $response->assertRedirect(route('admin.cursos'));

        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'estado' => 'ACTIVO',
        ]);

        $curso->refresh();
        $this->assertEquals('ACTIVO', $curso->estado_curso);
    }

    #[Test]
    public function test_update_rechaza_finalizado_como_valor_de_estado(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'estado' => 'FINALIZADO',
            ]));

        $response->assertSessionHasErrors('estado');

        $curso->refresh();
        $this->assertNotEquals('FINALIZADO', $curso->estado);
        $this->assertEquals('ACTIVO', $curso->estado_curso);
    }

    #[Test]
    public function test_curso_finalizado_no_puede_volver_a_activo_desde_el_formulario(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'finalizado_manualmente' => true,
        ]);

        $this->assertEquals('FINALIZADO', $curso->estado_curso);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'estado' => 'ACTIVO',
            ]));

        $response->assertSessionHas('error');

        $curso->refresh();
        $this->assertTrue($curso->finalizado_manualmente);
        $this->assertEquals('FINALIZADO', $curso->estado_curso);
    }

    #[Test]
    public function test_curso_finalizado_no_revive_moviendo_fecha_fin_al_futuro(): void
    {
        // Finalizado SOLO por fecha (finalizado_manualmente = false): es el
        // caso que el <select disabled> dejaba sin proteger.
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => now()->subDay(),
            'activo' => true,
            'finalizado_manualmente' => false,
        ]);

        $this->assertEquals('FINALIZADO', $curso->estado_curso);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'fecha_inicio' => '2026-09-24',
                'fecha_fin' => '2028-09-24',
            ]));

        $response->assertSessionHas('error');

        $curso->refresh();
        $this->assertEquals('FINALIZADO', $curso->estado_curso);
        $this->assertTrue(now()->startOfDay()->gt($curso->fecha_fin->copy()->endOfDay()));
    }

    #[Test]
    public function test_curso_finalizado_rechaza_estado_proximo_manipulado(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'finalizado_manualmente' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'estado' => 'PROXIMO',
            ]));

        $response->assertSessionHas('error');

        $curso->refresh();
        $this->assertNotEquals('PROXIMO', $curso->estado);
        $this->assertEquals('FINALIZADO', $curso->estado_curso);
    }

    #[Test]
    public function test_curso_finalizado_rechaza_estado_activo_manipulado(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'finalizado_manualmente' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'estado' => 'ACTIVO',
            ]));

        $response->assertSessionHas('error');

        $curso->refresh();
        $this->assertNotEquals('ACTIVO', $curso->estado);
        $this->assertEquals('FINALIZADO', $curso->estado_curso);
    }

    #[Test]
    public function test_curso_activo_sigue_siendo_editable_normalmente(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'finalizado_manualmente' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cursos.update', $curso->id), $this->datosUpdate([
                'fecha_inicio' => $curso->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => '2028-09-24',
            ]));

        $response->assertSessionHas('success');

        $curso->refresh();
        $this->assertEquals('Curso Modificado', $curso->nombre);
        $this->assertEquals('ACTIVO', $curso->estado_curso);
    }

    #[Test]
    public function test_edit_form_deshabilita_el_estado_en_un_curso_finalizado(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'finalizado_manualmente' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cursos.edit', $curso->id));

        $response->assertOk();
        $response->assertSee('<option value="FINALIZADO" selected>', false);
        $response->assertSee('disabled', false);
        $response->assertDontSee('<option value="PROXIMO"', false);
        $response->assertDontSee('<option value="ACTIVO"', false);
    }

    #[Test]
    public function test_finalizado_por_fecha_gana_a_un_override_activo(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subYear(),
            'fecha_fin' => now()->subDay(),
            'activo' => true,
            'estado' => 'ACTIVO',
        ]);

        $this->assertEquals('FINALIZADO', $curso->estado_curso);
    }

    #[Test]
    public function test_override_proximo_gana_a_una_fecha_inicio_ya_pasada(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subDays(30),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'estado' => 'PROXIMO',
        ]);

        $this->assertEquals('PROXIMO', $curso->estado_curso);
    }

    #[Test]
    public function test_listado_muestra_el_estado_guardado(): void
    {
        $curso = Curso::factory()->create([
            'nombre' => 'Curso Estado Test',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'estado' => 'PROXIMO',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cursos'));

        $response->assertOk();
        $response->assertSee('Próximo', false);
        $response->assertDontSee('>Activo<', false);
    }

    #[Test]
    public function test_finalizar_curso_sigue_funcionando(): void
    {
        $curso = Curso::factory()->create([
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => now()->addMonths(3),
            'activo' => true,
            'finalizado_manualmente' => false,
        ]);

        $this->assertEquals('ACTIVO', $curso->estado_curso);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.cursos.finalizar', $curso));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $curso->refresh();
        $this->assertTrue($curso->finalizado_manualmente);
        $this->assertEquals('FINALIZADO', $curso->estado_curso);
    }
}
