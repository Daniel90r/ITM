<?php

namespace Tests\Feature;

use App\Models\Director;
use App\Models\User;
use App\Services\DirectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DirectorHistoryTest extends TestCase
{
    use RefreshDatabase;

    private DirectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectorService();
    }

    private function columnaInfo(string $columna): ?array
    {
        $pdo = Schema::getConnection()->getPdo();
        $cols = $pdo->query('PRAGMA table_info(directores)')->fetchAll(\PDO::FETCH_ASSOC);

        return collect($cols)->firstWhere('name', $columna);
    }

    public function test_migracion_agrega_los_campos_esperados_a_directores(): void
    {
        $this->assertTrue(Schema::hasTable('directores'));

        foreach (['user_id', 'nombre', 'dui', 'telefono', 'email', 'firma_path', 'fecha_inicio', 'fecha_fin', 'activo'] as $columna) {
            $this->assertTrue(
                Schema::hasColumn('directores', $columna),
                "La columna '{$columna}' no existe en directores."
            );
        }

        // user_id: nullable (notnull = 0) y sin default propio
        $userIdCol = $this->columnaInfo('user_id');
        $this->assertNotNull($userIdCol, 'user_id debe existir.');
        $this->assertSame(0, (int) $userIdCol['notnull'], 'user_id debe ser nullable.');

        // activo: boolean con default false (verificado conductualmente,
        // pues SQLite representa el default de forma no portátil).
        $sinActivo = Director::create([
            'nombre'       => 'SinActivo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->assertFalse((bool) $sinActivo->activo);
    }

    public function test_se_puede_crear_un_director_historico(): void
    {
        $director = Director::create([
            'nombre'       => 'Director Antiguo',
            'fecha_inicio' => '2024-01-01',
            'fecha_fin'    => '2025-12-31',
            'activo'       => false,
        ]);

        $this->assertDatabaseHas('directores', [
            'id'      => $director->id,
            'nombre'  => 'Director Antiguo',
            'activo'  => 0,
        ]);

        $this->assertSame('2024-01-01', $director->fecha_inicio->toDateString());
        $this->assertSame('2025-12-31', $director->fecha_fin->toDateString());
        $this->assertFalse((bool) $director->activo);
    }

    public function test_scope_activo_devuelve_solo_el_director_vigente(): void
    {
        Director::create([
            'nombre'       => 'Director Antiguo',
            'fecha_inicio' => '2024-01-01',
            'fecha_fin'    => '2025-12-31',
            'activo'       => false,
        ]);
        Director::create([
            'nombre'       => 'Director Vigente',
            'fecha_inicio' => '2026-01-01',
            'activo'       => true,
        ]);

        $activos = Director::activo()->get();

        $this->assertCount(1, $activos);
        $this->assertSame('Director Vigente', $activos->first()->nombre);
    }

    public function test_nombrar_primer_director_crea_un_registro_activo(): void
    {
        $director = $this->service->nombrar([
            'nombre'       => 'Primero',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->assertSame(1, Director::count());
        $this->assertTrue($director->activo);
        $this->assertNull($director->fecha_fin);
        $this->assertSame('2026-01-01', $director->fecha_inicio->toDateString());
    }

    public function test_nombrar_segundo_director_cierra_al_anterior_correctamente(): void
    {
        Director::create([
            'nombre'       => 'Director A',
            'fecha_inicio' => '2024-01-01',
            'activo'       => true,
            'fecha_fin'    => null,
        ]);

        $nuevo = $this->service->nombrar([
            'nombre'       => 'Director B',
            'fecha_inicio' => '2026-01-01',
        ]);

        $anterior = Director::where('nombre', 'Director A')->firstOrFail()->fresh();

        $this->assertFalse($anterior->activo);
        $this->assertSame('2025-12-31', $anterior->fecha_fin->toDateString());

        $this->assertTrue($nuevo->activo);
        $this->assertNull($nuevo->fecha_fin);
        $this->assertSame('2026-01-01', $nuevo->fecha_inicio->toDateString());
    }

    public function test_nunca_quedan_dos_directores_activos(): void
    {
        $this->service->nombrar(['nombre' => 'A', 'fecha_inicio' => '2026-01-01']);
        $this->service->nombrar(['nombre' => 'B', 'fecha_inicio' => '2027-01-01']);

        $this->assertSame(1, Director::where('activo', true)->count());
    }

    public function test_misma_persona_puede_ser_directora_en_otro_periodo(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->service->nombrar([
            'nombre'       => 'Santos David Rodas',
            'dui'          => '12345678-9',
            'user_id'      => $user->id,
            'fecha_inicio' => '2024-01-01',
        ]);

        $this->service->nombrar([
            'nombre'       => 'Santos David Rodas',
            'dui'          => '12345678-9',
            'user_id'      => $user->id,
            'fecha_inicio' => '2028-01-01',
        ]);

        $this->assertSame(
            2,
            Director::where('nombre', 'Santos David Rodas')
                ->where('dui', '12345678-9')
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_al_eliminar_el_user_relacionado_el_director_queda_con_user_id_null(): void
    {
        $user = User::factory()->create();

        $director = Director::create([
            'nombre'       => 'Con Usuario',
            'user_id'      => $user->id,
            'fecha_inicio' => '2026-01-01',
            'activo'       => true,
        ]);

        $user->delete();
        $director->refresh();

        $this->assertNull($director->user_id);
        $this->assertDatabaseHas('directores', ['id' => $director->id]);
    }

    public function test_no_se_permite_nombrar_director_con_fecha_anterior_al_vigente(): void
    {
        $this->service->nombrar([
            'nombre'       => 'Vigente',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->nombrar([
            'nombre'       => 'Incorrecto',
            'fecha_inicio' => '2025-12-01',
        ]);
    }

    public function test_no_se_aceptan_activo_ni_fecha_fin_forzados_por_el_consumidor(): void
    {
        $director = $this->service->nombrar([
            'nombre'       => 'Forzado',
            'fecha_inicio' => '2026-01-01',
            'activo'       => false,
            'fecha_fin'    => '2099-12-31',
        ]);

        $this->assertTrue($director->activo);
        $this->assertNull($director->fecha_fin);
    }

    private function migracionDirectorHistory(): object
    {
        return require database_path('migrations/2026_10_07_023000_add_history_fields_to_directores_table.php');
    }

    public function test_migracion_history_fields_es_reversible_up_and_down(): void
    {
        $columnas = ['user_id', 'nombre', 'dui', 'telefono', 'email', 'firma_path', 'fecha_inicio', 'fecha_fin', 'activo'];

        // 1) Después de RefreshDatabase las columnas nuevas existen.
        foreach ($columnas as $c) {
            $this->assertTrue(Schema::hasColumn('directores', $c), "Pre down: '{$c}' debe existir.");
        }

        $migracion = $this->migracionDirectorHistory();

        // 3) Ejecutar down().
        $migracion->down();

        // 4) La tabla directores sigue viva pero sin las nueve columnas.
        $this->assertTrue(Schema::hasTable('directores'));
        foreach ($columnas as $c) {
            $this->assertFalse(Schema::hasColumn('directores', $c), "Post down: '{$c}' NO debe existir.");
        }

        // 5) Ejecutar up().
        $migracion->up();

        // 6) Las nueve columnas vuelven a existir.
        foreach ($columnas as $c) {
            $this->assertTrue(Schema::hasColumn('directores', $c), "Post up: '{$c}' debe existir.");
        }

        // 7) El comportamiento ON DELETE SET NULL persiste tras up().
        $user = User::factory()->create();

        $director = Director::create([
            'nombre'       => 'Con Usuario',
            'user_id'      => $user->id,
            'fecha_inicio' => '2026-01-01',
            'activo'       => true,
        ]);

        $user->delete();
        $director->refresh();

        $this->assertNull($director->user_id);
        $this->assertDatabaseHas('directores', ['id' => $director->id]);
    }
}
