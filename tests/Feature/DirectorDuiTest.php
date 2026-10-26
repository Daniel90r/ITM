<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorDuiTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    public function test_formulario_contiene_mascara_y_restricciones_de_dui(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.directores.index'))
            ->assertOk()->assertSee('data-director-dui', false)->assertSee('inputmode="numeric"', false)
            ->assertSee('pattern="[0-9]{8}-[0-9]"', false)->assertSee('id="director-dui-error"', false);
    }

    public function test_backend_rechaza_dui_incompleto_o_manipulado_sin_crear_director(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['23242343', '232423432', '23242343-a', '23242343-22'] as $dui) {
            $this->postJson(route('admin.directores.store'), ['nombre' => 'Director sintético', 'fecha_inicio' => '2026-10-25', 'dui' => $dui])
                ->assertUnprocessable()->assertJsonValidationErrors('dui');
        }
        $this->assertDatabaseCount('directores', 0);
    }
}
