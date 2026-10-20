<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SolicitudesListadoTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function solicitud(string $estado, ?Alumno $alumno = null, ?Curso $curso = null): Inscripcion
    {
        return Inscripcion::factory()->create([
            'alumno_id' => ($alumno ?? Alumno::factory()->create(['fecha_nacimiento' => '2000-01-01']))->id,
            'curso_id' => ($curso ?? Curso::factory()->create())->id,
            'estado' => $estado, 'activa' => $estado === 'aprobada',
        ]);
    }

    private function fila($respuesta, int $id): \DOMElement
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $nodo = $xpath->query('//tr[contains(@class,"fila-solicitud")][.//button[@onclick="abrirDetalle('.$id.')"]]')->item(0);
        $this->assertNotNull($nodo);
        return $nodo;
    }

    public function test_rechazada_aparece_en_todos_y_en_su_filtro_con_conteo_correcto(): void
    {
        $rechazada = $this->solicitud('rechazada');
        $this->solicitud('pendiente');
        $this->solicitud('aprobada');
        $todos = $this->get(route('admin.solicitudes'))->assertOk();
        $this->assertStringContainsString('Rechazada', $this->fila($todos, $rechazada->id)->textContent);
        $this->assertSame(1, $todos->viewData('conteos')['rechazadas']);
        $this->assertSame(3, $todos->viewData('conteos')['total']);
        $filtrada = $this->get(route('admin.solicitudes', ['estado' => 'rechazada']))->assertOk();
        $this->assertSame([$rechazada->id], $filtrada->viewData('inscripciones')->pluck('id')->all());
        $this->assertStringContainsString('Rechazada', $this->fila($filtrada, $rechazada->id)->textContent);
        $this->assertSame($todos->viewData('conteos'), $filtrada->viewData('conteos'));
    }

    public function test_inactiva_no_se_presenta_como_rechazada_y_explicita_el_total_real(): void
    {
        $inactiva = $this->solicitud('inactiva');
        for ($i = 0; $i < 5; $i++) $this->solicitud('aprobada');
        $respuesta = $this->get(route('admin.solicitudes'))->assertOk();
        $this->assertSame(['pendientes' => 0, 'aprobadas' => 5, 'rechazadas' => 0, 'inactivas' => 1, 'total' => 6, 'otros' => 0], $respuesta->viewData('conteos'));
        $fila = $this->fila($respuesta, $inactiva->id);
        $this->assertStringContainsString('Inactiva', $fila->textContent);
        $this->assertStringNotContainsString('Rechazada', $fila->textContent);
        $this->assertStringNotContainsString('Reabrir', $fila->textContent);
        $respuesta->assertSee('6 solicitudes registradas')->assertSee('data-estado="inactiva"', false);
        $rechazadas = $this->get(route('admin.solicitudes', ['estado' => 'rechazada']))->assertOk();
        $this->assertSame(0, $rechazadas->viewData('inscripciones')->total());
        $this->assertSame(1, $this->get(route('admin.solicitudes', ['estado' => 'inactiva']))->viewData('inscripciones')->total());
    }

    public static function estadosPrevios(): array { return [['rechazada'], ['inactiva']]; }

    #[DataProvider('estadosPrevios')]
    public function test_historial_de_un_alumno_en_cursos_distintos_no_se_confunde(string $estado): void
    {
        $alumno = Alumno::factory()->create(['fecha_nacimiento' => '2000-01-01']);
        $a = Curso::factory()->create(['nombre' => 'Curso A']);
        $b = Curso::factory()->create(['nombre' => 'Curso B']);
        $anterior = $this->solicitud($estado, $alumno, $a);
        $aprobada = $this->solicitud('aprobada', $alumno, $b);
        $antes = DB::table('inscripciones')->orderBy('id')->get()->toJson();
        $todos = $this->get(route('admin.solicitudes'))->assertOk();
        $this->assertCount(2, $todos->viewData('inscripciones'));
        $filtro = $this->get(route('admin.solicitudes', ['estado' => $estado]))->assertOk();
        $this->assertSame([$anterior->id], $filtro->viewData('inscripciones')->pluck('id')->all());
        $aprobadas = $this->get(route('admin.solicitudes', ['estado' => 'aprobada']))->assertOk();
        $this->assertSame([$aprobada->id], $aprobadas->viewData('inscripciones')->pluck('id')->all());
        $this->get(route('admin.inscripciones'))->assertOk()->assertSee('Curso B')->assertDontSee('Curso A');
        $this->assertSame($antes, DB::table('inscripciones')->orderBy('id')->get()->toJson());
    }

    public function test_busqueda_y_paginacion_se_aplican_antes_de_cargar_las_filas(): void
    {
        $alumno = Alumno::factory()->create(['nombre' => 'Nombre Buscado', 'apellido' => 'Apellido', 'codigo' => 'BUS-001']);
        for ($i = 0; $i < 12; $i++) $this->solicitud('rechazada', $alumno);
        for ($i = 0; $i < 11; $i++) $this->solicitud('aprobada');
        $primera = $this->get(route('admin.solicitudes', ['estado' => 'rechazada', 'q' => 'BUS-001']))->assertOk();
        $paginas = $primera->viewData('inscripciones');
        $this->assertSame(12, $paginas->total());
        $this->assertCount(10, $paginas);
        $this->assertStringContainsString('estado=rechazada', $paginas->nextPageUrl());
        $this->assertStringContainsString('q=BUS-001', $paginas->nextPageUrl());
        $segunda = $this->get($paginas->nextPageUrl())->assertOk();
        $this->assertCount(2, $segunda->viewData('inscripciones'));
        $this->assertEmpty(array_intersect($paginas->pluck('id')->all(), $segunda->viewData('inscripciones')->pluck('id')->all()));
        $this->assertSame(23, $segunda->viewData('conteos')['total']);
        $porNombre = $this->get(route('admin.solicitudes', ['q' => 'Nombre Buscado Apellido']))->assertOk();
        $this->assertSame(12, $porNombre->viewData('inscripciones')->total());
    }

    public function test_pendientes_y_aprobadas_conservan_estado_y_acciones(): void
    {
        $pendiente = $this->solicitud('pendiente');
        $this->solicitud('aprobada');
        $respuesta = $this->get(route('admin.solicitudes', ['estado' => 'pendiente']))->assertOk();
        $this->assertSame([$pendiente->id], $respuesta->viewData('inscripciones')->pluck('id')->all());
        $texto = $this->fila($respuesta, $pendiente->id)->textContent;
        $this->assertStringContainsString('Pendiente', $texto);
        $this->assertStringContainsString('Aprobar', $texto);
        $this->assertStringContainsString('Rechazar', $texto);
        $this->assertSame(1, $respuesta->viewData('conteos')['pendientes']);
        $this->assertSame(1, $respuesta->viewData('conteos')['aprobadas']);
    }

    public function test_estado_desconocido_no_se_inventa_como_rechazo(): void
    {
        $registro = $this->solicitud('rechazado');
        $this->get(route('admin.solicitudes'))->assertOk()->assertSee('Estado no reconocido');
        $otros = $this->get(route('admin.solicitudes', ['estado' => 'otros']))->assertOk();
        $this->assertSame(1, $otros->viewData('conteos')['otros']);
        $this->assertSame(0, $otros->viewData('conteos')['rechazadas']);
        $this->assertSame([$registro->id], $otros->viewData('inscripciones')->pluck('id')->all());
        $this->assertSame('rechazado', $registro->fresh()->estado);
    }

    public function test_busqueda_sin_resultados_y_validacion_de_parametros(): void
    {
        $this->solicitud('rechazada');
        $respuesta = $this->get(route('admin.solicitudes', ['q' => 'INEXISTENTE', 'estado' => 'rechazada']))->assertOk()
            ->assertSee('No se encontraron solicitudes con los filtros seleccionados.');
        $this->assertSame(0, $respuesta->viewData('inscripciones')->total());
        $this->assertSame(1, $respuesta->viewData('conteos')['rechazadas']);
        $this->getJson(route('admin.solicitudes', ['estado' => 'falso']))->assertUnprocessable();
        $this->getJson(route('admin.solicitudes', ['q' => ['invalido']]))->assertUnprocessable();
    }
}
