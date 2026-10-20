<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, DetalleCurso, Encargado, Inscripcion, Maestro, Materia, Mensualidad, Nota, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalAlumnoResponsiveTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_todas_las_pantallas_conservan_datos_enlaces_y_formularios(): void
    {
        $this->withoutVite();
        $usuario = User::factory()->create(['role' => 'alumno', 'name' => 'Cuenta de prueba responsive']);
        $alumno = Alumno::factory()->create([
            'user_id' => $usuario->id, 'nombre' => 'María Fernanda Alejandra Isabel',
            'apellido' => 'Rodríguez González de la Cruz', 'codigo' => 'PRUEBA-RESPONSIVE-1',
            'fecha_nacimiento' => now()->subYears(16), 'direccion' => str_repeat('Dirección ficticia de prueba. ', 8),
        ]);
        $encargado = Encargado::create(['nombre' => 'Responsable Ficticio de Nombre Largo', 'apellido' => 'Apellido Compuesto de Prueba', 'dui' => '11111111-1', 'activo' => true]);
        $alumno->encargados()->attach($encargado->id, ['parentesco' => 'Madre']);
        $curso = Curso::factory()->create(['nombre' => 'Computación y Herramientas Digitales de Productividad', 'nivel' => 'Matutino', 'estado' => 'proximo', 'anio_lectivo' => now()->year]);
        $this->actingAs($usuario);
        $respuesta = $this->get(route('alumno.inscripcion'))->assertOk()
            ->assertSee('data-encargado-bloqueado="0"', false)
            ->assertSee('id="firmaAlumno"', false)->assertSee('id="firmaEncargado"', false)
            ->assertSee('Datos oficiales del Encargado')->assertSee($encargado->nombre);
        $this->exportar('inscripcion-menor', $respuesta->getContent());

        $alumno->update(['fecha_nacimiento' => now()->subYears(20)]);
        $respuesta = $this->get(route('alumno.inscripcion'))->assertOk()
            ->assertSee('name="alumno_dui"', false)->assertDontSee('id="firmaEncargado"', false);
        $this->exportar('inscripcion-mayor', $respuesta->getContent());
        $alumno->update(['fecha_nacimiento' => now()->subYears(16)]);
        $encargado->update(['activo' => false]);
        $respuesta = $this->get(route('alumno.inscripcion'))->assertOk()->assertSee('data-encargado-bloqueado="1"', false);
        $this->exportar('inscripcion-bloqueada', $respuesta->getContent());
        $encargado->update(['activo' => true]);

        $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        $maestro = Maestro::factory()->create(['nombre' => 'Docente Ficticio de Nombre Compuesto', 'apellido' => 'Apellidos Largos de Prueba']);
        $materia = Materia::factory()->create(['nombre' => 'Herramientas de Productividad y Comunicación Digital Avanzada']);
        $detalle = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);
        \App\Models\AsignacionDocente::create(['detalle_curso_id' => $detalle->id, 'maestro_id' => $maestro->id, 'estado' => 'activa']);
        Nota::factory()->create(['alumno_id' => $alumno->id, 'detalle_curso_id' => $detalle->id, 'laboratorio' => 9, 'examen_teorico' => 8, 'practica' => 10, 'registrado_por' => $maestro->id]);
        foreach (['pagado', 'pendiente'] as $i => $estado) {
            Mensualidad::create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'mes' => (string) ($i + 1), 'anio' => now()->year, 'monto' => 20, 'estado' => $estado, 'fecha_vencimiento' => now()->addMonth()->toDateString()]);
        }
        $antes = $this->contenidoAcademico();
        foreach (['dashboard', 'notas', 'pagos', 'inscripcion'] as $pantalla) {
            $respuesta = $this->get(route('alumno.'.$pantalla))->assertOk()
                ->assertSee('portal-alumno')->assertSee('aria-controls="portal-menu"', false)
                ->assertSee('aria-current="page"', false)->assertDontSee('Mi perfil')
                ->assertDontSee('href="'.route('profile.edit').'"', false);
            if (in_array($pantalla, ['dashboard', 'notas'])) $respuesta->assertSee($materia->nombre);
            if ($pantalla === 'pagos') $respuesta->assertSee('Pagado')->assertSee('Pendiente')->assertSee('$20.00');
            $this->exportar($pantalla, $respuesta->getContent());
        }
        $this->get(route('profile.edit'))->assertForbidden();
        $this->assertSame($antes, $this->contenidoAcademico());

        foreach (['pendiente', 'rechazada'] as $estado) {
            $inscripcion->update(['estado' => $estado, 'motivo_rechazo' => 'Motivo ficticio de revisión']);
            $respuesta = $this->get(route('alumno.inscripcion'))->assertOk();
            $this->exportar('inscripcion-'.$estado, $respuesta->getContent());
        }
    }

    public function test_perfil_de_administrador_conserva_vista_original(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('profile.edit'))->assertOk()->assertDontSee('portal-alumno');
    }

    public function test_rutas_del_portal_conservan_autorizacion(): void
    {
        foreach (['dashboard', 'inscripcion', 'notas', 'pagos'] as $nombre) {
            $this->get(route('alumno.'.$nombre))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create(['role' => 'docente']));
        foreach (['dashboard', 'inscripcion', 'notas', 'pagos'] as $nombre) {
            $this->get(route('alumno.'.$nombre))->assertRedirect(route('docente.notas'));
        }
    }

    private function contenidoAcademico(): array
    {
        $datos = [];
        foreach (['alumnos', 'alumno_encargado', 'encargados', 'inscripciones', 'notas', 'mensualidades', 'cursos', 'detalle_cursos'] as $tabla) {
            $datos[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        return $datos;
    }

    private function exportar(string $nombre, string $html): void
    {
        if (! $directorio = getenv('ITM_PORTAL_PREVIEW_DIR')) return;
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/css/app.css']['file']));
        $html = preg_replace('/<link[^>]+https:\/\/fonts\.bunny\.net[^>]*>/', '', $html);
        $html = str_replace('</head>', '<link rel="stylesheet" href="'.$css.'"><style>'.file_get_contents(resource_path('css/alumno/portal.css')).'</style></head>', $html);
        $html = str_replace('http://localhost/images/logo_itm.jpg', 'file:///C:/wamp64/www/ITM/public/images/logo_itm.jpg', $html);
        $script = preg_replace('/^import .*;\s*/m', '', file_get_contents(resource_path('js/alumno/portal.js')));
        $script .= "\n".file_get_contents(resource_path('js/alumno/inscripcion.js'));
        $html = str_replace('</body>', '<script>'.$script.'</script></body>', $html);
        file_put_contents($directorio.'/'.$nombre.'.html', $html);
    }
}
