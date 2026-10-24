<?php

namespace Tests\Feature;

use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaestrosListadoInterfazTest extends TestCase
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

    public static function cantidades(): array
    {
        return [[0, '0 maestros registrados'], [1, '1 maestro registrado'], [2, '2 maestros registrados']];
    }

    #[DataProvider('cantidades')]
    public function test_contador_singular_plural_y_estado_vacio(int $cantidad, string $texto): void
    {
        Maestro::factory()->count($cantidad)->create();
        $response = $this->get(route('admin.maestros'))->assertOk()->assertSee($texto);
        if ($cantidad === 0) {
            $response->assertSee('No hay maestros registrados.');
        }
        $response->assertDontSee('1 maestros registrados')->assertDontSee('.estado-persona', false);
    }

    public function test_acciones_preservan_destinos_datos_de_edicion_y_estados(): void
    {
        $activo = Maestro::factory()->create(['activo' => true, 'nombre' => 'Nombre extenso de prueba', 'apellido' => 'Apellido completo sin truncar']);
        $inactivo = Maestro::factory()->create(['activo' => false]);
        $antes = Maestro::orderBy('id')->get()->toJson();
        $response = $this->get(route('admin.maestros'))->assertOk();
        $response->assertSee($activo->nombre_completo)->assertSee('Gestionar programas')
            ->assertSee('Más acciones')->assertSee('Estado y acceso')
            ->assertSee(route('admin.maestros.show', $activo->id), false)
            ->assertSee(route('admin.personas.estado', ['maestros', $activo->id]), false)
            ->assertSee(route('admin.personas.estado', ['maestros', $inactivo->id]), false)
            ->assertSee('gm-active', false)->assertSee('gm-inactive', false)
            ->assertSee('popover="auto"', false)->assertSee('aria-haspopup="menu"', false)
            ->assertSee('abrirModalEditarMaestro(', false)->assertSee('id="modalEditarMaestro"', false)
            ->assertSee('id="buscadorMaestro"', false)->assertSee('id="sinResultados"', false);
        $this->assertSame($antes, Maestro::orderBy('id')->get()->toJson());
        $this->assertStringContainsString('@js($maestro->telefono), @js($maestro->dui), @js($maestro->codigo), @js($maestro->user?->email', file_get_contents(resource_path('views/admin/maestros.blade.php')));
    }

    public function test_materias_conservan_tres_etiquetas_y_contador_adicional(): void
    {
        $maestro = Maestro::factory()->create();
        foreach (range(1, 4) as $numero) {
            DetalleCurso::factory()->create(['maestro_id' => $maestro->id, 'materia_id' => Materia::factory()->create(['nombre' => 'Materia de prueba '.$numero])->id]);
        }
        $this->get(route('admin.maestros'))->assertOk()->assertSee('Materia de prueba 1')->assertSee('Materia de prueba 3')
            ->assertDontSee('Materia de prueba 4')->assertSee('+1 más')->assertSee('4 asignaciones');
        $this->assertDatabaseCount('detalle_cursos', 4);
    }
}
