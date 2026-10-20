<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Encargado, Inscripcion, Materia, User};
use App\Services\EncargadoInscripcion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EncargadoInscripcionTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $alumno;
    private Curso $curso;
    private User $usuario;

    protected function beforeRefreshingDatabase(): void
    {
        // Se ejecuta ANTES de cualquier migración de RefreshDatabase.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->usuario = User::factory()->create(['role' => 'alumno']);
        $this->alumno = Alumno::factory()->create([
            'user_id' => $this->usuario->id,
            'fecha_nacimiento' => now()->subYears(16)->toDateString(),
        ]);
        $this->curso = Curso::factory()->create([
            'estado' => Curso::ESTADO_PROXIMO,
            'anio_lectivo' => now()->year,
        ]);
        $this->actingAs($this->usuario);
    }

    private function vincular(array $datos = [], string $parentesco = 'Madre'): Encargado
    {
        $encargado = Encargado::create(array_merge([
            'nombre' => 'Nombre Oficial', 'apellido' => 'Apellido Oficial',
            'dui' => '12345678-9', 'telefono' => null, 'activo' => true,
        ], $datos));
        $this->alumno->encargados()->attach($encargado->id, ['parentesco' => $parentesco]);

        return $encargado;
    }

    private function payload(string $ruta): array
    {
        return match ($ruta) {
            'alumno.inscripcion.subir' => [
                'curso_id' => $this->curso->id,
                'documento' => UploadedFile::fake()->create('solicitud.pdf', 1, 'application/pdf'),
                'tomara_ingles' => 1,
            ],
            'alumno.inscripcion.enviar' => [
                'curso_id' => $this->curso->id, 'tomara_ingles' => 1,
                'firma_alumno' => $this->firma(), 'firma_encargado' => $this->firma(),
            ],
            default => ['curso_id' => $this->curso->id],
        };
    }

    private function firma(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
    }

    public static function rutasValidas(): array
    {
        return array_map(fn ($ruta) => [$ruta], [
            'alumno.inscripcion.enviar', 'alumno.inscribirse', 'alumno.inscripcion.subir',
        ]);
    }

    public static function bloqueos(): array
    {
        $casos = [];
        foreach (self::rutasValidas() as [$ruta]) {
            foreach (['sin_vinculo', 'inactivo', 'nombre', 'apellido', 'dui', 'dui_invalido', 'parentesco', 'multiples'] as $caso) {
                $casos[$ruta.'-'.$caso] = [$ruta, $caso];
            }
        }

        return $casos;
    }

    #[DataProvider('bloqueos')]
    public function test_las_tres_rutas_bloquean_expedientes_invalidos(string $ruta, string $caso): void
    {
        if ($caso !== 'sin_vinculo') {
            $datos = match ($caso) {
                'inactivo' => ['activo' => false],
                'nombre', 'apellido', 'dui' => [$caso => ''],
                'dui_invalido' => ['dui' => 'invalido'],
                default => [],
            };
            $this->vincular($datos, $caso === 'parentesco' ? '' : 'Madre');
            if ($caso === 'multiples') {
                $this->vincular(['dui' => '98765432-1']);
            }
        }

        $this->get(route('alumno.inscripcion'))->assertOk()
            ->assertSee('Contacta a Administración')->assertSee('disabled', false);
        $this->post(route($ruta), $this->payload($ruta))->assertSessionHasErrors('encargado');
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('rutasValidas')]
    public function test_datos_oficiales_y_telefono_opcional_en_las_tres_rutas(string $ruta): void
    {
        $encargado = $this->vincular();
        $this->post(route($ruta), array_merge($this->payload($ruta), [
            'encargado_nombre' => 'Manipulado', 'encargado_apellido' => 'Falso',
            'encargado_dui' => '99999999-9', 'encargado_parentesco' => 'Otro',
            'encargado_telefono' => '0000-0000', 'encargado_id' => 999,
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $solicitud = Inscripcion::sole();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertFalse((bool) $solicitud->activa);
        $datos = json_decode($solicitud->observacion, true);
        $this->assertSame($encargado->id, $datos['encargado_id']);
        $this->assertSame('Nombre Oficial', $datos['encargado_nombre']);
        $this->assertSame('Madre', $datos['encargado_parentesco']);
        $this->assertNull($datos['encargado_telefono']);
        $this->assertDatabaseCount('encargados', 1);
        $this->assertDatabaseCount('alumno_encargado', 1);
        if ($ruta !== 'alumno.inscribirse') {
            $this->assertTrue((bool) $solicitud->tomara_ingles);
            Storage::disk('public')->assertExists($solicitud->documento_path);
        }
        if ($ruta === 'alumno.inscripcion.enviar') {
            $this->assertStringStartsWith('%PDF-', Storage::disk('public')->get($solicitud->documento_path));
        }
    }

    public function test_formulario_muestra_expediente_sin_inputs_de_identidad(): void
    {
        $this->vincular(['telefono' => '7777-8888']);
        $respuesta = $this->withSession(['_old_input' => ['encargado_nombre' => 'Manipulado']])
            ->get(route('alumno.inscripcion'))->assertOk();
        foreach (['Nombre Oficial', 'Apellido Oficial', '12345678-9', '7777-8888', 'Madre'] as $valor) {
            $respuesta->assertSee($valor);
        }
        foreach (['nombre', 'apellido', 'dui', 'telefono', 'parentesco'] as $campo) {
            $respuesta->assertDontSee('name="encargado_'.$campo.'"', false);
        }
        $respuesta->assertDontSee('Manipulado')->assertSee('id="firmaEncargado"', false)
            ->assertSee('data-encargado-bloqueado="0"', false);
    }

    public function test_firmas_son_obligatorias_para_el_envio_digital(): void
    {
        $this->vincular();
        foreach (['firma_alumno', 'firma_encargado'] as $campo) {
            $datos = $this->payload('alumno.inscripcion.enviar');
            unset($datos[$campo]);
            $this->post(route('alumno.inscripcion.enviar'), $datos)->assertSessionHasErrors($campo);
        }
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_pdf_recibe_identidad_oficial_y_ambas_firmas(): void
    {
        $this->vincular();
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($vista, $datos) {
            $this->assertSame('alumno.pdf.solicitud-inscripcion', $vista);
            $this->assertSame('Nombre Oficial', $datos['encargado_nombre']);
            $this->assertSame('Apellido Oficial', $datos['encargado_apellido']);
            $this->assertSame($this->firma(), $datos['firma_alumno']);
            $this->assertSame($this->firma(), $datos['firma_encargado']);
            $html = view($vista, $datos)->render();
            $this->assertStringContainsString('Nombre Oficial Apellido Oficial', $html);
            $this->assertSame(2, substr_count($html, '<img src="data:image/png;base64,'));

            return true;
        })->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturn('%PDF-prueba');
        $this->post(route('alumno.inscripcion.enviar'), $this->payload('alumno.inscripcion.enviar'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_descarga_pdf_real_sin_curso_seleccionado(): void
    {
        $this->vincular();
        $respuesta = $this->get(route('alumno.inscripcion.pdf'))->assertOk();
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_descarga_pdf_bloqueada_sin_encargado(): void
    {
        $this->get(route('alumno.inscripcion.pdf'))->assertSessionHasErrors('encargado');
    }

    #[DataProvider('rutasValidas')]
    public function test_reintento_reutiliza_solicitud_y_actualiza_snapshot_oficial(string $ruta): void
    {
        $this->vincular();
        Storage::disk('public')->put('solicitudes/anterior.pdf', 'historico');
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now(), 'estado' => 'rechazada', 'activa' => false,
            'motivo_rechazo' => 'Revisar', 'documento_path' => 'solicitudes/anterior.pdf',
            'observacion' => '{"encargado_nombre":"Antiguo"}',
        ]);
        $this->post(route($ruta), $this->payload($ruta))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('inscripciones', 1);
        $solicitud->refresh();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertNull($solicitud->motivo_rechazo);
        $this->assertSame('Nombre Oficial', json_decode($solicitud->observacion, true)['encargado_nombre']);
        if ($ruta !== 'alumno.inscribirse') {
            Storage::disk('public')->assertMissing('solicitudes/anterior.pdf');
            Storage::disk('public')->assertExists($solicitud->documento_path);
        }
    }

    public function test_aprobacion_antigua_conserva_historia_y_no_modifica_encargados(): void
    {
        $encargado = $this->vincular();
        $historia = json_encode(['encargado_nombre' => 'Manipulado', 'encargado_apellido' => 'Falso',
            'encargado_dui' => '99999999-9', 'encargado_parentesco' => 'Otro']);
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now(), 'estado' => 'pendiente', 'activa' => false,
            'observacion' => $historia, 'documento_path' => 'solicitudes/historico.pdf',
        ]);
        Storage::disk('public')->put($solicitud->documento_path, 'historico');
        $antes = $encargado->fresh()->getAttributes();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.solicitudes.aprobar', $solicitud->id))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('aprobada', $solicitud->fresh()->estado);
        $this->assertSame($historia, $solicitud->fresh()->observacion);
        $this->assertSame($antes, $encargado->fresh()->getAttributes());
        $this->assertDatabaseCount('encargados', 1);
        $this->assertDatabaseCount('alumno_encargado', 1);
        $this->assertSame('Madre', $this->alumno->encargados()->sole()->pivot->parentesco);
        Storage::disk('public')->assertExists($solicitud->documento_path);
    }

    public function test_aprobacion_antigua_sin_vinculo_no_crea_encargado_desde_json(): void
    {
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now(), 'estado' => 'pendiente', 'activa' => false,
            'observacion' => json_encode(['encargado_nombre' => 'Antiguo', 'encargado_apellido' => 'Apellido',
                'encargado_dui' => '12345678-9', 'encargado_parentesco' => 'Madre']),
        ]);
        $antes = $solicitud->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.solicitudes.aprobar', $solicitud->id))->assertSessionHasErrors('encargado');
        $this->assertSame($antes, $solicitud->fresh()->getAttributes());
        $this->assertDatabaseCount('encargados', 0);
        $this->assertDatabaseCount('alumno_encargado', 0);
    }

    public function test_aprobacion_revalida_encargado_y_conserva_opcion_de_ingles(): void
    {
        $encargado = $this->vincular();
        $ingles = Materia::factory()->create(['nombre' => 'Ingles', 'codigo' => 'ING', 'activa' => true]);
        $this->post(route('alumno.inscripcion.enviar'), $this->payload('alumno.inscripcion.enviar'))
            ->assertSessionHasNoErrors();
        $solicitud = Inscripcion::sole();
        $admin = User::factory()->create(['role' => 'admin']);
        $encargado->update(['activo' => false]);
        $this->actingAs($admin)->post(route('admin.solicitudes.aprobar', $solicitud->id))
            ->assertSessionHasErrors('encargado');
        $this->assertSame('pendiente', $solicitud->fresh()->estado);
        $this->assertDatabaseCount('alumno_materia', 0);

        $encargado->update(['activo' => true]);
        $this->actingAs($admin)->post(route('admin.solicitudes.aprobar', $solicitud->id))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('aprobada', $solicitud->fresh()->estado);
        $this->assertDatabaseHas('alumno_materia', [
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id, 'materia_id' => $ingles->id,
        ]);
    }

    public function test_expediente_modificado_durante_pdf_bloquea_y_limpia_solo_archivo_nuevo(): void
    {
        $encargado = $this->vincular();
        $solicitud = Inscripcion::create([
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now(), 'estado' => 'rechazada', 'activa' => false,
            'documento_path' => 'solicitudes/anterior.pdf', 'observacion' => 'Historia',
        ]);
        Storage::disk('public')->put('solicitudes/anterior.pdf', 'anterior');
        $antes = $solicitud->fresh()->getAttributes();
        Pdf::shouldReceive('loadView')->once()->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturnUsing(function () use ($encargado) {
            $encargado->update(['nombre' => 'Cambio Administrativo']);

            return '%PDF-prueba';
        });
        $this->post(route('alumno.inscripcion.enviar'), $this->payload('alumno.inscripcion.enviar'))
            ->assertSessionHasErrors('encargado');
        $this->assertSame($antes, $solicitud->fresh()->getAttributes());
        $this->assertSame(['solicitudes/anterior.pdf'], Storage::disk('public')->allFiles());
    }

    public function test_servicio_no_usa_relacion_cacheada_y_parentesco_procede_del_pivote(): void
    {
        $encargado = $this->vincular();
        $this->alumno->load('encargados');
        $encargado->update(['activo' => false]);
        $this->assertNotNull(app(EncargadoInscripcion::class)->inspeccionar($this->alumno)['error']);
        $encargado->update(['activo' => true]);
        $this->alumno->encargados()->updateExistingPivot($encargado->id, ['parentesco' => 'Tutor']);
        $this->assertSame('Tutor', app(EncargadoInscripcion::class)->exigir($this->alumno)['encargado_parentesco']);
    }

    public function test_mayor_exige_dui_y_firma_propia_y_descarga_pdf_sin_encargado(): void
    {
        $this->alumno->update(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $this->post(route('alumno.inscripcion.enviar'), ['curso_id' => $this->curso->id])
            ->assertSessionHasErrors(['alumno_dui', 'firma_alumno']);
        $respuesta = $this->get(route('alumno.inscripcion.pdf'))->assertOk();
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
    }

    #[DataProvider('rutasValidas')]
    public function test_mayores_sin_encargado_conservan_sus_rutas(string $ruta): void
    {
        $this->alumno->update(['fecha_nacimiento' => now()->subYears(18)->toDateString()]);
        $this->get(route('alumno.inscripcion'))->assertOk()->assertDontSee('id="firmaEncargado"', false);
        $datos = $this->payload($ruta);
        unset($datos['firma_encargado']);
        $datos['alumno_dui'] = '01234567-8';
        $this->post(route($ruta), $datos)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('pendiente', Inscripcion::sole()->estado);
        if ($ruta === 'alumno.inscripcion.enviar') {
            $this->assertSame('01234567-8', $this->alumno->fresh()->dui);
        }
    }
}
