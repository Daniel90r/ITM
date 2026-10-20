<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, User};
use App\Services\DocumentosInscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentosInscripcionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ConEncargadoOficial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('inscripciones'); Storage::fake('public');
        config(['documentos_inscripcion.enabled' => true, 'documentos_inscripcion.legacy_read_enabled' => false]);
    }

    private function escenario(): array
    {
        $user = User::factory()->create(['role' => 'alumno']);
        $alumno = Alumno::factory()->create(['user_id' => $user->id, 'fecha_nacimiento' => '2000-01-01']);
        $curso = Curso::factory()->create(['estado' => 'PROXIMO', 'activo' => true, 'finalizado_manualmente' => false]);
        $ins = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'documento_path' => 'solicitudes/historico.pdf']);
        return [$user, $alumno, $curso, $ins];
    }

    public function test_descarga_propia_admin_y_cabeceras_con_bytes_originales(): void
    {
        [$user, , , $ins] = $this->escenario();
        $bytes = "%PDF-1.4\nDocumento ficticio\n%%EOF";
        Storage::disk('inscripciones')->put($ins->documento_path, $bytes);
        foreach ([[$user, 'alumno.inscripciones.documento'], [User::factory()->create(['role' => 'admin']), 'admin.solicitudes.documento']] as [$actor, $route]) {
            $response = $this->actingAs($actor)->get(route($route, $ins));
            $response->assertOk()->assertDownload('solicitud-inscripcion.pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Pragma', 'no-cache');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertSame($bytes, file_get_contents($response->baseResponse->getFile()->getPathname()));
            $this->actingAs($actor)->head(route($route, $ins))->assertOk()->assertHeader('Expires', '0');
        }
    }

    public function test_autorizacion_precede_a_lectura(): void
    {
        [, , , $ins] = $this->escenario();
        $otro = User::factory()->create(['role' => 'alumno']);
        Alumno::factory()->create(['user_id' => $otro->id]);
        $servicio = $this->mock(DocumentosInscripcion::class);
        $servicio->shouldNotReceive('localizar');
        $this->actingAs($otro)->get(route('alumno.inscripciones.documento', $ins))->assertNotFound();
        $docente = User::factory()->create(['role' => 'docente']);
        $this->actingAs($docente)->get(route('admin.solicitudes.documento', $ins))->assertForbidden();
        Alumno::factory()->create(['user_id' => $docente->id]);
        $this->actingAs($docente)->get(route('alumno.inscripciones.documento', $ins))->assertForbidden();
    }

    public function test_invitado_y_archivo_o_registro_inexistente(): void
    {
        [$user, , , $ins] = $this->escenario();
        foreach (['admin.solicitudes.documento', 'alumno.inscripciones.documento'] as $route) $this->get(route($route, $ins))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('alumno.inscripciones.documento', $ins))->assertNotFound();
        $this->get(route('alumno.inscripciones.documento', 999999))->assertNotFound();
    }

    public function test_compatibilidad_historica_es_optativa_y_no_copia_archivos(): void
    {
        [$user, , , $ins] = $this->escenario();
        Storage::disk('public')->put($ins->documento_path, 'historico ficticio');
        $this->actingAs($user)->get(route('alumno.inscripciones.documento', $ins))->assertNotFound();
        config(['documentos_inscripcion.legacy_read_enabled' => true]);
        $r = $this->get(route('alumno.inscripciones.documento', $ins))->assertOk();
        $this->assertSame('historico ficticio', file_get_contents($r->baseResponse->getFile()->getPathname()));
        Storage::disk('inscripciones')->assertMissing($ins->documento_path);
        $this->assertSame('solicitudes/historico.pdf', $ins->fresh()->documento_path);
    }

    public static function rutasInvalidas(): array
    {
        return array_map(fn ($p) => [$p], ['../secreto.pdf', 'solicitudes/../../secreto.pdf', '/tmp/a.pdf', 'C:\\secreto.pdf', 'https://ejemplo.test/a.pdf', 'solicitudes/a%2f..pdf', 'solicitudes\\a.pdf', 'directores/firmas/a.pdf']);
    }

    #[DataProvider('rutasInvalidas')]
    public function test_rechaza_rutas_manipuladas(string $ruta): void
    {
        [$user, , , $ins] = $this->escenario();
        $ins->update(['documento_path' => $ruta]);
        $this->actingAs($user)->get(route('alumno.inscripciones.documento', $ins))->assertNotFound();
    }

    public function test_nuevos_uuid_y_almacenamiento_sin_copias_publicas(): void
    {
        $s = app(DocumentosInscripcion::class);
        $ruta = $s->guardar('%PDF-ficticio');
        $this->assertMatchesRegularExpression('~^solicitudes/[0-9a-f-]{36}\.pdf$~', $ruta);
        $this->assertSame('%PDF-ficticio', Storage::disk('inscripciones')->get($ruta));
        $subido = $s->guardarSubido(UploadedFile::fake()->createWithContent('prueba.pdf', '%PDF-subido'));
        $this->assertSame('%PDF-subido', Storage::disk('inscripciones')->get($subido));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_subida_privada_real_del_controlador_y_reintento_protegido(): void
    {
        [$user, , $curso, $ins] = $this->escenario();
        $ins->update(['documento_path' => null, 'estado' => 'rechazada']);
        $r = $this->actingAs($user)->post(route('alumno.inscripcion.subir'), ['curso_id' => $curso->id, 'documento' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF")]);
        $r->assertSessionHasNoErrors();
        Storage::disk('inscripciones')->assertExists($ins->fresh()->documento_path);
        $ruta = $ins->fresh()->documento_path;
        $ins->update(['estado' => 'rechazada']);
        $this->post(route('alumno.inscripcion.subir'), ['curso_id' => $curso->id])->assertSessionHasErrors('documento');
        $this->assertSame($ruta, $ins->fresh()->documento_path);
        Storage::disk('inscripciones')->assertExists($ruta);
    }

    public function test_fallo_de_escritura_no_crea_inscripcion(): void
    {
        [$user, , $curso, $ins] = $this->escenario(); $ins->update(['documento_path' => null, 'estado' => 'rechazada']);
        $root = Storage::disk('inscripciones')->path('');
        $disk = \Mockery::mock(); $disk->shouldReceive('path')->with('')->andReturn($root); $disk->shouldReceive('exists')->andReturn(false); $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('inscripciones')->andReturn($disk);
        $this->actingAs($user)->post(route('alumno.inscripcion.subir'), ['curso_id' => $curso->id, 'documento' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHas('error');
        $this->assertNull($ins->fresh()->documento_path); $this->assertSame('rechazada', $ins->fresh()->estado);
    }

    public function test_desactivacion_y_rutas_previas(): void
    {
        [$user, , , $ins] = $this->escenario();
        config(['documentos_inscripcion.enabled' => false]);
        $this->actingAs($user)->get(route('alumno.inscripciones.documento', $ins))->assertNotFound();
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('alumno.inscripcion.pdf'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('alumno.inscripcion.enviar'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('alumno.inscripcion.subir'));
        $this->assertFalse((bool) (require config_path('documentos_inscripcion.php'))['enabled']);
    }

    public function test_enlace_fuera_de_raiz_se_rechaza(): void
    {
        [$user, , , $ins] = $this->escenario();
        $destino = Storage::disk('public')->path('fuera'); mkdir($destino);
        file_put_contents($destino.'/a.pdf', 'falso privado');
        Storage::disk('inscripciones')->makeDirectory('solicitudes');
        $link = Storage::disk('inscripciones')->path('solicitudes/escape');
        $creado = @symlink($destino, $link);
        if (!$creado && PHP_OS_FAMILY === 'Windows') {
            exec('cmd /c mklink /J '.escapeshellarg($link).' '.escapeshellarg($destino).' 2>NUL', $salida, $estado);
            $creado = $estado === 0;
        }
        if (!$creado) $this->markTestSkipped('El entorno no permite crear enlaces de prueba.');
        try {
            $ins->update(['documento_path' => 'solicitudes/escape/a.pdf']);
            $this->actingAs($user)->get(route('alumno.inscripciones.documento', $ins))->assertNotFound();
        } finally {
            // Windows elimina con rmdir los enlaces/junctions a directorios.
            if (PHP_OS_FAMILY === 'Windows' || !is_link($link)) rmdir($link); else unlink($link);
        }
    }

    public function test_planificador_solo_lee_y_detecta_colisiones_invalidos_y_sin_referencia(): void
    {
        [, , , $ins] = $this->escenario();
        Storage::disk('public')->put($ins->documento_path, 'original');
        Storage::disk('inscripciones')->put($ins->documento_path, 'distinto');
        Storage::disk('public')->put('solicitudes/sin-referencia.pdf', 'sin referencia');
        Inscripcion::factory()->create(['documento_path' => '../invalido.pdf']);
        $antes = $ins->fresh()->getRawOriginal();
        $this->artisan('inscripciones:planificar-documentos')->expectsOutputToContain('"colisiones":1')->assertSuccessful();
        $this->assertSame($antes, $ins->fresh()->getRawOriginal());
        $this->assertSame('original', Storage::disk('public')->get($ins->documento_path));
        $this->artisan('inscripciones:planificar-documentos', ['--manifest' => true])->assertSuccessful();
        $manifiestos = Storage::disk('inscripciones')->files('manifiestos');
        $this->assertCount(1, $manifiestos);
        $manifest = json_decode(Storage::disk('inscripciones')->get($manifiestos[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $manifest['resumen']['colisiones']);
        $this->assertSame(1, $manifest['resumen']['referencias_invalidas']);
        $this->assertSame(1, $manifest['resumen']['sin_referencia']);
    }

    public static function edades(): array { return [[true], [false]]; }

    #[DataProvider('edades')]
    public function test_pdf_nuevo_adulto_y_menor_se_guarda_en_privado(bool $mayor): void
    {
        [$user, $alumno, $curso, $ins] = $this->escenario();
        $alumno->update(['fecha_nacimiento' => $mayor ? '2000-01-01' : now()->subYears(15)->toDateString()]);
        if (!$mayor) $this->prepararEncargadoOficial($alumno);
        $ins->update(['documento_path' => null, 'estado' => 'rechazada']);
        $imagen = imagecreatetruecolor(4, 4); ob_start(); imagepng($imagen); $png = ob_get_clean(); imagedestroy($imagen);
        $firma = 'data:image/png;base64,'.base64_encode($png);
        $this->actingAs($user)->post(route('alumno.inscripcion.enviar'), [
            'curso_id' => $curso->id, 'alumno_dui' => '12345678-9', 'firma_alumno' => $firma, 'firma_encargado' => $firma,
        ])->assertSessionHasNoErrors();
        $path = $ins->fresh()->documento_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('inscripciones')->get($path));
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('alumno.inscripciones.documento', $ins))->assertOk();
    }
}
