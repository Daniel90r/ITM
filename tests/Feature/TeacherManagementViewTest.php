<?php

namespace Tests\Feature;

use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherManagementViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearMaestroConUsuario(string $dui = '01234567-8', string $telefono = '7123-4567'): Maestro
    {
        $user = User::factory()->create(['role' => 'docente']);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'telefono' => $telefono,
            'dui' => $dui,
            'codigo' => 'MST'.uniqid(),
            'activo' => true,
        ]);
    }

    public function test_detail_view_renders_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertStatus(200);
        $response->assertSee('Programas asignados', false);
        $response->assertSee($maestro->nombre_completo, false);
    }

    public function test_detail_view_includes_edit_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertSee('id="modalEditarMaestro"', false);
    }

    public function test_detail_view_includes_asignar_curso_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertSee('id="modalAsignarCurso"', false);

        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@include\s*\(\s*['\"]admin\.partials\.modal-asignar-curso['\"]\s*\)/", $content));
    }

    public function test_detail_view_has_no_edit_maestro_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        // Decisión vigente: la edición de maestros NO se inicia desde la vista
        // detalle. El modal sigue incluido (ver test_detail_view_includes_edit_modal),
        // pero ningún control de esta pantalla lo abre.
        $response->assertDontSee('abrirModalEditarMaestro(', false);
    }

    public function test_detail_view_keeps_the_rest_of_the_maestro_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        // Asignar programas, cambiar maestro, desasignar e historial siguen vivos.
        $response->assertSee('abrirModalAsignarCurso(', false);
        $response->assertSee('Asignar programa', false);
        $response->assertSee('id="modalAsignarCurso"', false);
        $response->assertSee('id="modalCambiarMaestro"', false);
        $response->assertSee('Programas asignados', false);
    }

    public function test_list_view_keeps_the_edit_maestro_button(): void
    {
        $this->crearMaestroConUsuario();

        $content = file_get_contents(resource_path('views/admin/maestros.blade.php'));

        $this->assertStringContainsString('abrirModalEditarMaestro(', $content);
        $this->assertSame(1, substr_count($content, 'abrirModalEditarMaestro('), 'La vista de listado debe tener un único botón Editar.');
    }

    public function test_detail_view_no_obsolete_function_calls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertDontSee('limpiarBusqueda()', false);
        $response->assertDontSee('abrirModalAsignar(', false);
    }

    public function test_detail_view_no_obsolete_script_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $html = $response->getContent();
        $this->assertStringNotContainsString('abrirModalAsignar(', $html);
        $this->assertStringNotContainsString('editPassword_confirmation', $html);
    }

    public function test_modals_js_has_no_five_param_signature(): void
    {
        $path = resource_path('js/admin/modals.js');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString(
            'id, nombre, apellido, codigo, email',
            $content,
            'modals.js no debe contener la firma obsoleta de 5 parámetros.'
        );
    }

    public function test_maestros_js_does_not_define_edit_modal(): void
    {
        $path = resource_path('js/admin/maestros.js');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('abrirModalEditarMaestro', $content, 'maestros.js no debe definir abrirModalEditarMaestro.');
        $this->assertStringNotContainsString('cerrarModalEditarMaestro', $content, 'maestros.js no debe definir cerrarModalEditarMaestro.');
    }

    public function test_modal_asignar_curso_partial_has_no_vite_push(): void
    {
        $path = resource_path('views/admin/partials/modal-asignar-curso.blade.php');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString("@push('scripts')", $content, 'modal-asignar-curso no debe contener @push scripts.');
    }

    public function test_vite_config_includes_maestros_js(): void
    {
        $content = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('resources/js/admin/maestros.js', $content);
    }

    public function test_detail_view_has_correct_vite_entries(): void
    {
        $path = resource_path('views/admin/maestro-detalle.blade.php');
        $content = file_get_contents($path);

        $this->assertStringContainsString('@vite', $content);
        $this->assertStringContainsString('resources/js/admin/maestros.js', $content);
    }

    public function test_maestros_js_imports_modals_js(): void
    {
        $content = file_get_contents(resource_path('js/admin/maestros.js'));
        $this->assertStringContainsString("import './modals.js';", $content);
    }

    public function test_list_view_loads_maestros_js_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestros.blade.php'));
        $this->assertSame(1, preg_match_all("/@vite\s*\(\s*['\"]resources\/js\/admin\/maestros\.js['\"]\s*\)/", $content));
    }

    public function test_detail_view_loads_maestros_js_once(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@vite\s*\(\s*['\"]resources\/js\/admin\/maestros\.js['\"]\s*\)/", $content));
    }

    public function test_list_view_does_not_load_modals_js_directly(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestros.blade.php'));
        $this->assertStringNotContainsString('modals.js', $content);
    }

    public function test_detail_view_does_not_load_modals_js_directly(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertStringNotContainsString('modals.js', $content);
    }

    public function test_modals_js_exposes_abrir_modal_editar_maestro(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringContainsString('window.abrirModalEditarMaestro = abrirModalEditarMaestro', $content);
        $this->assertStringContainsString('window.abrirModalMaestro = abrirModalMaestro', $content);
        $this->assertStringContainsString('window.cerrarModalMaestro = cerrarModalMaestro', $content);
        $this->assertStringContainsString('window.cerrarModalEditarMaestro = cerrarModalEditarMaestro', $content);
    }

    public function test_edit_button_has_seven_args(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestros.blade.php'));
        $this->assertStringContainsString('abrirModalEditarMaestro(', $content);
        $jsArgCount = substr_count($content, '@js(');
        $this->assertSame(7, $jsArgCount, 'El botón Editar debe pasar 7 argumentos @js().');
    }

    public function test_modal_editar_maestro_included_once_in_list_view(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestros.blade.php'));
        $this->assertSame(1, preg_match_all("/@include\s*\(\s*['\"]admin\.partials\.modal-editar-maestro['\"]\s*\)/", $content));
    }

    public function test_modal_editar_maestro_included_once_in_detail_view(): void
    {
        $content = file_get_contents(resource_path('views/admin/maestro-detalle.blade.php'));
        $this->assertSame(1, preg_match_all("/@include\s*\(\s*['\"]admin\.partials\.modal-editar-maestro['\"]\s*\)/", $content));
    }

    public function test_detail_view_asignar_programa_button_uses_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $html = $response->getContent();
        $this->assertStringNotContainsString('abrirModalAsignar(', $html);
        $this->assertStringContainsString('abrirModalAsignarCurso(', $html);
        $this->assertStringContainsString('Asignar programa', $html);
    }

    public function test_detail_view_does_not_have_search_clear_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertDontSee('limpiarBusquedaMaestros()', false);
    }

    public function test_js_files_have_null_safe_listeners(): void
    {
        $modalsContent = file_get_contents(resource_path('js/admin/modals.js'));

        $this->assertStringContainsString('if (modalNuevo', $modalsContent);
        $this->assertStringContainsString('if (modalEditar', $modalsContent);
    }

    public function test_asignar_form_action_points_to_correct_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertSee(route('admin.maestros.asignar', $maestro->id), false);
    }

    public function test_detail_view_edit_button_passes_telefono_and_dui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario('01234567-8', '7123-4567');

        $response = $this->actingAs($admin)->get(route('admin.maestros.show', $maestro->id));

        $response->assertSee('7123-4567', false);
        $response->assertSee('01234567-8', false);
    }

    public function test_modal_editar_maestro_has_no_broken_onmouseover_html(): void
    {
        $path = resource_path('views/admin/partials/modal-editar-maestro.blade.php');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('onmouseover="this"style', $content, 'El atributo onmouseover no debe estar roto.');
        $this->assertStringNotContainsString('onmouseout="this"style', $content, 'El atributo onmouseout no debe estar roto.');
        $this->assertStringContainsString("onmouseover=\"this.style.background='#e5e7eb'\"", $content, 'El atributo onmouseover debe tener la sintaxis correcta.');
        $this->assertStringContainsString("onmouseout=\"this.style.background='#f3f4f6'\"", $content, 'El atributo onmouseout debe tener la sintaxis correcta.');
    }

    public function test_list_view_has_gestionar_programas_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maestro = $this->crearMaestroConUsuario();

        $response = $this->actingAs($admin)->withoutVite()->get(route('admin.maestros'));

        $response->assertSee('Gestionar programas', false);
        $response->assertSee(route('admin.maestros.show', $maestro->id), false);
    }

    public function test_modals_js_exposes_abrir_modal_asignar_curso(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringContainsString('window.abrirModalAsignarCurso = abrirModalAsignarCurso', $content);
        $this->assertStringContainsString('window.cerrarModalAsignarCurso = cerrarModalAsignarCurso', $content);
    }

    public function test_modals_js_has_no_obsolete_asignar_signature(): void
    {
        $content = file_get_contents(resource_path('js/admin/modals.js'));
        $this->assertStringNotContainsString('abrirModalAsignar(maestroId, maestroNombre)', $content);
        $this->assertStringNotContainsString("'/admin/maestros/' + maestroId + '/asignar'", $content);
    }
}
