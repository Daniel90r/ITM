<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RespaldoBaseDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RespaldoBaseDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => "Admin O'Brien"]);
    }

    public function test_la_pantalla_muestra_el_boton_y_el_estado_vacio(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.respaldos'))
            ->assertOk()
            ->assertSee('Generar copia de seguridad')
            ->assertSee('Todavía no hay copias de seguridad')
            ->assertSee('sidebar-link active', false);
    }

    public function test_genera_un_respaldo_con_estructura_y_datos(): void
    {
        $admin = $this->admin();
        DB::table('sessions')->insert(['id' => 'sesion-secreta', 'user_id' => $admin->id, 'payload' => 'datos-de-sesion', 'last_activity' => time()]);

        $this->actingAs($admin)
            ->post(route('admin.respaldos.store'))
            ->assertRedirect(route('admin.respaldos'))
            ->assertSessionHas('success');

        $archivos = Storage::disk('local')->files(RespaldoBaseDatos::CARPETA);
        $this->assertCount(1, $archivos);
        $this->assertMatchesRegularExpression(RespaldoBaseDatos::PATRON_ARCHIVO, basename($archivos[0]));

        $sql = Storage::disk('local')->get($archivos[0]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `users`;', $sql);
        $this->assertStringContainsString('INSERT INTO `users`', $sql);
        $this->assertStringContainsString("'Admin O''Brien'", $sql, 'Las comillas de los textos se escapan.');
        $this->assertStringContainsString('DROP TABLE IF EXISTS `sessions`;', $sql);
        $this->assertStringNotContainsString('sesion-secreta', $sql, 'Las sesiones activas no se respaldan.');
        $this->assertStringEndsWith("-- Fin del respaldo\n", $sql);
    }

    public function test_el_respaldo_se_puede_restaurar(): void
    {
        $admin = $this->admin();
        $nombre = app(RespaldoBaseDatos::class)->generar();
        $sql = Storage::disk('local')->get(RespaldoBaseDatos::CARPETA . '/' . $nombre);

        DB::table('users')->delete();
        $this->assertSame(0, DB::table('users')->count());

        DB::unprepared($sql);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'email' => $admin->email, 'role' => 'admin']);
    }

    public function test_lista_descarga_y_elimina_respaldos(): void
    {
        $admin = $this->admin();
        $nombre = app(RespaldoBaseDatos::class)->generar();

        $this->actingAs($admin)->get(route('admin.respaldos'))
            ->assertOk()->assertSee($nombre)->assertSee('Descargar');

        $this->get(route('admin.respaldos.descargar', $nombre))
            ->assertOk()
            ->assertDownload($nombre);

        $this->delete(route('admin.respaldos.destroy', $nombre))
            ->assertRedirect(route('admin.respaldos'));
        Storage::disk('local')->assertMissing(RespaldoBaseDatos::CARPETA . '/' . $nombre);
    }

    public function test_rechaza_nombres_de_archivo_ajenos_a_los_respaldos(): void
    {
        Storage::disk('local')->put('respaldos/otro.sql', 'x');
        Storage::disk('local')->put('secreto.txt', 'x');
        $this->actingAs($this->admin());

        $this->get(route('admin.respaldos.descargar', 'otro.sql'))->assertNotFound();
        $this->get('/admin/respaldos/..%2Fsecreto.txt/descargar')->assertNotFound();
        $this->get(route('admin.respaldos.descargar', 'respaldo-itm-2026-01-01_000000.sql'))->assertNotFound();
        $this->delete(route('admin.respaldos.destroy', 'otro.sql'))->assertNotFound();
        Storage::disk('local')->assertExists('respaldos/otro.sql');
    }

    public static function rolesSinAcceso(): array
    {
        return [['docente'], ['alumno']];
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_otros_roles_no_acceden(string $rol): void
    {
        $nombre = app(RespaldoBaseDatos::class)->generar();
        $this->actingAs(User::factory()->create(['role' => $rol]));

        $this->get(route('admin.respaldos'))->assertForbidden();
        $this->post(route('admin.respaldos.store'))->assertForbidden();
        $this->get(route('admin.respaldos.descargar', $nombre))->assertForbidden();
        $this->delete(route('admin.respaldos.destroy', $nombre))->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->files(RespaldoBaseDatos::CARPETA));
    }

    public function test_visitante_sin_sesion_es_enviado_al_login(): void
    {
        $this->get(route('admin.respaldos'))->assertRedirect(route('login'));
        $this->post(route('admin.respaldos.store'))->assertRedirect(route('login'));
    }
}
