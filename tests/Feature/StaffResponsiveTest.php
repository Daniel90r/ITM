<?php
namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Curso, DetalleCurso, Inscripcion, Maestro, Materia, Mensualidad, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffResponsiveTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    // La vista heredada de notas declara una función PHP global. Aislar su renderizado
    // evita colisiones con otras pruebas sin cambiar esa funcionalidad.
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_pantallas_conservan_navegacion_y_datos(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        $docente = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::factory()->create(['user_id' => $docente->id]);
        $alumno = Alumno::factory()->create(['nombre' => 'Alumno Ficticio Responsive', 'apellido' => 'Apellidos de Prueba']);
        $curso = Curso::factory()->create(['activo' => true, 'estado' => 'activo']);
        $materia = Materia::factory()->create();
        $detalle = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);
        AsignacionDocente::create(['detalle_curso_id' => $detalle->id, 'maestro_id' => $maestro->id, 'estado' => 'activa']);
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        foreach (range(1, 30) as $numero) {
            $otro = Alumno::factory()->create(['nombre' => 'Estudiante Ficticio '.$numero]);
            Inscripcion::factory()->create(['alumno_id' => $otro->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        }
        Mensualidad::create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'mes' => '6', 'anio' => now()->year, 'monto' => 25, 'estado' => 'pendiente', 'fecha_vencimiento' => now()->subMonth()->toDateString()]);
        $antes = DB::table('inscripciones')->get()->toJson();
        $this->actingAs($admin);
        foreach (['dashboard', 'alumnos', 'maestros', 'directores.index', 'encargados.index', 'cursos', 'materias', 'solicitudes', 'inscripciones', 'asistencias', 'notas', 'boletas', 'mensualidades', 'pagos', 'reportes', 'mensualidades.historial.index'] as $nombre) {
            $this->comprobar('admin-'.$nombre, route('admin.'.$nombre));
        }
        foreach (['alumnos', 'cursos', 'calificaciones', 'asistencias', 'mensualidades', 'maestros'] as $tipo) {
            $this->comprobar('reporte-'.$tipo, route('admin.reportes.show', ['tipo' => $tipo]));
        }
        $this->comprobar('historial', route('admin.mensualidades.historial.show', $alumno));
        $this->comprobar('boleta-preview', route('admin.boletas.preview', ['alumno' => $alumno->id, 'curso' => $curso->id]));
        $this->comprobar('curso-detalle', route('admin.cursos.show', $curso));
        $this->comprobar('curso-editar', route('admin.cursos.edit', $curso));
        $this->comprobar('maestro-detalle', route('admin.maestros.show', $maestro));
        $this->actingAs($docente);
        $this->comprobar('docente-dashboard', route('docente.dashboard'));
        foreach (['notas', 'asistencia'] as $nombre) {
            $this->comprobar('docente-'.$nombre, route('docente.'.$nombre, ['curso_id' => $curso->id, 'detalle_curso_id' => $detalle->id, 'fecha' => today()->toDateString()]))->assertSee($alumno->nombre);
        }
        $this->assertSame($antes, DB::table('inscripciones')->get()->toJson());
    }

    public function test_scope_y_permisos_no_cambian(): void
    {
        $this->withoutVite();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('docente.notas'))->assertRedirect(route('login'));
        $alumnoUser = User::factory()->create(['role' => 'alumno']);
        Alumno::factory()->create(['user_id' => $alumnoUser->id]);
        $this->actingAs($alumnoUser)->get(route('alumno.dashboard'))->assertOk()->assertDontSee('id="staff-menu-toggle"', false)->assertDontSee('itm-staff');
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('docente.notas'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('profile.edit'))->assertOk()->assertDontSee('id="staff-menu-toggle"', false);
    }

    private function comprobar(string $nombre, string $url)
    {
        $respuesta = $this->get($url)->assertOk()->assertSee('itm-staff')->assertSee('aria-controls="staff-navigation"', false)->assertSee('data-staff-sidebar', false)
            ->assertSee('Cerrar menú de navegación', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), 'id="staff-navigation"'));
        if ($directorio = getenv('ITM_STAFF_PREVIEW_DIR')) {
            $html = $respuesta->getContent();
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
            $html = str_replace('<head>', '<head><style>'.$css.'</style>', $html);
            $html = preg_replace('/<link[^>]+https:\/\/fonts\.bunny\.net[^>]*>/', '', $html);
            $html = str_replace('http://localhost/images/', 'file:///C:/wamp64/www/ITM/public/images/', $html);
            file_put_contents($directorio.'/'.$nombre.'.html', $html);
        }
        return $respuesta;
    }
}
