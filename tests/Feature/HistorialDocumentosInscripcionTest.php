<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, DocumentoInscripcion, Inscripcion, User};
use App\Services\{DocumentosInscripcion, HistorialDocumentosInscripcion};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistorialDocumentosInscripcionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ConEncargadoOficial;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); Storage::fake('inscripciones');
        config(['documentos_inscripcion.enabled' => true, 'documentos_inscripcion.history_enabled' => true,
            'documentos_inscripcion.legacy_read_enabled' => false]);
    }

    private function contexto(): array
    {
        $user = User::factory()->create(['role' => 'alumno']);
        $alumno = Alumno::factory()->create(['user_id' => $user->id, 'fecha_nacimiento' => '2000-01-01']);
        $curso = Curso::factory()->create(['estado' => 'PROXIMO', 'activo' => true, 'finalizado_manualmente' => false]);
        return [$user, $alumno, $curso];
    }

    private function enviar(User $user, Curso $curso, string $contenido = 'primera'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('alumno.inscripcion.subir'), [
            'curso_id' => $curso->id,
            'documento' => UploadedFile::fake()->createWithContent('documento.pdf', "%PDF-1.4\n$contenido\n%%EOF"),
        ]);
    }

    public function test_primera_presentacion_y_reintento_conservan_bytes_y_versiones(): void
    {
        [$user, $alumno, $curso] = $this->contexto();
        $this->enviar($user, $curso)->assertSessionHasNoErrors();
        $ins = $alumno->inscripciones()->firstOrFail();
        $v1 = $ins->documentoVigente()->firstOrFail();
        $bytes1 = Storage::disk('inscripciones')->get($v1->ruta);
        $this->assertSame(1, $v1->version);
        $this->assertSame(hash('sha256', $bytes1), $v1->sha256);
        $this->assertSame(strlen($bytes1), $v1->bytes);
        $this->assertSame($v1->ruta, $ins->documento_path);
        $ins->update(['estado' => 'rechazada', 'activa' => false]);
        $this->enviar($user, $curso, 'segunda')->assertSessionHasNoErrors();
        $this->assertSame([1, 2], $ins->documentos()->pluck('version')->all());
        $this->assertSame(1, $ins->documentos()->where('vigente', '1')->count());
        $this->assertFalse((bool) $v1->fresh()->vigente);
        $this->assertSame($bytes1, Storage::disk('inscripciones')->get($v1->ruta));
        $this->assertSame(2, $ins->documentoVigente()->first()->version);
        $this->assertSame($ins->fresh()->documento_path, $ins->documentoVigente()->first()->ruta);
        $this->assertSame('pendiente', $ins->fresh()->estado);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_duplicado_de_contenido_reutiliza_archivo_y_version(): void
    {
        [$user, $alumno, $curso] = $this->contexto();
        $this->enviar($user, $curso)->assertSessionHasNoErrors();
        $ins = $alumno->inscripciones()->first(); $ruta = $ins->documento_path;
        $ins->update(['estado' => 'rechazada']);
        $this->enviar($user, $curso)->assertSessionHasNoErrors();
        $this->assertSame(1, $ins->documentos()->count());
        $this->assertSame($ruta, $ins->fresh()->documento_path);
        $this->assertSame([$ruta], Storage::disk('inscripciones')->allFiles());
    }

    public function test_importacion_simulada_es_idempotente_y_no_asocia_archivos_sueltos(): void
    {
        [, $alumno, $curso] = $this->contexto();
        $ins = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id,
            'documento_path' => 'solicitudes/antiguo.pdf', 'estado' => 'rechazada']);
        config(['documentos_inscripcion.legacy_read_enabled' => true]);
        Storage::disk('public')->put($ins->documento_path, '%PDF-original');
        Storage::disk('public')->put('solicitudes/sin-asociacion.pdf', '%PDF-suelto');
        $s = app(HistorialDocumentosInscripcion::class);
        $v = $s->incorporarReferenciaVerificada($ins);
        $this->assertSame($v->id, $s->incorporarReferenciaVerificada($ins)->id);
        $this->assertSame('public', $v->disco);
        $this->assertSame(1, DocumentoInscripcion::count());
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
        $this->assertCount(2, Storage::disk('public')->allFiles());
        $this->assertSame('rechazada', $ins->fresh()->estado);
    }

    public function test_reintento_incorpora_anterior_verificable_sin_copiarlo(): void
    {
        [$user, $alumno, $curso] = $this->contexto();
        $ins = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id,
            'documento_path' => 'solicitudes/antiguo.pdf', 'estado' => 'rechazada']);
        config(['documentos_inscripcion.legacy_read_enabled' => true]);
        Storage::disk('public')->put($ins->documento_path, '%PDF-anterior');
        $this->enviar($user, $curso, 'nuevo')->assertSessionHasNoErrors();
        $this->assertSame(['public', 'inscripciones'], $ins->documentos()->pluck('disco')->all());
        $this->assertSame('%PDF-anterior', Storage::disk('public')->get('solicitudes/antiguo.pdf'));
        $this->assertCount(1, Storage::disk('inscripciones')->allFiles());
    }

    public function test_fallo_de_metadatos_revierte_todo_y_solo_compensa_archivo_nuevo(): void
    {
        [$user, $alumno, $curso] = $this->contexto();
        $this->enviar($user, $curso)->assertSessionHasNoErrors();
        $ins = $alumno->inscripciones()->first(); $ruta = $ins->documento_path;
        $ins->update(['estado' => 'rechazada', 'motivo_rechazo' => 'Prueba']);
        $antes = $ins->fresh()->getRawOriginal();
        DocumentoInscripcion::creating(function ($d) { if ($d->version === 2) throw new \RuntimeException('fallo metadatos simulado'); });
        $this->withoutExceptionHandling();
        try {
            $this->enviar($user, $curso, 'segunda'); $this->fail('Debió fallar');
        } catch (\RuntimeException $e) { $this->assertSame('fallo metadatos simulado', $e->getMessage()); }
        finally { DocumentoInscripcion::flushEventListeners(); }
        $this->assertSame($antes, $ins->fresh()->getRawOriginal());
        $this->assertSame(1, $ins->documentos()->count());
        $this->assertTrue($ins->documentoVigente()->exists());
        $this->assertSame([$ruta], Storage::disk('inscripciones')->allFiles());
    }

    public function test_no_compensa_un_archivo_que_ya_esta_registrado(): void
    {
        [$u, $a, $c] = $this->contexto(); $this->enviar($u, $c)->assertSessionHasNoErrors();
        $ruta = $a->inscripciones()->first()->documento_path;
        app(DocumentosInscripcion::class)->descartarNuevo($ruta);
        Storage::disk('inscripciones')->assertExists($ruta);
    }

    public function test_planificador_reconoce_versiones_historicas_como_asociadas(): void
    {
        [$u, $a, $c] = $this->contexto(); $this->enviar($u, $c)->assertSessionHasNoErrors();
        $ins = $a->inscripciones()->first(); $ins->update(['estado' => 'rechazada']);
        $this->enviar($u, $c, 'segunda')->assertSessionHasNoErrors();
        $this->artisan('inscripciones:planificar-documentos')
            ->expectsOutputToContain('"sin_referencia":0,"versiones_registradas":2')->assertSuccessful();
    }

    public function test_segunda_peticion_sobre_estado_ya_pendiente_no_duplica_versiones(): void
    {
        [$u, $a, $c] = $this->contexto();
        $this->enviar($u, $c)->assertSessionHasNoErrors();
        $ins = $a->inscripciones()->first(); $ins->update(['estado' => 'rechazada']);
        $queries = []; DB::listen(function ($q) use (&$queries) { $queries[] = strtolower($q->sql); });
        $this->enviar($u, $c, 'segunda')->assertSessionHasNoErrors();
        $this->enviar($u, $c, 'tercera')->assertSessionHas('error');
        $this->assertSame(2, $ins->documentos()->count());
        $this->assertCount(2, Storage::disk('inscripciones')->allFiles());
        $this->assertTrue((bool) array_filter($queries, fn ($q) => str_contains($q, 'select max(') && str_contains($q, 'documentos_inscripcion')));
    }

    public function test_dos_transacciones_sqlite_en_memoria_no_publican_dos_vigentes(): void
    {
        // Contención real entre dos conexiones a la MISMA base exclusivamente en RAM.
        // Copiar el DDL efectivo de la migración, no una tabla sin restricciones.
        $dsn = 'sqlite:file:itm-versiones-'.bin2hex(random_bytes(8)).'?mode=memory&cache=shared';
        $uno = new \PDO($dsn); $dos = new \PDO($dsn);
        foreach ([$uno, $dos] as $pdo) {
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON'); $pdo->exec('PRAGMA busy_timeout = 1');
            $this->assertSame('', $pdo->query('PRAGMA database_list')->fetch(\PDO::FETCH_ASSOC)['file']);
        }
        $uno->exec('CREATE TABLE inscripciones (id INTEGER PRIMARY KEY)');
        $uno->exec(DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'documentos_inscripcion'")->sql);
        foreach (DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'documentos_inscripcion' AND sql IS NOT NULL") as $index) $uno->exec($index->sql);
        $uno->exec('INSERT INTO inscripciones(id) VALUES (1)');
        $insertar = 'INSERT INTO documentos_inscripcion(inscripcion_id, version, ruta, disco, sha256, bytes, vigente) VALUES (1, ?, ?, \'inscripciones\', ?, 4, \'1\')';
        $uno->beginTransaction();
        $uno->prepare($insertar)->execute([1, 'solicitudes/primero.pdf', str_repeat('a', 64)]);
        $dos->beginTransaction();
        try {
            $dos->prepare($insertar)->execute([1, 'solicitudes/segundo.pdf', str_repeat('b', 64)]);
            $this->fail('La segunda escritura no debe adelantar la transacción abierta.');
        } catch (\PDOException $e) { $this->assertContains((int) $e->errorInfo[1], [5, 6]); }
        finally { $dos->rollBack(); }
        $uno->commit();
        $dos->beginTransaction();
        $siguiente = (int) $dos->query('SELECT MAX(version) FROM documentos_inscripcion WHERE inscripcion_id = 1')->fetchColumn() + 1;
        $dos->exec('UPDATE documentos_inscripcion SET vigente = NULL WHERE inscripcion_id = 1');
        $dos->prepare($insertar)->execute([$siguiente, 'solicitudes/segundo.pdf', str_repeat('b', 64)]);
        $dos->commit();
        $this->assertSame(2, (int) $uno->query('SELECT COUNT(*) FROM documentos_inscripcion')->fetchColumn());
        $this->assertSame(1, (int) $uno->query("SELECT COUNT(*) FROM documentos_inscripcion WHERE vigente = '1'")->fetchColumn());
        $this->assertSame(2, (int) $uno->query("SELECT version FROM documentos_inscripcion WHERE vigente = '1'")->fetchColumn());
    }

    public function test_restricciones_sql_impiden_dos_vigentes_y_numero_de_version_duplicado(): void
    {
        [$u, $a, $c] = $this->contexto(); $this->enviar($u, $c)->assertSessionHasNoErrors();
        $v = $a->inscripciones()->first()->documentoVigente()->first();
        foreach ([['version' => 2, 'vigente' => '1'], ['version' => 1, 'vigente' => null]] as $cambios) {
            try {
                DB::table('documentos_inscripcion')->insert(array_merge($v->getRawOriginal(), ['id' => $v->id + 100, 'ruta' => 'solicitudes/otro.pdf'], $cambios));
                $this->fail('UNIQUE debía rechazar el duplicado');
            } catch (QueryException $e) { $this->assertSame('23000', (string) $e->getCode()); }
        }
        $this->assertSame(1, DocumentoInscripcion::count());
    }

    public function test_descarga_versiones_autorizacion_y_no_exponer_rutas(): void
    {
        [$u, $a, $c] = $this->contexto(); $this->enviar($u, $c)->assertSessionHasNoErrors();
        $ins = $a->inscripciones()->first(); $v1 = $ins->documentoVigente()->first();
        $ins->update(['estado' => 'rechazada']); $this->enviar($u, $c, 'segunda')->assertSessionHasNoErrors();
        $this->actingAs($u)->get(route('alumno.inscripciones.documentos', $ins))->assertOk()
            ->assertJsonPath('total', 2)->assertJsonMissingPath('documentos.0.ruta')->assertJsonMissingPath('documentos.0.disco');
        foreach ([[$u, 'alumno.inscripciones'], [User::factory()->create(['role' => 'admin']), 'admin.solicitudes']] as [$actor, $prefijo]) {
            $r = $this->actingAs($actor)->get(route($prefijo.'.documentos.version', [$ins, $v1->id]))->assertOk()->assertDownload('solicitud-inscripcion.pdf');
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
            $this->assertSame(Storage::disk('inscripciones')->get($v1->ruta), file_get_contents($r->baseResponse->getFile()->getPathname()));
        }
        $otro = User::factory()->create(['role' => 'alumno']); Alumno::factory()->create(['user_id' => $otro->id]);
        $this->actingAs($otro)->get(route('alumno.inscripciones.documentos.version', [$ins, $v1->id]))->assertNotFound();
        $insOtro = Inscripcion::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.solicitudes.documentos.version', [$insOtro, $v1->id]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'docente']))->get(route('admin.solicitudes.documentos', $ins))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.solicitudes.documentos.version', [$ins, $v1->id]))->assertRedirect(route('login'));
    }

    public function test_archivo_faltante_manipulado_o_ruta_invalida_devuelve_404(): void
    {
        [$u, $a, $c] = $this->contexto(); $this->enviar($u, $c)->assertSessionHasNoErrors();
        $ins = $a->inscripciones()->first(); $v = $ins->documentoVigente()->first();
        $url = route('alumno.inscripciones.documentos.version', [$ins, $v->id]);
        Storage::disk('inscripciones')->put($v->ruta, 'bytes alterados ficticios');
        $this->get($url)->assertNotFound();
        Storage::disk('inscripciones')->delete($v->ruta);
        $this->get($url)->assertNotFound();
        $v->update(['ruta' => '../fuera.pdf']); $this->get($url)->assertNotFound();
        $this->get(route('alumno.inscripciones.documentos.version', [$ins, 999999]))->assertNotFound();
    }

    public function test_migracion_pendiente_bloquea_antes_de_guardar(): void
    {
        [$u, , $c] = $this->contexto(); Schema::drop('documentos_inscripcion');
        $this->enviar($u, $c)->assertSessionHasErrors('documento');
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
        $this->assertSame(0, Inscripcion::count());
    }

    public function test_conflicto_de_copias_no_sobrescribe_ni_crea_historial(): void
    {
        [, $a, $c] = $this->contexto();
        $ins = Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $c->id, 'documento_path' => 'solicitudes/legado.pdf']);
        config(['documentos_inscripcion.legacy_read_enabled' => true]);
        Storage::disk('public')->put($ins->documento_path, 'publico');
        Storage::disk('inscripciones')->put($ins->documento_path, 'privado distinto');
        try { app(HistorialDocumentosInscripcion::class)->incorporarReferenciaVerificada($ins); $this->fail('Debió rechazar colisión'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('documento', $e->errors()); }
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertSame('publico', Storage::disk('public')->get($ins->documento_path));
    }

    public static function edades(): array { return [[true], [false]]; }

    #[DataProvider('edades')]
    public function test_pdf_de_adulto_y_menor_registra_historial(bool $mayor): void
    {
        [$u, $a, $c] = $this->contexto(); if (!$mayor) $this->prepararEncargadoOficial($a);
        $im = imagecreatetruecolor(3, 3); ob_start(); imagepng($im); $firma = 'data:image/png;base64,'.base64_encode(ob_get_clean()); imagedestroy($im);
        $this->actingAs($u)->post(route('alumno.inscripcion.enviar'), ['curso_id' => $c->id, 'alumno_dui' => '12345678-9', 'firma_alumno' => $firma, 'firma_encargado' => $firma])->assertSessionHasNoErrors();
        $ins = $a->inscripciones()->firstOrFail(); $v = $ins->documentoVigente()->firstOrFail();
        $this->assertStringStartsWith('%PDF-', Storage::disk('inscripciones')->get($v->ruta));
        $this->assertSame(1, $v->version); $this->assertSame($ins->documento_path, $v->ruta);
    }

    public function test_fallo_archivo_no_genera_metadatos(): void
    {
        [$u, , $c] = $this->contexto();
        $root = Storage::disk('inscripciones')->path('');
        $fake = \Mockery::mock(); $fake->shouldReceive('path')->with('')->andReturn($root);
        $fake->shouldReceive('exists')->andReturn(false); $fake->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('inscripciones')->andReturn($fake);
        $this->enviar($u, $c)->assertSessionHas('error');
        $this->assertSame(0, DocumentoInscripcion::count()); $this->assertSame(0, Inscripcion::count());
    }
}
