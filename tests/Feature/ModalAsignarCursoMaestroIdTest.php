<?php

namespace Tests\Feature;

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Materia;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * El modal "Asignar programas al maestro" necesita saber a QUIÉN se asigna.
 *
 * El endpoint materiasPorCurso lee `maestro_id` de la query para separar
 * "esta materia ya es de este maestro" de "esta materia es de otro maestro".
 * Si el frontend no lo envía, el modal muestra como bloqueada una materia que
 * en realidad ya le pertenece al maestro que se está editando.
 */
class ModalAsignarCursoMaestroIdTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Curso $curso;

    private static int $secuenciaMaestro = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->curso = Curso::create([
            'nombre' => 'Curso A',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
            'activo' => true,
        ]);
    }

    private function materia(string $nombre, string $codigo): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activa' => true,
        ]);
    }

    private function maestro(string $nombre = 'Carlos'): Maestro
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

    private function detalle(Materia $materia): DetalleCurso
    {
        return DetalleCurso::create([
            'curso_id' => $this->curso->id,
            'materia_id' => $materia->id,
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

    private function respuesta(?int $maestroId = null)
    {
        $parametros = ['curso_id' => $this->curso->id];

        if ($maestroId !== null) {
            $parametros['maestro_id'] = $maestroId;
        }

        return $this->actingAs($this->admin)
            ->getJson(route('admin.maestros.materiasPorCurso', $parametros));
    }

    private function materiaEn(array $materias, string $codigo): array
    {
        $encontrada = collect($materias)->firstWhere('codigo', $codigo);

        $this->assertNotNull($encontrada, "La materia {$codigo} no vino en la respuesta.");

        return $encontrada;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Backend
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_materia_ya_asignada_al_mismo_maestro_se_identifica_como_suya(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $maestro = $this->maestro();

        $this->asignar($this->detalle($materia), $maestro);

        $response = $this->respuesta($maestro->id);
        $response->assertOk();

        $item = $this->materiaEn($response->json('materias'), 'WRD');

        $this->assertTrue($item['ya_asignada_a_este_maestro']);
        $this->assertFalse($item['tiene_maestro_actual']);
    }

    #[Test]
    public function test_materia_asignada_a_otro_maestro_sigue_bloqueada(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $mio     = $this->maestro('Carlos');
        $otro    = $this->maestro('Ana');

        $this->asignar($this->detalle($materia), $otro);

        $response = $this->respuesta($mio->id);
        $response->assertOk();

        $item = $this->materiaEn($response->json('materias'), 'WRD');

        $this->assertFalse($item['ya_asignada_a_este_maestro']);
        $this->assertTrue($item['tiene_maestro_actual']);
        $this->assertNotNull($item['maestro_actual']);
    }

    #[Test]
    public function test_materia_sin_maestro_actual_queda_seleccionable(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $maestro = $this->maestro();

        $this->detalle($materia);

        $response = $this->respuesta($maestro->id);
        $response->assertOk();

        $item = $this->materiaEn($response->json('materias'), 'WRD');

        $this->assertFalse($item['tiene_maestro_actual']);
        $this->assertFalse($item['ya_asignada_a_este_maestro']);
    }

    #[Test]
    public function test_sin_maestro_id_la_materia_propia_aparece_como_bloqueada(): void
    {
        // Compatibilidad: si el frontend no manda el parámetro, el backend
        // sigue respondiendo, pero sin la precisión de "ya es tuya".
        $materia = $this->materia('Microsoft Word', 'WRD');
        $maestro = $this->maestro();

        $this->asignar($this->detalle($materia), $maestro);

        $response = $this->respuesta();
        $response->assertOk();

        $item = $this->materiaEn($response->json('materias'), 'WRD');

        $this->assertFalse($item['ya_asignada_a_este_maestro']);
        $this->assertTrue($item['tiene_maestro_actual']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Frontend
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_el_modal_expone_el_id_del_maestro_para_el_fetch(): void
    {
        $maestro = $this->maestro();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.maestros.show', $maestro->id));

        $response->assertOk();
        $response->assertSee('data-maestro-id="' . $maestro->id . '"', false);
    }

    #[Test]
    public function test_el_js_envia_maestro_id_al_pedir_las_materias(): void
    {
        $js = file_get_contents(base_path('resources/js/admin/modals.js'));

        $this->assertStringContainsString("params.set('maestro_id', maestroId)", $js);
        $this->assertStringContainsString('materias-por-curso/', $js);
        $this->assertStringContainsString('URLSearchParams', $js);
    }
}
