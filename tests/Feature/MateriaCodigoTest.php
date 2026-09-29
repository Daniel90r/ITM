<?php

namespace Tests\Feature;

use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MateriaCodigoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_microsoft_word_genera_mw(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Word']);
        $response->assertOk()->assertJson(['codigo' => 'MW']);
    }

    public function test_microsoft_windows_genera_mwi_cuando_mw_existe(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Windows']);
        $response->assertOk()->assertJson(['codigo' => 'MWI']);
    }

    public function test_microsoft_works_genera_mwo_cuando_mw_y_mwi_existen(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);
        Materia::create(['codigo' => 'MWI', 'nombre' => 'Microsoft Windows', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Works']);
        $response->assertOk()->assertJson(['codigo' => 'MWO']);
    }

    public function test_microsoft_web_genera_mwe(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);
        Materia::create(['codigo' => 'MWI', 'nombre' => 'Microsoft Windows', 'activa' => 1]);
        Materia::create(['codigo' => 'MWO', 'nombre' => 'Microsoft Works', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Web']);
        $response->assertOk()->assertJson(['codigo' => 'MWE']);
    }

    public function test_programacion_web_genera_pw(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Programación Web']);
        $response->assertOk()->assertJson(['codigo' => 'PW']);
    }

    public function test_programacion_windows_genera_pwi_cuando_pw_existe(): void
    {
        Materia::create(['codigo' => 'PW', 'nombre' => 'Programación Web', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Programación Windows']);
        $response->assertOk()->assertJson(['codigo' => 'PWI']);
    }

    public function test_programacion_web_avanzada_genera_pwa(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Programación Web Avanzada']);
        $response->assertOk()->assertJson(['codigo' => 'PWA']);
    }

    public function test_nombre_con_acentos_y_espacios_multiples(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => '  Diseño  Gráfico  Avanzado  ']);
        $response->assertOk()->assertJson(['codigo' => 'DGA']);
    }

    public function test_nombre_duplicado_exacto_lanza_excepcion(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias', ['nombre' => 'Microsoft Word']);
        $response->assertStatus(422)->assertJsonValidationErrors(['nombre' => 'Ya existe una materia con ese nombre.']);
    }

    public function test_nombre_duplicado_con_diferente_casing_lanza_excepcion(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias', ['nombre' => 'microsoft word']);
        $response->assertStatus(422)->assertJsonValidationErrors(['nombre' => 'Ya existe una materia con ese nombre.']);
    }

    public function test_palabras_stop_words_se_ignoran(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Base de Datos']);
        $response->assertOk()->assertJson(['codigo' => 'BD']);
    }

    public function test_palabras_stop_words_en_medio(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Programación de Computadoras']);
        $response->assertOk()->assertJson(['codigo' => 'PC']);
    }

    public function test_codigo_unico_en_base_de_datos(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);
        Materia::create(['codigo' => 'MWI', 'nombre' => 'Microsoft Windows', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Works']);
        $response->assertOk();
        $codigo = $response->json('codigo');
        $this->assertNotEquals('MW', $codigo);
        $this->assertNotEquals('MWI', $codigo);
        $this->assertTrue(Materia::where('codigo', $codigo)->doesntExist());
    }

    public function test_siguiente_codigo_materia_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Word']);
        $response->assertOk()->assertJson(['codigo' => 'MW']);
    }

    public function test_siguiente_codigo_materia_con_colision(): void
    {
        Materia::create(['codigo' => 'MW', 'nombre' => 'Microsoft Word', 'activa' => 1]);

        $response = $this->actingAs($this->admin)->postJson('/admin/materias/siguiente-codigo', ['nombre' => 'Microsoft Windows']);
        $response->assertOk()->assertJson(['codigo' => 'MWI']);
    }
}
