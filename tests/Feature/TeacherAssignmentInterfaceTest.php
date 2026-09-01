<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssignmentInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearDatosBase(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $maestroUser = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::create([
            'user_id' => $maestroUser->id,
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => '7123-4567',
            'dui' => '01234567-8',
            'codigo' => 'MST80001',
            'activo' => true,
        ]);

        $curso = Curso::create([
            'nombre' => 'Test Curso',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);

        $materia = Materia::create([
            'nombre' => 'Test Materia',
            'codigo' => 'MAT80001',
            'descripcion' => 'Test',
            'activa' => true,
        ]);

        return [
            'admin' => $admin,
            'maestroUser' => $maestroUser,
            'maestro' => $maestro,
            'curso' => $curso,
            'materia' => $materia,
        ];
    }

    public function test_admin_can_open_maestro_detail_page(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertRedirect(route('login'));
    }

    public function test_docente_recibe_403(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['maestroUser'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertStatus(403);
    }

    public function test_alumno_recibe_403(): void
    {
        $data = $this->crearDatosBase();
        $alumno = User::factory()->create(['role' => 'alumno']);

        $response = $this->actingAs($alumno)
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertStatus(403);
    }

    public function test_detail_shows_only_selected_maestro(): void
    {
        $data = $this->crearDatosBase();

        $otroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Apellido',
            'codigo' => 'MST99999',
            'activo' => true,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee($data['maestro']->nombre, false);
        $response->assertSee($otroMaestro->nombre, false);
    }

    public function test_detail_does_not_show_other_maestro_data(): void
    {
        $data = $this->crearDatosBase();

        $otroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroUser->id,
            'nombre' => 'Confidencial',
            'apellido' => 'Datos',
            'codigo' => 'MST88888',
            'telefono' => '7999-9999',
            'dui' => '87654321-0',
            'activo' => true,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee($otroMaestro->codigo, false);
        $response->assertDontSee($otroMaestro->dui, false);
        $response->assertDontSee($otroMaestro->telefono, false);
        $response->assertDontSee($otroUser->email, false);
        $response->assertDontSee($otroUser->password, false);
    }

    public function test_asignar_form_uses_correct_route(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee(route('admin.maestros.asignar', $data['maestro']->id), false);
    }

    public function test_asignar_form_uses_post_and_csrf(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_select_curso_id_exists(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('name="curso_id"', false);
    }

    public function test_select_materia_id_exists(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('name="materia_id"', false);
    }

    public function test_selects_have_required_attribute(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $content = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($content, LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);

        $cursoSelect = $xpath->query('//select[@name="curso_id"]')->item(0);
        $materiaSelect = $xpath->query('//select[@name="materia_id"]')->item(0);

        $this->assertNotNull($cursoSelect, 'El select curso_id existe en el DOM');
        $this->assertTrue($cursoSelect->hasAttribute('required'), 'El select curso_id tiene el atributo required');

        $this->assertNotNull($materiaSelect, 'El select materia_id existe en el DOM');
        $this->assertTrue($materiaSelect->hasAttribute('required'), 'El select materia_id tiene el atributo required');
    }

    public function test_only_active_cursos_shown(): void
    {
        $data = $this->crearDatosBase();

        Curso::create([
            'nombre' => 'Curso Inactivo',
            'nivel' => 'Vespertino',
            'seccion' => 'B',
            'anio_lectivo' => 2025,
            'activo' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Test Curso', false);
    }

    public function test_inactive_cursos_not_shown(): void
    {
        $data = $this->crearDatosBase();

        Curso::create([
            'nombre' => 'Curso Inactivo XYZ',
            'nivel' => 'Vespertino',
            'seccion' => 'B',
            'anio_lectivo' => 2025,
            'activo' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('Curso Inactivo XYZ', false);
    }

    public function test_only_active_materias_shown(): void
    {
        $data = $this->crearDatosBase();

        Materia::create([
            'nombre' => 'Materia Inactiva',
            'codigo' => 'MAT99999',
            'descripcion' => 'Inactiva',
            'activa' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Test Materia', false);
    }

    public function test_inactive_materias_not_shown(): void
    {
        $data = $this->crearDatosBase();

        Materia::create([
            'nombre' => 'Materia Inactiva ABC',
            'codigo' => 'MAT99999',
            'descripcion' => 'Inactiva',
            'activa' => false,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('Materia Inactiva ABC', false);
    }

    public function test_curso_id_old_input_preserved_when_materia_fails(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => $data['curso']->id,
                'materia_id' => 999999,
            ]);

        $response->assertSee('value="'.$data['curso']->id.'" selected', false);
    }

    public function test_materia_id_old_input_preserved_when_curso_fails(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => '',
                'materia_id' => $data['materia']->id,
            ]);

        $response->assertSee('value="'.$data['materia']->id.'" selected', false);
    }

    public function test_validation_errors_reopen_modal(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->withHeader('Referer', route('admin.maestros.show', $data['maestro']->id))
            ->followingRedirects()
            ->post(route('admin.maestros.asignar', $data['maestro']->id), [
                'curso_id' => 999999,
                'materia_id' => 999999,
            ]);

        $response->assertSee('data-open="1"', false);
    }

    public function test_active_maestro_shows_asignar_programa_button(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('abrirModalAsignarCurso()', false);
        $response->assertSee('Asignar programa', false);
    }

    public function test_inactive_maestro_does_not_show_asignar_button(): void
    {
        $data = $this->crearDatosBase();
        $data['maestro']->update(['activo' => false]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertDontSee('abrirModalAsignarCurso()', false);
    }

    public function test_existing_assignment_shows_curso_nivel_materia_codigo(): void
    {
        $data = $this->crearDatosBase();

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee($data['curso']->nombre, false);
        $response->assertSee($data['curso']->nivel, false);
        $response->assertSee($data['curso']->seccion, false);
        $response->assertSee($data['materia']->nombre, false);
        $response->assertSee($data['materia']->codigo, false);
    }

    public function test_no_assignments_shows_empty_state(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee('Este maestro no tiene programas asignados.', false);
    }

    public function test_desasignar_form_has_correct_route(): void
    {
        $data = $this->crearDatosBase();

        $detalle = DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $response->assertSee(route('admin.maestros.desasignar', [$data['maestro']->id, $detalle->id]), false);
        $response->assertSee('name="_method"', false);
        $response->assertSee('value="DELETE"', false);
    }

    public function test_curso_and_materia_eager_loaded(): void
    {
        $data = $this->crearDatosBase();

        DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $maestroVista = $response->viewData('maestro');

        $this->assertTrue($maestroVista->relationLoaded('detalleCursos'));

        $detalleVista = $maestroVista->detalleCursos->first();
        $this->assertTrue($detalleVista->relationLoaded('curso'));
        $this->assertTrue($detalleVista->relationLoaded('materia'));
    }

    public function test_modal_included_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@include\s*\(\s*['\"]admin\.partials\.modal-asignar-curso['\"]\s*\)/", $content));
    }

    public function test_maestros_js_loaded_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@vite\s*\(\s*['\"]resources\/js\/admin\/maestros\.js['\"]\s*\)/", $content));
    }

    public function test_no_obsolete_js_url_concatenation(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])
            ->get(route('admin.maestros.show', $data['maestro']->id));

        $html = $response->getContent();
        $this->assertStringNotContainsString("/admin/maestros/' + maestroId", $html);
    }

    public function test_modals_js_exposes_new_functions(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringContainsString('window.abrirModalAsignarCurso = abrirModalAsignarCurso', $content);
        $this->assertStringContainsString('window.cerrarModalAsignarCurso = cerrarModalAsignarCurso', $content);
    }

    public function test_modals_js_no_obsolete_signature(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringNotContainsString('abrirModalAsignar(maestroId, maestroNombre)', $content);
        $this->assertStringNotContainsString("'/admin/maestros/' + maestroId + '/asignar'", $content);
    }

    public function test_modals_js_null_safe_listeners(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('if (modalNuevo', $content);
        $this->assertStringContainsString('if (modalEditar', $content);
        $this->assertStringContainsString('if (modalAsignar', $content);
        $this->assertStringContainsString('modalAsignar.dataset', $content);
        $this->assertStringContainsString('modalAsignarAbierto', $content);
    }
}
