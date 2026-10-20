<?php

namespace App\Console\Commands;

use App\Models\Inscripcion;
use App\Services\DocumentosInscripcion;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;

class PlanificarDocumentosInscripcion extends Command
{
    protected $signature = 'inscripciones:planificar-documentos {--manifest : Crear un manifiesto privado nuevo; nunca mover documentos}';
    protected $description = 'Inventario documental de solo lectura, sin cambios en base de datos ni PDF';

    public function handle(DocumentosInscripcion $documentos): int
    {
        $referencias = Inscripcion::whereNotNull('documento_path')->where('documento_path', '!=', '')
            ->get(['id', 'documento_path'])->groupBy('documento_path');
        $versiones = \Illuminate\Support\Facades\Schema::hasTable('documentos_inscripcion')
            ? \App\Models\DocumentoInscripcion::all() : collect();
        $archivos = []; $porRuta = []; $invalidas = [];
        foreach ($referencias as $ruta => $filas) {
            if (!$documentos->validarRuta($ruta)) $invalidas[] = ['ruta' => $ruta, 'inscripciones' => $filas->pluck('id')->all()];
        }
        foreach (['public', 'inscripciones'] as $disco) {
            try { $documentos->rutaSegura($disco, 'solicitudes/comprobacion.pdf'); }
            catch (RuntimeException $e) { $this->error('Raíz insegura: inventario detenido.'); return self::FAILURE; }
            $raiz = rtrim(\Illuminate\Support\Facades\Storage::disk($disco)->path(''), '/\\');
            if (!is_dir($raiz)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $archivo) {
                $ruta = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
                if (!$archivo->isLink() && (!$archivo->isFile() || strtolower($archivo->getExtension()) !== 'pdf')) continue;
                $entrada = ['disco' => $disco, 'ruta' => $ruta, 'inscripciones' => $referencias->get($ruta, collect())->pluck('id')->all()];
                $asociadas = $versiones->where('disco', $disco)->where('ruta', $ruta);
                $entrada['versiones'] = $asociadas->map(fn ($v) => ['id' => $v->id, 'inscripcion_id' => $v->inscripcion_id, 'version' => $v->version])->values()->all();
                try {
                    $segura = $documentos->rutaSegura($disco, $ruta);
                    $hash = hash_file('sha256', $segura);
                    if ($hash === false) throw new RuntimeException('No legible');
                    $entrada += ['bytes' => filesize($segura), 'sha256' => $hash];
                    if ($asociadas->contains(fn ($v) => $v->sha256 !== $hash || $v->bytes !== $entrada['bytes'])) {
                        $entrada['error'] = 'integridad_historial_no_coincide';
                    }
                    $porRuta[$ruta][$disco] = $hash;
                } catch (RuntimeException $e) { $entrada['error'] = 'ruta_insegura_o_no_legible'; }
                $archivos[] = $entrada;
            }
        }
        $colisiones = array_keys(array_filter($porRuta, fn ($hashes) => count(array_unique($hashes)) > 1));
        $faltantes = $referencias->keys()->filter(fn ($ruta) => !isset($porRuta[$ruta]))->values()->all();
        $resumen = ['archivos' => count($archivos), 'con_referencia' => count(array_filter($archivos, fn ($a) => count($a['inscripciones']) > 0 || count($a['versiones']) > 0)),
            'sin_referencia' => count(array_filter($archivos, fn ($a) => count($a['inscripciones']) === 0 && count($a['versiones']) === 0)),
            'versiones_registradas' => $versiones->count(),
            'versiones_no_localizadas' => $versiones->filter(fn ($v) => !isset($porRuta[$v->ruta][$v->disco]))->count(),
            'referencias_invalidas' => count($invalidas), 'referencias_no_localizadas' => count($faltantes), 'colisiones' => count($colisiones),
            'copias_en_ambos_discos' => count(array_filter($porRuta, fn ($hashes) => count($hashes) > 1)),
            'archivos_inseguros' => count(array_filter($archivos, fn ($a) => isset($a['error'])))];
        if ($this->option('manifest')) {
            $directorio = \Illuminate\Support\Facades\Storage::disk('inscripciones')->path('manifiestos');
            // Verificar TODOS los ancestros antes de escribir el manifiesto.
            for ($p = $directorio; ; $p = dirname($p)) {
                if (is_link($p) || (file_exists($p) && strcasecmp(str_replace('\\', '/', realpath($p)), str_replace('\\', '/', $p)) !== 0)) {
                    $this->error('Directorio de manifiesto inseguro.'); return self::FAILURE;
                }
                if (dirname($p) === $p) break;
            }
            if (!is_dir($directorio) && !mkdir($directorio, 0700, true)) return self::FAILURE;
            $ruta = $directorio.'/inventario-'.gmdate('Ymd-His').'-'.\Illuminate\Support\Str::uuid().'.json';
            $json = json_encode(['generado_en' => now()->toIso8601String(), 'resumen' => $resumen,
                'archivos' => $archivos, 'referencias_invalidas' => $invalidas, 'referencias_no_localizadas' => $faltantes, 'colisiones' => $colisiones], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $fh = fopen($ruta, 'xb');
            if (!$fh) return self::FAILURE;
            try { $escritos = fwrite($fh, $json); } finally { fclose($fh); }
            if ($escritos !== strlen($json) || hash_file('sha256', $ruta) !== hash('sha256', $json)) return self::FAILURE;
            $this->info('Manifiesto privado creado: '.basename($ruta));
        }
        $this->line(json_encode($resumen, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
