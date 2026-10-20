<?php

namespace App\Services;

use App\Models\{DocumentoInscripcion, Inscripcion};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HistorialDocumentosInscripcion
{
    public function __construct(private DocumentosInscripcion $archivos) {}

    /** Invocado dentro de la transacción alumno -> curso -> inscripción del envío. */
    public function registrar(Inscripcion $inscripcion, string $rutaNueva): DocumentoInscripcion
    {
        return app(ControlTransicionDocumental::class)->operacion(fn () => $this->registrarSinBloqueo($inscripcion, $rutaNueva));
    }

    private function registrarSinBloqueo(Inscripcion $inscripcion, string $rutaNueva): DocumentoInscripcion
    {
        $this->archivos->exigirHistorialDisponible();
        return DB::transaction(function () use ($inscripcion, $rutaNueva) {
            $inscripcion = Inscripcion::lockForUpdate()->findOrFail($inscripcion->id);
            $anterior = $inscripcion->documento_path;
            if ($anterior && $anterior !== $rutaNueva) {
                $this->incorporarBloqueada($inscripcion);
            }
            $meta = $this->leer('inscripciones', $rutaNueva);
            // Una presentación idéntica reutiliza los bytes y su versión: no duplica archivos.
            $existente = $inscripcion->documentos()->where('sha256', $meta['sha256'])->first();
            if ($existente) {
                $comprobacion = $this->leer($existente->disco, $existente->ruta);
                $this->comparar($existente, $comprobacion);
                $inscripcion->documentos()->where('vigente', '1')->update(['vigente' => null]);
                $existente->update(['vigente' => '1']);
                $documento = $existente;
            } else {
                $inscripcion->documentos()->where('vigente', '1')->update(['vigente' => null]);
                $documento = $this->crear($inscripcion, $rutaNueva, 'inscripciones', $meta);
            }
            $inscripcion->update(['documento_path' => $documento->ruta]);
            return $documento;
        });
    }

    /** Preparación idempotente: solo una referencia existente y verificable, nunca archivos sueltos. */
    public function incorporarReferenciaVerificada(Inscripcion $inscripcion): DocumentoInscripcion
    {
        $this->archivos->exigirHistorialDisponible();
        return app(ControlTransicionDocumental::class)->operacion(fn () => DB::transaction(
            fn () => $this->incorporarBloqueada(Inscripcion::lockForUpdate()->findOrFail($inscripcion->id))));
    }

    /** Solo el proceso de transición bajo barrera exclusiva; nunca cambia documento_path. */
    public function incorporarInicialPrivada(Inscripcion $inscripcion, array $esperado): DocumentoInscripcion
    {
        app(ControlTransicionDocumental::class)->exigirExclusiva();
        return DB::transaction(function () use ($inscripcion, $esperado) {
            $inscripcion = Inscripcion::lockForUpdate()->findOrFail($inscripcion->id);
            if ($inscripcion->documento_path !== $esperado['ruta']) $this->fallar('La referencia documental cambió.');
            $meta = $this->leer('inscripciones', $esperado['ruta']);
            if ($meta['sha256'] !== $esperado['sha256'] || $meta['bytes'] !== $esperado['bytes']) {
                $this->fallar('La copia privada no coincide con el inventario autorizado.');
            }
            $versiones = $inscripcion->documentos()->get();
            if ($versiones->isNotEmpty()) {
                $v = $versiones->first();
                if ($versiones->count() !== 1 || $v->version !== 1 || !$v->vigente
                    || $v->disco !== 'inscripciones' || $v->ruta !== $esperado['ruta']) {
                    $this->fallar('Historial incompatible con la incorporación inicial.');
                }
                $this->comparar($v, $meta);
                return $v;
            }
            return $this->crear($inscripcion, $esperado['ruta'], 'inscripciones', $meta);
        });
    }

    private function incorporarBloqueada(Inscripcion $inscripcion): DocumentoInscripcion
    {
        $ruta = $inscripcion->documento_path;
        if (!$ruta) $this->fallar('La inscripción no tiene una referencia documental verificable.');
        $candidatos = [];
        foreach (config('documentos_inscripcion.legacy_read_enabled') ? ['inscripciones', 'public'] : ['inscripciones'] as $disco) {
            if ($this->archivos->localizarEnDisco($disco, $ruta)) $candidatos[$disco] = $this->leer($disco, $ruta);
        }
        if (!$candidatos) $this->fallar('El documento anterior no está disponible; no se reemplazó.');
        if (count(array_unique(array_column($candidatos, 'sha256'))) > 1) $this->fallar('Hay una colisión entre copias del documento anterior.');
        $existente = $inscripcion->documentos()->where('ruta', $ruta)->first();
        if ($existente) {
            if (!isset($candidatos[$existente->disco])) $this->fallar('El disco histórico requiere una transición autorizada.');
            $this->comparar($existente, $candidatos[$existente->disco]);
            if (!$existente->vigente) $this->fallar('La referencia vigente y el historial documental no coinciden.');
            return $existente;
        }
        if ($inscripcion->documentos()->exists()) $this->fallar('El historial y la referencia vigente requieren revisión.');
        $disco = array_key_first($candidatos);
        return $this->crear($inscripcion, $ruta, $disco, $candidatos[$disco]);
    }

    private function crear(Inscripcion $inscripcion, string $ruta, string $disco, array $meta): DocumentoInscripcion
    {
        if (DocumentoInscripcion::where('disco', $disco)->where('ruta', $ruta)->exists()) {
            $this->fallar('El archivo ya está asociado; no se reasignó a otra inscripción.');
        }
        return DocumentoInscripcion::create($meta + [
            'inscripcion_id' => $inscripcion->id, 'version' => (int) $inscripcion->documentos()->max('version') + 1,
            'ruta' => $ruta, 'disco' => $disco, 'vigente' => '1',
        ]);
    }

    private function leer(string $disco, string $ruta): array
    {
        try { $archivo = $this->archivos->localizarEnDisco($disco, $ruta); }
        catch (RuntimeException $e) { $this->fallar('Ruta documental no autorizada.'); }
        if (!$archivo) $this->fallar('El documento no está disponible.');
        $hash = hash_file('sha256', $archivo); $bytes = filesize($archivo);
        if ($hash === false || $bytes === false) $this->fallar('No se pudo verificar el documento.');
        return ['sha256' => $hash, 'bytes' => $bytes];
    }

    private function comparar(DocumentoInscripcion $documento, array $meta): void
    {
        if ($documento->sha256 !== $meta['sha256'] || $documento->bytes !== $meta['bytes']) {
            $this->fallar('La integridad del documento histórico no coincide.');
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['documento' => $mensaje]);
    }
}
