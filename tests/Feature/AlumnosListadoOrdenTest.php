<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumnosListadoOrdenTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_orden_por_creacion_y_desempate_por_id_sin_omisiones(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $antiguo = Alumno::factory()->create(['created_at' => '2025-01-01 12:00:00']);
        $reciente = Alumno::factory()->create(['created_at' => '2026-10-18 12:00:00']);
        $empate = Alumno::factory()->create(['created_at' => '2026-10-18 12:00:00']);
        $intermedio = Alumno::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        $esperados = [$empate->id, $reciente->id, $intermedio->id, $antiguo->id];
        $antes = Alumno::orderBy('id')->get()->toJson();

        foreach ([[], ['buscar' => 'Prueba', 'curso' => 'Computación', 'anio' => 2026], ['page' => 2]] as $parametros) {
            // La pantalla filtra en el navegador y no tiene paginación de servidor.
            $response = $this->get(route('admin.alumnos', $parametros))->assertOk();
            $ids = $response->viewData('alumnos')->pluck('id')->all();
            $this->assertSame($esperados, $ids);
            $this->assertSame($ids, array_values(array_unique($ids)));
        }
        $this->assertSame($antes, Alumno::orderBy('id')->get()->toJson());
    }

    public function test_datos_largos_y_acciones_siguen_disponibles(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $usuario = User::factory()->create(['email' => 'correo.muy.largo.para.comprobar.la.tabla@ejemplo.test', 'role' => 'alumno']);
        $alumno = Alumno::factory()->create([
            'user_id' => $usuario->id, 'nombre' => 'María Fernanda Alejandra Isabel',
            'apellido' => 'Rodríguez González de la Cruz', 'telefono' => '2222-3333',
            'codigo' => 'PRUEBA-001', 'activo' => true,
        ]);
        $curso = Curso::factory()->create(['nombre' => 'Computación y Herramientas Digitales', 'anio_lectivo' => 2026]);
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        Alumno::factory()->create(['nombre' => 'Alumno', 'apellido' => 'Sin curso', 'activo' => false]);
        $antes = Inscripcion::orderBy('id')->get()->toJson();
        $response = $this->get(route('admin.alumnos'))->assertOk()
            ->assertSee($alumno->nombre_completo)->assertSee('2222-3333')
            ->assertSee($usuario->email)->assertSee($curso->nombre)
            ->assertSee('Editar')->assertSee('abrirModalNuevo()', false)
            ->assertSee('abrirModalEditar(', false)->assertSee('limpiarFiltrosAlumnos()', false)
            ->assertSee('alumnos-scroll')->assertSee('● Activo')->assertSee('● Inactivo');
        $this->assertSame($antes, Inscripcion::orderBy('id')->get()->toJson());
        if ($archivo = getenv('ITM_ALUMNOS_PREVIEW_HTML')) {
            $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $response->getContent());
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            $css = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/css/app.css']['file']));
            $html = str_replace('</head>', '<link rel="stylesheet" href="'.$css.'"></head>', $html);
            $html = preg_replace('/<link[^>]+https:\/\/fonts\.bunny\.net[^>]*>/', '', $html);
            $html = str_replace('http://localhost/images/logo_itm.jpg', 'file:///C:/wamp64/www/ITM/public/images/logo_itm.jpg', $html);
            $html = str_replace('</body>', '<script>'.file_get_contents(resource_path('js/admin/alumnos.js')).'</script></body>', $html);
            file_put_contents($archivo, $html);
        }
    }

    public function test_usuario_no_administrador_no_puede_consultar_alumnos(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'alumno']))
            ->get(route('admin.alumnos'))->assertForbidden();
    }
}
