<?php

namespace Tests\Feature;

use App\Models\{DocumentoInscripcion, Inscripcion};
use App\Services\{ControlTransicionDocumental, TransicionDocumental};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Inventario únicamente: no invoca ejecutar, ejecutarReal ni el comando de transición. */
class DocumentalInventoryPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['public', 'inscripciones'] as $disk) {
            $fake = Storage::fake($disk);
            config(["filesystems.disks.$disk.root" => rtrim($fake->path(''), '/\\')]);
            app(ControlTransicionDocumental::class)->exigirTemporal($fake->path(''));
        }
        config(['documentos_inscripcion.enabled' => false, 'documentos_inscripcion.history_enabled' => false,
            'documentos_inscripcion.legacy_read_enabled' => false, 'documentos_inscripcion.transition_guard_enabled' => false]);
    }

    public function test_inventario_109_ficticios_separa_seis_asociados_y_103_sin_propietario_sin_escribir(): void
    {
        $esperados = [];
        for ($i = 1; $i <= 109; $i++) {
            $ruta = 'solicitudes/ficticio-'.$i.'.pdf';
            $bytes = "%PDF-1.4\nFICTICIO SIN DATOS PERSONALES $i\n%%EOF";
            Storage::disk('public')->put($ruta, $bytes);
            if ($i <= 6) Inscripcion::factory()->create(['documento_path' => $ruta]);
            $esperados[$ruta] = ['bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
        }
        $referencias = Inscripcion::orderBy('id')->get()->toJson();
        $archivos = Storage::disk('public')->allFiles();
        $servicio = app(TransicionDocumental::class);
        DB::enableQueryLog();
        $plan = $servicio->planificar();
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($consultas as $q) $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $q['query']);
        $resumen = $servicio->resumen($plan);
        $this->assertSame(109, $resumen['archivos']);
        $this->assertSame(6, $resumen['asociados']);
        $this->assertSame(103, $resumen['cuarentena']);
        $this->assertSame(0, $resumen['contenidos_repetidos']);
        foreach ($plan['archivos'] as $f) {
            $this->assertSame($esperados[$f['ruta']]['bytes'], $f['bytes']);
            $this->assertSame($esperados[$f['ruta']]['sha256'], $f['sha256']);
        }
        $this->assertSame($archivos, Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
        $this->assertSame($referencias, Inscripcion::orderBy('id')->get()->toJson());
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertDirectoryDoesNotExist(storage_path('framework/transicion-documental'));
        $this->assertSame($plan, $servicio->planificar());
    }

    public static function inconsistencias(): array
    {
        return [['faltante', 'ORIGINAL_FALTANTE'], ['compartida', 'REFERENCIA_COMPARTIDA'],
            ['destino', 'DESTINO_DIFERENTE'], ['insegura', 'REFERENCIA_INSEGURA']];
    }

    #[DataProvider('inconsistencias')]
    public function test_plan_detiene_inconsistencias_sin_ejecutar_transicion(string $caso, string $error): void
    {
        $ruta = 'solicitudes/ficticio.pdf';
        Storage::disk('public')->put($ruta, '%PDF-FICTICIO ORIGINAL');
        Inscripcion::factory()->create(['documento_path' => $caso === 'insegura' ? '../escape.pdf' : $ruta]);
        if ($caso === 'faltante') Storage::disk('public')->delete($ruta);
        if ($caso === 'compartida') Inscripcion::factory()->create(['documento_path' => $ruta]);
        if ($caso === 'destino') Storage::disk('inscripciones')->put($ruta, '%PDF-FICTICIO DIFERENTE');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($error);
        app(TransicionDocumental::class)->planificar();
    }

    public function test_huella_detecta_cambio_de_igual_tamano_y_no_infiere_asociaciones_por_contenido(): void
    {
        $ruta = 'solicitudes/ficticio.pdf';
        Storage::disk('public')->put($ruta, '%PDF-FICTICIO A');
        Storage::disk('public')->put('solicitudes/desconocido.pdf', '%PDF-FICTICIO A');
        Inscripcion::factory()->create(['documento_path' => $ruta]);
        $s = app(TransicionDocumental::class);
        $antes = $s->planificar();
        $this->assertSame(1, $s->resumen($antes)['asociados']);
        $this->assertSame(1, $s->resumen($antes)['cuarentena']);
        Storage::disk('public')->put($ruta, '%PDF-FICTICIO B');
        $despues = $s->planificar();
        $a = collect($antes['archivos'])->firstWhere('ruta', $ruta);
        $b = collect($despues['archivos'])->firstWhere('ruta', $ruta);
        $this->assertSame($a['bytes'], $b['bytes']);
        $this->assertNotSame($a['sha256'], $b['sha256']);
        $this->assertNotSame(hash('sha256', json_encode($antes)), hash('sha256', json_encode($despues)));
        $this->assertSame(0, DocumentoInscripcion::count());
    }
}
