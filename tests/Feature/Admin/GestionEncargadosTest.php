<?php

namespace Tests\Feature\Admin;

use App\Models\{Alumno, Encargado, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GestionEncargadosTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::create(2026, 10, 16, 12));
    }

    private function alumno(array $datos = []): Alumno
    {
        return Alumno::factory()->create(array_merge([
            'nombre' => 'Jennifer Prueba', 'apellido' => 'Apellido Prueba',
            'fecha_nacimiento' => '2010-10-16',
        ], $datos));
    }

    private function encargado(Alumno $alumno, array $datos = [], string $parentesco = 'Madre'): Encargado
    {
        $encargado = Encargado::create(array_merge([
            'nombre' => 'Ana Maria', 'apellido' => 'Guardiana Prueba',
            'dui' => '12345678-9', 'telefono' => '7777-8888',
            'email' => 'encargada@example.test', 'activo' => true,
        ], $datos));
        $alumno->encargados()->attach($encargado->id, ['parentesco' => $parentesco]);

        return $encargado;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_rutas_de_ambas_pestanas_tienen_nombre_url_y_acceso_admin_correctos(): void
    {
        $this->admin();
        $this->encargado($this->alumno());
        $rutas = [
            'admin.alumnos' => '/admin/alumnos',
            'admin.encargados.index' => '/admin/alumnos/encargados',
        ];

        foreach ($rutas as $nombre => $url) {
            $ruta = app('router')->getRoutes()->getByName($nombre);
            $this->assertNotNull($ruta, "Falta la ruta {$nombre}.");
            $this->assertSame(ltrim($url, '/'), $ruta->uri());
            $this->assertContains('GET', $ruta->methods());
            $this->assertContains('auth', $ruta->gatherMiddleware());
            $this->assertContains('role:admin', $ruta->gatherMiddleware());
            $this->assertSame($url, route($nombre, [], false));

            $respuesta = $this->get($url)->assertOk();
            foreach ($rutas as $destino => $path) {
                $respuesta->assertSee('href="'.route($destino).'"', false);
            }
        }
    }

    public function test_invitado_es_redirigido_sin_datos_personales(): void
    {
        $this->encargado($this->alumno());
        $this->get(route('admin.encargados.index'))->assertRedirect(route('login'))
            ->assertDontSee('Jennifer')->assertDontSee('12345678-9');
    }

    public static function rolesNoAutorizados(): array
    {
        return [['alumno'], ['docente'], ['director']];
    }

    #[DataProvider('rolesNoAutorizados')]
    public function test_solo_admin_puede_consultar(string $rol): void
    {
        // El esquema puede restringir los roles; director se verifica sin persistirlo.
        $usuario = User::factory()->create(['role' => 'alumno']);
        $usuario->role = $rol;
        $this->encargado($this->alumno());
        $this->actingAs($usuario)->get(route('admin.encargados.index'))
            ->assertForbidden()->assertDontSee('Jennifer')->assertDontSee('12345678-9');
    }

    public function test_admin_ve_tabla_modal_y_pestanas_sin_acciones_de_escritura(): void
    {
        $this->admin();
        $alumno = $this->alumno(['codigo' => 'ALM-MENOR']);
        $this->encargado($alumno, [], 'Tutor');
        $respuesta = $this->get(route('admin.encargados.index'))->assertOk()
            ->assertSee('Gestión de Encargados')->assertSee('Jennifer Prueba Apellido Prueba')
            ->assertSee('ALM-MENOR')->assertSee('16 años')->assertSee('Ana Maria Guardiana Prueba')
            ->assertSee('Tutor')->assertSee('Activo')->assertSee('Ver datos')
            ->assertSee('id="modalDatosEncargado"', false)->assertSee('$refs.modal.showModal()', false)
            ->assertSee('x-text="detalle.dui', false)->assertSee('x-text="detalle.email', false)
            ->assertDontSee('Editar')->assertDontSee('Eliminar')->assertDontSee('Guardar');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('12345678-9', $respuesta->getContent());
        $this->assertStringContainsString('encargada@example.test', $respuesta->getContent());
        $this->assertStringContainsString('7777-8888', $respuesta->getContent());
        $respuesta->assertSee(route('admin.alumnos'), false);
        $this->get(route('admin.alumnos'))->assertOk()->assertSee(route('admin.encargados.index'), false);
    }

    public function test_limite_de_edad_excluye_mayores_fecha_desconocida_y_futura(): void
    {
        $this->admin();
        $menor = $this->alumno(['fecha_nacimiento' => '2008-10-17']);
        $recienNacido = $this->alumno(['fecha_nacimiento' => '2026-10-16']);
        foreach (['2008-10-16', '2000-01-01', null, '2026-10-17'] as $fecha) {
            $adulto = $this->alumno(['fecha_nacimiento' => $fecha]);
            $this->encargado($adulto, ['nombre' => 'Adulto Encargado Oculto']);
        }
        $respuesta = $this->get(route('admin.encargados.index'))->assertOk()->assertDontSee('Adulto Encargado Oculto');
        $this->assertEqualsCanonicalizing([$menor->id, $recienNacido->id], $respuesta->viewData('alumnos')->pluck('id')->all());
        $respuesta->assertSee('17 años')->assertSee('0 años');
    }

    public function test_menor_sin_encargado_permanece_en_el_listado(): void
    {
        $this->admin();
        $this->alumno(['codigo' => 'SIN-VINCULO']);
        $this->get(route('admin.encargados.index'))->assertOk()->assertSee('SIN-VINCULO')
            ->assertSee('Sin encargado registrado')->assertDontSee('Ver datos</button>', false);
    }

    public function test_todos_los_vinculos_y_estados_se_muestran_con_parentesco_del_pivote(): void
    {
        $this->admin();
        $alumno = $this->alumno();
        $primero = $this->encargado($alumno, ['nombre' => 'Primera Encargada'], 'Madre');
        $segundo = $this->encargado($alumno, ['nombre' => 'Segundo Encargado', 'activo' => false], 'Abuelo');
        $otro = $this->alumno(['nombre' => 'Otro Menor']);
        $otro->encargados()->attach($primero->id, ['parentesco' => 'Tutor']);
        $respuesta = $this->get(route('admin.encargados.index'))->assertOk()
            ->assertSee('Primera Encargada')->assertSee('Segundo Encargado')->assertSee('Madre')
            ->assertSee('Abuelo')->assertSee('Tutor')->assertSee('Inactivo')->assertSee('Activo');
        $this->assertSame(3, substr_count($respuesta->getContent(), '>Ver datos</button>'));
        $this->assertSame([$primero->id, $segundo->id], $respuesta->viewData('alumnos')->firstWhere('id', $alumno->id)->encargados->pluck('id')->all());
    }

    public static function busquedas(): array
    {
        return [['Jennifer'], ['Jennifer Prueba Apellido'], ['Apellido Prueba'], ['COD-UNICO'], ['Ana Maria'], ['Guardiana Prueba']];
    }

    #[DataProvider('busquedas')]
    public function test_busqueda_por_alumno_codigo_y_encargado(string $busqueda): void
    {
        $this->admin();
        $menor = $this->alumno(['codigo' => 'COD-UNICO']);
        $this->encargado($menor);
        $this->alumno(['nombre' => 'Sin Coincidencia', 'apellido' => 'Diferente', 'codigo' => 'OTRO']);
        $adulto = $this->alumno(['fecha_nacimiento' => '1990-01-01', 'codigo' => 'COD-ADULTO']);
        $this->encargado($adulto);
        $respuesta = $this->get(route('admin.encargados.index', ['q' => $busqueda]))->assertOk();
        $this->assertSame([$menor->id], $respuesta->viewData('alumnos')->pluck('id')->all());
        $respuesta->assertDontSee('COD-ADULTO')->assertDontSee('Sin Coincidencia');
    }

    public function test_buscar_un_encargado_conserva_todos_los_vinculos_del_alumno(): void
    {
        $this->admin();
        $menor = $this->alumno();
        $this->encargado($menor, ['nombre' => 'Coincidencia']);
        $this->encargado($menor, ['nombre' => 'Otro Vinculado'], 'Padre');
        $this->get(route('admin.encargados.index', ['q' => 'Coincidencia']))->assertOk()
            ->assertSee('Coincidencia')->assertSee('Otro Vinculado')->assertSee('Padre');
    }

    public function test_busqueda_literal_sin_resultados_y_limpieza(): void
    {
        $this->admin();
        $this->alumno();
        foreach (['NoExiste', '%', '_', "' OR 1=1 --"] as $busqueda) {
            $respuesta = $this->get(route('admin.encargados.index', ['q' => $busqueda]))->assertOk();
            $this->assertSame(0, $respuesta->viewData('alumnos')->total());
            $respuesta->assertSee('No se encontraron alumnos menores')->assertSee('Limpiar');
        }
        $this->assertSame(1, $this->get(route('admin.encargados.index'))->viewData('alumnos')->total());
    }

    public function test_paginacion_conserva_busqueda_y_no_pierde_vinculos(): void
    {
        $this->admin();
        for ($i = 0; $i < 21; $i++) {
            $this->alumno(['apellido' => sprintf('Familia %02d', $i)]);
        }
        $primera = $this->get(route('admin.encargados.index', ['q' => 'Familia']))->assertOk();
        $this->assertSame(21, $primera->viewData('alumnos')->total());
        $this->assertCount(20, $primera->viewData('alumnos'));
        $this->assertStringContainsString('q=Familia', $primera->viewData('alumnos')->nextPageUrl());
        $segunda = $this->get(route('admin.encargados.index', ['q' => 'Familia', 'page' => 2]))->assertOk();
        $this->assertCount(1, $segunda->viewData('alumnos'));
        $this->assertEmpty(array_intersect($primera->viewData('alumnos')->pluck('id')->all(), $segunda->viewData('alumnos')->pluck('id')->all()));
    }

    public function test_consulta_no_modifica_expedientes_y_carga_relacion_sin_n_mas_uno(): void
    {
        $this->admin();
        $primero = $this->alumno();
        $encargado = $this->encargado($primero);
        $this->encargado($this->alumno(['nombre' => 'Otro Menor']));
        $antes = [DB::table('alumnos')->get()->toJson(), DB::table('encargados')->get()->toJson(), DB::table('alumno_encargado')->get()->toJson()];
        DB::enableQueryLog();
        $respuesta = $this->get(route('admin.encargados.index'))->assertOk();
        $consultasRelacion = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], '"encargados"'));
        DB::disableQueryLog();
        $this->assertCount(1, $consultasRelacion);
        foreach ($respuesta->viewData('alumnos') as $alumno) {
            $this->assertTrue($alumno->relationLoaded('encargados'));
        }
        $despues = [DB::table('alumnos')->get()->toJson(), DB::table('encargados')->get()->toJson(), DB::table('alumno_encargado')->get()->toJson()];
        $this->assertSame($antes, $despues);
        $this->post(route('admin.encargados.index'), ['encargado_id' => $encargado->id, 'nombre' => 'Manipulado'])->assertStatus(405);
        $this->assertSame('Ana Maria', $encargado->fresh()->nombre);
    }

    public function test_datos_especiales_se_escapan_y_modal_sigue_siendo_solo_lectura(): void
    {
        $this->admin();
        $nombre = 'O\'Connor <script>alert("x")</script>';
        $this->encargado($this->alumno(), ['nombre' => $nombre, 'telefono' => null, 'email' => null]);
        $respuesta = $this->get(route('admin.encargados.index'))->assertOk();
        $respuesta->assertDontSee('<script>alert("x")</script>', false)->assertSee($nombre);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $modal = '//*[@id="modalDatosEncargado"]';
        $this->assertSame(0, $xpath->query($modal.'//input|'.$modal.'//textarea|'.$modal.'//select')->length);
        $this->assertSame(7, $xpath->query($modal.'//dd[@x-text]')->length);
        $expresion = $xpath->query('//button[@aria-controls="modalDatosEncargado"]')->item(0)->getAttribute('x-on:click');
        $this->assertStringNotContainsString('<script>', $expresion);
        $this->assertStringContainsString('u003Cscript', $expresion);
        $this->assertStringContainsString("|| 'No registrado'", $respuesta->getContent());
    }

    public function test_valida_busqueda_sin_aceptar_arrays_o_textos_excesivos(): void
    {
        $this->admin();
        foreach ([['q' => ['nombre']], ['q' => str_repeat('a', 101)]] as $datos) {
            $this->getJson(route('admin.encargados.index', $datos))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
    }
}
