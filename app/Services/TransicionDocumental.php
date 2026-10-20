<?php

namespace App\Services;

use App\Models\{DocumentoInscripcion, Inscripcion};
use Illuminate\Support\Facades\{DB, Schema};
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class TransicionDocumental
{
    public function __construct(
        private DocumentosInscripcion $archivos,
        private ControlTransicionDocumental $control,
        private HistorialDocumentosInscripcion $historial,
    ) {}

    /** Inventario determinista en memoria: no resuelve discos ni crea sus raíces. */
    public function planificar(): array
    {
        $this->estructura();
        $publico = $this->raiz('public');
        $privado = $this->raiz('inscripciones');
        if ($this->control->dentro($privado, $publico) || $this->control->dentro($publico, $privado)
            || $this->control->dentro($privado, public_path())) throw new RuntimeException('DESTINO_PUBLICO');
        $referencias = Inscripcion::orderBy('id')->get(['id', 'documento_path'])->map(fn ($i) => [
            'id' => $i->id, 'ruta' => $i->documento_path ?: null,
        ])->all();
        $asociaciones = [];
        foreach ($referencias as $r) {
            if ($r['ruta'] === null) continue;
            if (!$this->archivos->validarRuta($r['ruta'])) throw new RuntimeException('REFERENCIA_INSEGURA');
            if (isset($asociaciones[$r['ruta']])) throw new RuntimeException('REFERENCIA_COMPARTIDA');
            $asociaciones[$r['ruta']] = $r['id'];
        }
        $files = [];
        if (is_dir($publico)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publico, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $f) {
                if ($f->isLink()) throw new RuntimeException('ENLACE_NO_PERMITIDO');
                $this->archivos->verificarRutaAbsoluta($f->getPathname());
                if (!$f->isFile() || strtolower($f->getExtension()) !== 'pdf') continue;
                $ruta = str_replace('\\', '/', substr($f->getPathname(), strlen($publico) + 1));
                if (!$this->archivos->validarRuta($ruta)) throw new RuntimeException('ARCHIVO_FUERA_DE_SOLICITUDES');
                $meta = $this->metadatos($f->getPathname());
                $files[$ruta] = ['id' => hash('sha256', "public\0".$ruta), 'ruta' => $ruta,
                    'inscripcion_id' => $asociaciones[$ruta] ?? null] + $meta;
                $destino = $privado.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $ruta);
                $this->archivos->verificarRutaAbsoluta($destino);
                if (file_exists($destino) && $this->metadatos($destino) !== $meta) throw new RuntimeException('DESTINO_DIFERENTE');
            }
        }
        foreach ($asociaciones as $ruta => $id) if (!isset($files[$ruta])) throw new RuntimeException('ORIGINAL_FALTANTE');
        ksort($files, SORT_STRING);
        foreach (DocumentoInscripcion::all() as $v) {
            $f = $files[$v->ruta] ?? null;
            if (!$f || $v->inscripcion_id !== $f['inscripcion_id'] || $v->version !== 1 || !$v->vigente
                || $v->disco !== 'inscripciones' || $v->sha256 !== $f['sha256'] || $v->bytes !== $f['bytes']) {
                throw new RuntimeException('HISTORIAL_INCOMPATIBLE');
            }
        }
        return ['formato' => 1, 'referencias' => $referencias, 'archivos' => array_values($files)];
    }

    public function resumen(array $plan): array
    {
        $asociados = count(array_filter($plan['archivos'], fn ($f) => $f['inscripcion_id'] !== null));
        return ['archivos' => count($plan['archivos']), 'asociados' => $asociados,
            'cuarentena' => count($plan['archivos']) - $asociados,
            'bytes' => array_sum(array_column($plan['archivos'], 'bytes')),
            'contenidos_repetidos' => count($plan['archivos']) - count(array_unique(array_column($plan['archivos'], 'sha256')))];
    }

    public function ejecutar(string $inventario, string $sha256, string $cuarentena, string $lote): array
    {
        // Este cierre se evalúa ANTES de resolver rutas o abrir conexiones de ejecución.
        $this->control->exigirAislamiento();
        foreach (['enabled', 'history_enabled', 'legacy_read_enabled'] as $opcion) {
            if (config('documentos_inscripcion.'.$opcion)) throw new RuntimeException('OPCIONES_INCOMPATIBLES');
        }
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}\z/D', $lote)) throw new RuntimeException('LOTE_INVALIDO');
        $this->control->exigirTemporal($inventario);
        $this->control->exigirTemporal($cuarentena);
        foreach (['public', 'inscripciones'] as $disco) {
            $raiz = $this->raiz($disco);
            if ($this->control->dentro($cuarentena, $raiz) || $this->control->dentro($raiz, $cuarentena)
                || $this->control->dentro($inventario, $raiz)) throw new RuntimeException('CUARENTENA_O_INVENTARIO_INSEGURO');
        }
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $sha256) || !is_file($inventario)) throw new RuntimeException('INVENTARIO_NO_AUTORIZADO');
        $json = file_get_contents($inventario);
        if ($json === false || hash('sha256', $json) !== $sha256) throw new RuntimeException('INVENTARIO_NO_AUTORIZADO');
        $autorizado = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $this->control->exclusiva(fn () => $this->procesar($autorizado, $sha256, $cuarentena, $lote));
    }

    public function ejecutarReal(string $inventario, string $sha256, string $cuarentena, string $lote,
        string $autorizacion, string $autorizacionSha, string $claveSha): array
    {
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}\z/D', $lote)) throw new RuntimeException('LOTE_INVALIDO');
        $entorno = app(EntornoTransicionReal::class);
        $contexto = $entorno->preparar($cuarentena, $claveSha);
        $permiso = app(AutorizacionTransicionDocumental::class);
        $solicitud = ['inventario' => $inventario, 'sha256' => $sha256, 'cuarentena' => $cuarentena, 'lote' => $lote];
        $p = $permiso->validar($autorizacion, $autorizacionSha, $entorno->autoridad().'/clave-publica.txt',
            $contexto, $solicitud, app(RutasAutorizacionDocumental::class)->raiz('consumos'), $claveSha);
        foreach ([$autorizacion, $inventario, $p['respaldo']['ruta'], $p['auditoria']['ruta']] as $archivo) {
            $entorno->verificarAcl($archivo);
        }
        $json = file_get_contents($inventario);
        if (hash('sha256', $json) !== $sha256) throw new RuntimeException('INVENTARIO_ALTERADO');
        $autorizado = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if ($this->planificar() !== $autorizado) throw new RuntimeException('INVENTARIO_ALTERADO');
        return $this->control->exclusivaReal(function () use ($entorno, $contexto, $cuarentena, $claveSha, $permiso, $autorizado, $sha256, $lote) {
            if ($entorno->preparar($cuarentena, $claveSha) !== $contexto) throw new RuntimeException('ENTORNO_MODIFICADO');
            return $permiso->usar(fn () => $this->procesar($autorizado, $sha256, $cuarentena, $lote,
                fn () => $permiso->exigirActiva()));
        });
    }

    /** Mismo motor de copia y transacción para ambos canales, sin rebajar sus precondiciones. */
    private function procesar(array $autorizado, string $sha256, string $cuarentena, string $lote, ?\Closure $comprobar = null): array
    {
        $comprobar?->__invoke();
        $this->control->congelar(); // Permanece hasta retirada explícita, incluso después de fallos.
        $plan = $this->planificar();
        if ($plan !== $autorizado) throw new RuntimeException('INVENTARIO_ALTERADO');
        $dir = rtrim($cuarentena, '/\\').DIRECTORY_SEPARATOR.$lote;
        $this->crearDirectorio($dir);
        $this->guardarNuevoOIdentico($dir.'/manifiesto.json', json_encode(
            ['inventario_sha256' => $sha256, 'plan' => $plan], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        foreach ($plan['archivos'] as $f) {
            $comprobar?->__invoke();
            $destino = $f['inscripcion_id'] !== null
                ? $this->raiz('inscripciones').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $f['ruta'])
                : $dir.DIRECTORY_SEPARATOR.$f['id'].'.pdf';
            $this->copiar($this->raiz('public').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $f['ruta']), $destino, $f);
        }
        // Nuevo inventario completo después de copiar: detecta archivos nuevos y referencias cambiadas.
        if ($this->planificar() !== $plan) throw new RuntimeException('ORIGEN_MODIFICADO');
        DB::transaction(function () use ($plan, $comprobar) {
            $comprobar?->__invoke();
            $refs = Inscripcion::orderBy('id')->lockForUpdate()->get(['id', 'documento_path'])
                ->map(fn ($i) => ['id' => $i->id, 'ruta' => $i->documento_path ?: null])->all();
            if ($refs !== $plan['referencias']) throw new RuntimeException('REFERENCIAS_MODIFICADAS');
            foreach ($plan['archivos'] as $f) {
                if ($f['inscripcion_id'] !== null) {
                    $this->historial->incorporarInicialPrivada(Inscripcion::findOrFail($f['inscripcion_id']), $f);
                }
            }
            if ($this->planificar() !== $plan) throw new RuntimeException('ORIGEN_MODIFICADO');
            $comprobar?->__invoke();
        });
        $resumen = $this->resumen($plan);
        $this->guardarNuevoOIdentico($dir.'/completado.json', json_encode(
            ['inventario_sha256' => $sha256, 'resumen' => $resumen], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $resumen;
    }

    private function estructura(): void
    {
        if (!Schema::hasTable('documentos_inscripcion') || !Schema::hasColumns('documentos_inscripcion', [
            'id', 'inscripcion_id', 'version', 'ruta', 'disco', 'sha256', 'bytes', 'vigente', 'registrado_at',
        ])) throw new RuntimeException('ESTRUCTURA_DOCUMENTAL_NO_DISPONIBLE');
        $indices = Schema::getIndexes('documentos_inscripcion');
        foreach ([['inscripcion_id', 'version'], ['inscripcion_id', 'vigente'], ['disco', 'ruta']] as $columnas) {
            if (!collect($indices)->contains(fn ($i) => $i['unique'] && $i['columns'] === $columnas)) {
                throw new RuntimeException('RESTRICCIONES_DOCUMENTALES_NO_DISPONIBLES');
            }
        }
        if (!collect(Schema::getForeignKeys('documentos_inscripcion'))->contains(fn ($f) =>
            $f['columns'] === ['inscripcion_id'] && $f['foreign_table'] === 'inscripciones' && $f['foreign_columns'] === ['id']
            && strtolower($f['on_delete']) === 'restrict')) throw new RuntimeException('CLAVE_DOCUMENTAL_NO_DISPONIBLE');
    }

    private function raiz(string $disco): string
    {
        if (config("filesystems.disks.$disco.driver") !== 'local') throw new RuntimeException('DISCO_NO_LOCAL');
        $raiz = rtrim((string) config("filesystems.disks.$disco.root"), '/\\');
        $this->archivos->verificarRutaAbsoluta($raiz);
        return $raiz;
    }

    private function metadatos(string $ruta): array
    {
        $this->archivos->verificarRutaAbsoluta($ruta);
        clearstatcache(true, $ruta);
        if (!is_file($ruta) || !is_readable($ruta)) throw new RuntimeException('ARCHIVO_NO_LEGIBLE');
        $bytes = filesize($ruta); $hash = hash_file('sha256', $ruta);
        if ($bytes === false || $bytes < 1 || $hash === false) throw new RuntimeException('ARCHIVO_INCOMPLETO');
        return ['bytes' => $bytes, 'sha256' => $hash];
    }

    private function crearDirectorio(string $dir): void
    {
        $this->archivos->verificarRutaAbsoluta($dir);
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('DIRECTORIO_NO_DISPONIBLE');
        $this->archivos->verificarRutaAbsoluta($dir);
    }

    private function guardarNuevoOIdentico(string $ruta, string $bytes): void
    {
        if (file_exists($ruta)) {
            $this->archivos->verificarRutaAbsoluta($ruta);
            if (file_get_contents($ruta) !== $bytes) throw new RuntimeException('MANIFIESTO_CONTRADICTORIO');
            return;
        }
        $this->copiarBytesExclusivos($ruta, $bytes);
    }

    private function copiarBytesExclusivos(string $ruta, string $bytes): void
    {
        $this->archivos->verificarRutaAbsoluta($ruta);
        $temporal = $ruta.'.part-'.bin2hex(random_bytes(12));
        $this->archivos->verificarRutaAbsoluta($temporal);
        $f = @fopen($temporal, 'xb');
        if (!$f) throw new RuntimeException('MANIFIESTO_NO_DISPONIBLE');
        try {
            if (fwrite($f, $bytes) !== strlen($bytes) || !fflush($f)) throw new RuntimeException('MANIFIESTO_INCOMPLETO');
            if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('MANIFIESTO_INCOMPLETO');
        }
        finally { fclose($f); }
        $this->archivos->verificarRutaAbsoluta($ruta);
        if (!@link($temporal, $ruta) && (!is_file($ruta) || file_get_contents($ruta) !== $bytes)) {
            throw new RuntimeException('MANIFIESTO_NO_DISPONIBLE');
        }
        if (file_get_contents($ruta) !== $bytes) throw new RuntimeException('MANIFIESTO_INCOMPLETO');
        if (!unlink($temporal)) throw new RuntimeException('TEMPORAL_CONSERVADO');
    }

    private function copiar(string $origen, string $destino, array $esperado): void
    {
        $meta = ['bytes' => $esperado['bytes'], 'sha256' => $esperado['sha256']];
        if ($this->metadatos($origen) !== $meta) throw new RuntimeException('ORIGINAL_ALTERADO');
        $this->archivos->verificarRutaAbsoluta($destino);
        if (file_exists($destino)) {
            if ($this->metadatos($destino) !== $meta) throw new RuntimeException('DESTINO_DIFERENTE');
            return;
        }
        $this->crearDirectorio(dirname($destino));
        $temporal = $destino.'.part-'.bin2hex(random_bytes(12));
        $this->archivos->verificarRutaAbsoluta($temporal);
        $entrada = @fopen($origen, 'rb');
        if (!$entrada) throw new RuntimeException('ORIGINAL_NO_DISPONIBLE');
        try {
            $salida = @fopen($temporal, 'xb');
            if (!$salida) throw new RuntimeException('COPIA_NO_DISPONIBLE');
            try {
                if ($this->transferir($entrada, $salida) !== $meta['bytes'] || !fflush($salida)) throw new RuntimeException('COPIA_INCOMPLETA');
                if (function_exists('fsync') && !fsync($salida)) throw new RuntimeException('COPIA_NO_SINCRONIZADA');
            } finally { fclose($salida); }
        } finally { fclose($entrada); }
        if ($this->metadatos($temporal) !== $meta || $this->metadatos($origen) !== $meta) throw new RuntimeException('INTEGRIDAD_NO_COINCIDE');
        $this->archivos->verificarRutaAbsoluta($destino);
        // Un enlace duro publica de forma exclusiva: nunca reemplaza un destino ocupado.
        if (!@link($temporal, $destino)) {
            if (!is_file($destino) || $this->metadatos($destino) !== $meta) throw new RuntimeException('FINALIZACION_NO_DISPONIBLE');
        }
        if ($this->metadatos($destino) !== $meta) throw new RuntimeException('COPIA_FINAL_ALTERADA');
        if (!unlink($temporal)) throw new RuntimeException('TEMPORAL_CONSERVADO'); // Solo el .part creado aquí.
    }

    /** Punto de inyección para probar fallos reales de escritura sin tocar los originales. */
    protected function transferir($entrada, $salida): int|false
    {
        return stream_copy_to_stream($entrada, $salida);
    }
}
