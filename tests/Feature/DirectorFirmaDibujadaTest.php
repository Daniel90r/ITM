<?php

namespace Tests\Feature;

use App\Models\{Alumno, Boleta, Curso, DetalleCurso, Director, Inscripcion, Materia, Nota, User};
use App\Services\BoletaService;
use App\Exceptions\BoletaEmissionException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DirectorFirmaDibujadaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('boletas_oficiales');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function png(bool $trazos = true, bool $transparente = true): string
    {
        $image = imagecreatetruecolor(300, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, $transparente ? 127 : 0));
        if ($trazos) {
            imagesetthickness($image, 3);
            imageline($image, 20, 70, 260, 30, imagecolorallocate($image, 20, 30, 40));
        }
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        return $bytes;
    }

    private function dibujo(bool $trazos = true, bool $transparente = true): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($trazos, $transparente));
    }

    private function registrar(array $datos = [])
    {
        return $this->post(route('admin.directores.store'), array_merge([
            'nombre' => 'Director de prueba', 'fecha_inicio' => now()->subMonth()->toDateString(),
            'firma_origen' => 'dibujar', 'firma_dibujada' => $this->dibujo(),
        ], $datos));
    }

    public function test_registro_png_privado_sin_base64_ni_rutas_del_cliente(): void
    {
        $this->registrar(['firma_path' => '../../public/firma.png'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $director = Director::sole();
        $this->assertStringStartsWith('directores/firmas/', $director->firma_path);
        $this->assertStringEndsWith('.png', $director->firma_path);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring(Storage::disk('local')->get($director->firma_path))[2]);
        $this->assertStringNotContainsString('base64', $director->firma_path);
        Storage::disk('public')->assertMissing($director->firma_path);
        $this->get(route('admin.directores.firma.show', $director))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public static function entradasInvalidas(): array
    {
        return [['transparente'], ['blanca'], ['texto'], ['base64_invalido'], ['jpeg_disfrazado'], ['demasiados_bytes'], ['sin_trazo'], ['dimensiones']];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_firmas_invalidas_o_vacias_sin_escribir(string $tipo): void
    {
        $data = match ($tipo) {
            'transparente' => $this->dibujo(false),
            'blanca' => $this->dibujo(false, false),
            'texto' => 'data:image/png;base64,'.base64_encode('no imagen'),
            'base64_invalido' => 'data:image/png;base64,@@@',
            'jpeg_disfrazado' => 'data:image/jpeg;base64,'.base64_encode($this->png()),
            'demasiados_bytes' => 'data:image/png;base64,'.base64_encode(str_repeat('a', 2 * 1024 * 1024 + 1)),
            'sin_trazo' => '',
            'dimensiones' => $this->imagenGrande(),
        };
        $this->registrar(['firma_dibujada' => $data])->assertSessionHasErrors('firma_dibujada');
        $this->assertDatabaseCount('directores', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function imagenGrande(): string
    {
        $image = imagecreatetruecolor(2000, 1100);
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    public function test_registro_opcional_y_archivo_alternativo(): void
    {
        $this->post(route('admin.directores.store'), ['nombre' => 'Sin firma', 'fecha_inicio' => now()->subYear()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertNull(Director::sole()->firma_path);
        $this->post(route('admin.directores.store'), [
            'nombre' => 'Con archivo', 'fecha_inicio' => now()->toDateString(),
            'firma' => UploadedFile::fake()->image('firma.jpg', 100, 100),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertNotNull(Director::activo()->sole()->firma_path);
    }

    public function test_no_acepta_dos_fuentes_simultaneas(): void
    {
        $this->registrar(['firma' => UploadedFile::fake()->image('firma.png')])->assertSessionHasErrors();
        $this->assertDatabaseCount('directores', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_actualiza_solo_firma_conservando_archivo_anterior_y_datos(): void
    {
        $this->registrar()->assertSessionHasNoErrors();
        $director = Director::sole();
        $antes = $director->getAttributes();
        $oldPath = $director->firma_path;
        $this->patch(route('admin.directores.firma.update', $director), [
            'firma_dibujada' => $this->dibujo(true, false), 'nombre' => 'Manipulado', 'activo' => false,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $director->refresh();
        $this->assertNotSame($oldPath, $director->firma_path);
        foreach (['nombre', 'fecha_inicio', 'fecha_fin', 'user_id', 'activo'] as $campo) $this->assertSame($antes[$campo], $director->getAttributes()[$campo]);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($director->firma_path);
    }

    public function test_actualizacion_vacia_no_cambia_firma_y_no_abre_modal_de_registro(): void
    {
        $this->registrar();
        $director = Director::sole();
        $antes = $director->firma_path;
        $this->patch(route('admin.directores.firma.update', $director), [])
            ->assertSessionHasErrorsIn('firmaDirector', 'firma');
        $this->assertSame($antes, $director->fresh()->firma_path);
        $this->get(route('admin.directores.index'))->assertOk()->assertSee('data-firma-director', false);
    }

    public function test_historico_no_admite_sustituir_su_firma(): void
    {
        $this->registrar();
        $director = Director::sole();
        $director->update(['activo' => false, 'fecha_fin' => now()->subDay()]);
        $antes = $director->firma_path;
        $this->patch(route('admin.directores.firma.update', $director), ['firma_dibujada' => $this->dibujo()])
            ->assertSessionHasErrorsIn('firmaDirector', 'firma');
        $this->assertSame($antes, $director->fresh()->firma_path);
    }

    public function test_si_nombramiento_falla_solo_se_limpia_archivo_nuevo(): void
    {
        $this->registrar();
        $files = Storage::disk('local')->allFiles();
        $this->registrar(['fecha_inicio' => now()->subYears(2)->toDateString()])->assertSessionHasErrors('fecha_inicio');
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('directores', 1);
    }

    private function escenarioAcademico(): array
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'aprobada']);
        $materia = Materia::factory()->create(['es_opcional' => false]);
        $detalle = DetalleCurso::factory()->create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
        Nota::factory()->create(['alumno_id' => $alumno->id, 'detalle_curso_id' => $detalle->id, 'promedio' => 8, 'conducta' => 'B']);
        return [$alumno, $curso];
    }

    public function test_boleta_emitida_copia_firma_y_pdf_usa_snapshot_historico(): void
    {
        $this->registrar();
        $director = Director::sole();
        [$alumno, $curso] = $this->escenarioAcademico();
        $boleta = app(BoletaService::class)->emitir($alumno->id, $curso->id);
        $snapshot = Storage::disk('local')->get($boleta->director_firma_snapshot_path);
        $this->assertSame(Storage::disk('local')->get($director->firma_path), $snapshot);
        $antes = $boleta->getAttributes();
        $this->patch(route('admin.directores.firma.update', $director), ['firma_dibujada' => $this->dibujo(true, false)])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.directores.store'), ['nombre' => 'Otro Director', 'fecha_inicio' => now()->addDay()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame($antes, $boleta->fresh()->getAttributes());
        $this->assertSame($snapshot, Storage::disk('local')->get($boleta->director_firma_snapshot_path));

        $response = $this->get(route('admin.boletas.pdf', $alumno->id).'?curso_id='.$curso->id)->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertTrue(Storage::disk('boletas_oficiales')->exists($boleta->id.'/oficial.pdf'));
    }

    public function test_pdf_real_con_png_del_director(): void
    {
        $this->registrar();
        [$alumno, $curso] = $this->escenarioAcademico();
        $boleta = app(BoletaService::class)->emitir($alumno->id, $curso->id);
        $bytes = Storage::disk('boletas_oficiales')->get($boleta->id.'/oficial.pdf');
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('/Subtype /Image', $bytes);
    }

    public function test_nuevas_boletas_usan_firma_actualizada_sin_tocar_snapshot_anterior(): void
    {
        $this->registrar();
        $director = Director::sole();
        [$alumno, $curso] = $this->escenarioAcademico();
        $primera = app(BoletaService::class)->emitir($alumno->id, $curso->id);
        $original = Storage::disk('local')->get($primera->director_firma_snapshot_path);
        $this->patch(route('admin.directores.firma.update', $director), ['firma_dibujada' => $this->dibujo(true, false)])
            ->assertSessionHasNoErrors();
        [$otroAlumno, $otroCurso] = $this->escenarioAcademico();
        $segunda = app(BoletaService::class)->emitir($otroAlumno->id, $otroCurso->id);
        $this->assertSame($director->id, $segunda->director_id);
        $this->assertSame(Storage::disk('local')->get($director->fresh()->firma_path), Storage::disk('local')->get($segunda->director_firma_snapshot_path));
        $this->assertNotSame($original, Storage::disk('local')->get($segunda->director_firma_snapshot_path));
        $this->assertSame($original, Storage::disk('local')->get($primera->director_firma_snapshot_path));
    }

    public function test_pdf_sin_snapshot_no_sustituye_firma_por_la_del_director_actual(): void
    {
        $this->registrar();
        [$alumno, $curso] = $this->escenarioAcademico();
        $boleta = app(BoletaService::class)->emitir($alumno->id, $curso->id);
        // Simular una ruta ausente sin eliminar archivos de firmas.
        $boleta->update(['director_firma_snapshot_path' => 'boletas/firmas/'.$boleta->id.'/ausente.png']);
        $this->get(route('admin.boletas.pdf', $alumno->id).'?curso_id='.$curso->id)->assertOk();
    }

    public function test_sin_firma_emision_sigue_bloqueada(): void
    {
        $this->post(route('admin.directores.store'), ['nombre' => 'Sin firma', 'fecha_inicio' => now()->subMonth()->toDateString()]);
        [$alumno, $curso] = $this->escenarioAcademico();
        $this->expectException(BoletaEmissionException::class);
        $this->expectExceptionMessage('firma válida');
        app(BoletaService::class)->emitir($alumno->id, $curso->id);
    }

    public function test_permisos_impiden_registrar_actualizar_y_leer_firma(): void
    {
        $this->registrar();
        $director = Director::sole();
        $antes = $director->getAttributes();
        foreach (['alumno', 'docente'] as $rol) {
            $this->actingAs(User::factory()->create(['role' => $rol]));
            $this->registrar()->assertForbidden();
            $this->patch(route('admin.directores.firma.update', $director), ['firma_dibujada' => $this->dibujo()])->assertForbidden();
            $this->get(route('admin.directores.firma.show', $director))->assertForbidden();
        }
        $this->assertSame($antes, $director->fresh()->getAttributes());
        $this->assertDatabaseCount('directores', 1);
    }
}
