<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\CambioEstadoPersona;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstadoPersonaInterfazTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public static function personas(): array
    {
        return [['alumnos', true], ['alumnos', false], ['maestros', true], ['maestros', false]];
    }

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }

    #[DataProvider('personas')]
    public function test_presentacion_y_campos_de_seguridad_para_ambos_tipos(string $tipo, bool $activo): void
    {
        $persona = ($tipo === 'alumnos' ? Alumno::class : Maestro::class)::factory()->create([
            'activo' => $activo,
            'user_id' => $tipo === 'alumnos' ? null : User::factory()->create(['role' => 'docente'])->id,
        ]);
        $response = $this->get(route('admin.personas.estado', [$tipo, $persona->id]));
        $response->assertOk()->assertSee('Información de la persona')->assertSee('Administración del acceso')
            ->assertSee('Historial de cambios')->assertSee($persona->nombre_completo)
            ->assertSee($activo ? 'Desactivar acceso' : 'Reactivar acceso')
            ->assertSee('Sin cambios de estado registrados.');
        if ($tipo === 'alumnos') {
            $response->assertSee('Expediente sin cuenta asociada.');
        }
        $xpath = $this->dom($response->getContent());
        $form = '//form[@data-persona-form][input[@name="revision"]]';
        $this->assertSame(route('admin.personas.estado.update', [$tipo, $persona->id]), $xpath->evaluate('string('.$form.'/@action)'));
        foreach (['_method' => 'PATCH', 'esperado' => $activo ? '1' : '0', 'activo' => $activo ? '0' : '1', 'revision' => '0'] as $name => $value) {
            $this->assertSame($value, $xpath->evaluate('string('.$form.'/input[@name="'.$name.'"]/@value)'));
        }
        $this->assertNotEmpty($xpath->evaluate('string('.$form.'/input[@name="_token"]/@value)'));
        $this->assertStringContainsString($persona->nombre_completo, $xpath->evaluate('string('.$form.'/@data-persona-confirm)'));
        $this->assertSame(0, $xpath->query('//form[@data-persona-form and @data-confirm]')->length);
        $this->assertSame('10', $xpath->evaluate('string('.$form.'/textarea/@minlength)'));
        $this->assertSame('500', $xpath->evaluate('string('.$form.'/textarea/@maxlength)'));
        $this->assertSame(0, $xpath->query($form.'//button[@disabled]')->length);
        $this->assertDatabaseCount('cambios_estado_personas', 0);
        $this->assertSame($activo, (bool) $persona->fresh()->activo);
    }

    public function test_validacion_conserva_motivo_y_no_cambia_estado(): void
    {
        $persona = Alumno::factory()->create(['activo' => true, 'user_id' => null]);
        $url = route('admin.personas.estado', ['alumnos', $persona->id]);
        $this->from($url)->patch(route('admin.personas.estado.update', ['alumnos', $persona->id]), [
            'activo' => 0, 'esperado' => 1, 'revision' => 0, 'motivo' => 'Corto',
        ])->assertRedirect($url)->assertSessionHasErrors('motivo');
        $this->assertTrue($persona->fresh()->activo);
        $this->assertDatabaseCount('cambios_estado_personas', 0);
    }

    public function test_muestra_errores_de_validacion_y_motivo_recuperado(): void
    {
        $persona = Alumno::factory()->create(['activo' => true, 'user_id' => null]);
        $this->withSession(['_old_input' => ['motivo' => 'Corto']]);
        request()->setLaravelSession(app('session')->driver());
        $view = $this->withViewErrors(['motivo' => 'El motivo debe tener al menos 10 caracteres.'])
            ->view('admin.estado-persona', [
                'tipo' => 'alumnos', 'persona' => $persona, 'revision' => 0,
                'asignaciones' => collect(), 'cierres' => null,
                'historial' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
            ]);
        $xpath = $this->dom((string) $view);
        $this->assertSame(1, $xpath->query('//*[@role="alert"]')->length);
        $this->assertSame('Corto', $xpath->evaluate('string(//textarea[@id="motivo-estado"])'));
        $this->assertSame('true', $xpath->evaluate('string(//textarea[@id="motivo-estado"]/@aria-invalid)'));
        $this->assertSame(1, $xpath->query('//*[@id="motivo-estado-error"]')->length);
        $this->assertTrue($persona->fresh()->activo);
        $this->assertDatabaseCount('cambios_estado_personas', 0);
    }

    public function test_historial_conserva_motivos_y_representa_ambos_estados(): void
    {
        $persona = Alumno::factory()->create(['user_id' => null]);
        $evento = CambioEstadoPersona::create([
            'alumno_id' => $persona->id, 'administrador_id' => auth()->id(),
            'estado_anterior' => true, 'estado_nuevo' => false,
            'motivo' => "Motivo de prueba <script>alert('x')</script>\nSegunda línea", 'registrado_at' => now(),
        ]);
        $original = $evento->fresh()->getAttributes();
        $this->get(route('admin.personas.estado', ['alumnos', $persona->id]))->assertOk()
            ->assertSee('Estado anterior')->assertSee('Estado nuevo')->assertSee('Administrador responsable')
            ->assertSee($evento->motivo)->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('ep-active', false)->assertSee('ep-inactive', false);
        $this->assertEquals($original, $evento->fresh()->getAttributes());
    }

    public function test_programadas_conservan_formulario_y_bloqueo_de_desactivacion(): void
    {
        $persona = Maestro::factory()->create(['activo' => true]);
        $detalle = DetalleCurso::factory()->create(['maestro_id' => $persona->id]);
        $asignacion = AsignacionDocente::create(['maestro_id' => $persona->id, 'detalle_curso_id' => $detalle->id, 'estado' => 'programada', 'fecha_inicio' => now()->addMonth()->toDateString()]);
        $response = $this->get(route('admin.personas.estado', ['maestros', $persona->id]))->assertOk()->assertSee('Asignaciones por resolver');
        $xpath = $this->dom($response->getContent());
        $this->assertSame(1, $xpath->query('//form[input[@name="revision"]]//button[@disabled]')->length);
        $forms = $xpath->query('//form[@action="'.route('admin.asignaciones.finalizar-programada', $asignacion->id).'"]');
        $this->assertSame(1, $forms->length);
        $this->assertSame('PATCH', $xpath->evaluate('string(input[@name="_method"]/@value)', $forms->item(0)));
        $this->assertSame('programada', $asignacion->fresh()->estado);
        $this->assertDatabaseCount('cierres_asignaciones_programadas', 0);
    }
}
