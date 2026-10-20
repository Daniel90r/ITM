<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentosInscripcion
{
    public function exigirDocumentoInicial(\App\Models\Alumno $alumno, int $cursoId): void
    {
        if ($this->historialActivo()) {
            $this->exigirHistorialDisponible();
            return;
        }
        if ($this->habilitado() && $alumno->inscripciones()->where('curso_id', $cursoId)
            ->whereNotNull('documento_path')->where('documento_path', '!=', '')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'documento' => 'La sustitución de documentos privados requiere habilitar primero la conservación de versiones. Contacta a Administración.',
            ]);
        }
    }

    public function habilitado(): bool
    {
        return (bool) config('documentos_inscripcion.enabled');
    }

    public function historialActivo(): bool
    {
        return $this->habilitado() && (bool) config('documentos_inscripcion.history_enabled');
    }

    public function exigirHistorialDisponible(): void
    {
        if (!$this->historialActivo() || !\Illuminate\Support\Facades\Schema::hasTable('documentos_inscripcion')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'documento' => 'El historial documental no está habilitado o falta aplicar su migración. Contacta a Administración.',
            ]);
        }
    }

    public function localizarEnDisco(string $disco, string $ruta): ?string
    {
        if ($disco === 'public' && !config('documentos_inscripcion.legacy_read_enabled')) return null;
        $archivo = $this->rutaSegura($disco, $ruta);
        return is_file($archivo) && is_readable($archivo) ? $archivo : null;
    }

    public function validarRuta(string $ruta): bool
    {
        return !str_contains($ruta, '..') && (bool) preg_match(
            '~\Asolicitudes/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]*\.pdf\z~D', $ruta
        );
    }

    public function rutaSegura(string $disco, string $ruta): string
    {
        if (!in_array($disco, ['inscripciones', 'public'], true) || !$this->validarRuta($ruta)
            || config("filesystems.disks.$disco.driver") !== 'local') {
            throw new RuntimeException('Ruta documental no autorizada.');
        }
        $raiz = rtrim(Storage::disk($disco)->path(''), '/\\');
        if ($raiz === '' || !preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $raiz)) {
            throw new RuntimeException('Raíz documental no válida.');
        }
        $destino = $raiz.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $ruta);
        $this->verificarRutaAbsoluta($destino);
        return $destino;
    }

    /** No crea directorios; rechaza enlaces y ancestros redirigidos, también en Windows. */
    public function verificarRutaAbsoluta(string $destino): void
    {
        $normalizada = str_replace('\\', '/', $destino);
        if (!preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $destino)
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $normalizada)
            || str_contains($destino, "\0")
            || str_contains($destino, ':') && !preg_match('~^[A-Za-z]:[/\\\\][^:]*$~', $destino)) {
            throw new RuntimeException('Ruta documental no autorizada.');
        }
        for ($actual = $destino; ; $actual = dirname($actual)) {
            if (is_link($actual)) throw new RuntimeException('Enlace documental no permitido.');
            if (file_exists($actual)) {
                $real = realpath($actual);
                $normalizar = fn ($p) => PHP_OS_FAMILY === 'Windows'
                    ? strtolower(str_replace('\\', '/', $p)) : $p;
                if ($real === false || $normalizar($real) !== $normalizar($actual)) {
                    throw new RuntimeException('Resolución documental no permitida.');
                }
            }
            if (dirname($actual) === $actual) break;
        }
    }

    public function localizar(string $ruta): ?string
    {
        foreach (config('documentos_inscripcion.legacy_read_enabled') ? ['inscripciones', 'public'] : ['inscripciones'] as $disco) {
            $archivo = $this->rutaSegura($disco, $ruta);
            if (is_file($archivo) && is_readable($archivo)) return $archivo;
        }
        return null;
    }

    public function guardar(string $bytes): string
    {
        return app(ControlTransicionDocumental::class)->operacion(fn () => $this->guardarSinBloqueo($bytes));
    }

    private function guardarSinBloqueo(string $bytes): string
    {
        if (!$this->habilitado()) throw new RuntimeException('Almacenamiento privado desactivado.');
        $ruta = 'solicitudes/'.Str::uuid().'.pdf';
        $this->rutaSegura('inscripciones', $ruta);
        if (Storage::disk('inscripciones')->exists($ruta)) throw new RuntimeException('Colisión documental.');
        if (!Storage::disk('inscripciones')->put($ruta, $bytes)) throw new RuntimeException('No se pudo guardar el documento.');
        return $ruta;
    }

    public function guardarSubido(UploadedFile $archivo): string
    {
        $bytes = file_get_contents($archivo->getRealPath());
        if ($bytes === false) throw new RuntimeException('No se pudo leer el documento.');
        return $this->guardar($bytes);
    }

    /** Solo compensación de un archivo nuevo cuya operación falló. */
    public function descartarNuevo(string $ruta): void
    {
        app(ControlTransicionDocumental::class)->operacion(fn () => $this->descartarNuevoSinBloqueo($ruta));
    }

    private function descartarNuevoSinBloqueo(string $ruta): void
    {
        // Nunca aceptar rutas históricas arbitrarias como compensación.
        if (!preg_match('~\Asolicitudes/[0-9a-f-]{36}\.pdf\z~D', $ruta)) return;
        if (\Illuminate\Support\Facades\Schema::hasTable('documentos_inscripcion')
            && \App\Models\DocumentoInscripcion::where('disco', 'inscripciones')->where('ruta', $ruta)->exists()) return;
        $this->rutaSegura('inscripciones', $ruta);
        Storage::disk('inscripciones')->delete($ruta);
    }
}
