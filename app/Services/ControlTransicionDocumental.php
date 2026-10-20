<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Barrera cooperativa local. No sustituye el inventario de procesos externos. */
class ControlTransicionDocumental
{
    private int $lectores = 0;
    private bool $exclusiva = false;

    public function __construct(private DocumentosInscripcion $archivos) {}

    public function directorio(): string
    {
        return config('documentos_inscripcion.transition_control_root') ?: storage_path('framework/transicion-documental');
    }

    public function bloqueado(): bool
    {
        $this->archivos->verificarRutaAbsoluta($this->directorio().'/bloqueado');
        return file_exists($this->directorio().'/bloqueado');
    }

    public function operacion(Closure $operacion): mixed
    {
        if (!config('documentos_inscripcion.transition_guard_enabled')) return $operacion();
        abort_if($this->exclusiva || $this->bloqueado(), 503, 'Inscripciones temporalmente en mantenimiento documental.');
        if ($this->lectores > 0) return $operacion();
        $lock = $this->abrirLock();
        try {
            abort_unless(flock($lock, LOCK_SH | LOCK_NB), 503, 'Mantenimiento documental en curso.');
            abort_if($this->bloqueado(), 503, 'Mantenimiento documental en curso.');
            $this->lectores++;
            try { return $operacion(); }
            finally { $this->lectores--; }
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    /** Canal de pruebas: un fallo de aislamiento nunca se convierte en permiso real. */
    public function exigirAislamiento(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('EJECUCION_REAL_NO_HABILITADA');
        }
        foreach (['public', 'inscripciones'] as $disco) {
            if (config("filesystems.disks.$disco.driver") !== 'local') throw new RuntimeException('DISCO_NO_LOCAL');
            $this->exigirTemporal((string) config("filesystems.disks.$disco.root"));
            if (rtrim(str_replace('\\', '/', Storage::disk($disco)->path('')), '/')
                !== rtrim(str_replace('\\', '/', config("filesystems.disks.$disco.root")), '/')) {
                throw new RuntimeException('DISCO_Y_CONFIGURACION_NO_COINCIDEN');
            }
        }
        $this->exigirTemporal(storage_path());
        $this->exigirTemporal($this->directorio());
        if (DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite'
            || DB::select('PRAGMA database_list')[0]->file !== '') {
            throw new RuntimeException('AISLAMIENTO_NO_VERIFICADO');
        }
    }

    public function exigirTemporal(string $ruta): void
    {
        $this->archivos->verificarRutaAbsoluta(rtrim($ruta, '/\\'));
        if (!$this->dentro($ruta, sys_get_temp_dir()) || $this->dentro($ruta, base_path())) {
            throw new RuntimeException('ALMACENAMIENTO_NO_AISLADO');
        }
    }

    public function dentro(string $ruta, string $raiz): bool
    {
        $normal = function ($p) {
            $p = rtrim(str_replace('\\', '/', $p), '/');
            return PHP_OS_FAMILY === 'Windows' ? strtolower($p) : $p;
        };
        return str_starts_with($normal($ruta).'/', $normal($raiz).'/');
    }

    public function exclusiva(Closure $accion): mixed
    {
        $this->exigirAislamiento();
        return $this->bajoExclusiva($accion);
    }

    /** Canal real separado: nunca convierte un fallo de aislamiento en permiso de ejecución. */
    public function exclusivaReal(Closure $accion): mixed
    {
        app(AutorizacionTransicionDocumental::class)->exigirValidada();
        app(EntornoTransicionReal::class)->verificarConexion();
        return $this->bajoExclusiva(function () use ($accion) {
            app(EntornoTransicionReal::class)->verificarEscritores();
            return $accion();
        });
    }

    private function bajoExclusiva(Closure $accion): mixed
    {
        if (!config('documentos_inscripcion.transition_guard_enabled') || $this->lectores || $this->exclusiva) {
            throw new RuntimeException('BARRERA_NO_DISPONIBLE');
        }
        $lock = $this->abrirLock();
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('TRANSICION_CONCURRENTE');
            $this->exclusiva = true;
            try { return $accion(); }
            finally { $this->exclusiva = false; }
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    public function congelar(): void
    {
        $this->exigirExclusiva();
        $ruta = $this->directorio().'/bloqueado';
        $this->archivos->verificarRutaAbsoluta($ruta);
        if (!file_exists($ruta)) {
            $f = fopen($ruta, 'xb');
            if (!$f) throw new RuntimeException('NO_SE_PUDO_CONGELAR');
            try { if (fwrite($f, "bloqueado\n") !== 10) throw new RuntimeException('NO_SE_PUDO_CONGELAR'); }
            finally { fclose($f); }
        }
    }

    public function retirar(): void
    {
        $this->exigirExclusiva();
        if (\Illuminate\Support\Facades\Schema::hasTable('documentos_inscripcion')
            && \App\Models\DocumentoInscripcion::exists()
            && (!config('documentos_inscripcion.enabled') || !config('documentos_inscripcion.history_enabled'))) {
            throw new RuntimeException('FLUJO_PRIVADO_NO_PREPARADO');
        }
        if ($this->bloqueado() && !unlink($this->directorio().'/bloqueado')) throw new RuntimeException('NO_SE_PUDO_RETIRAR_BLOQUEO');
    }

    public function exigirExclusiva(): void
    {
        if (!$this->exclusiva) throw new RuntimeException('REQUIERE_TRANSICION_EXCLUSIVA');
    }

    private function abrirLock()
    {
        $dir = $this->directorio();
        $this->archivos->verificarRutaAbsoluta($dir.'/transicion.lock');
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('BARRERA_NO_DISPONIBLE');
        $lock = fopen($dir.'/transicion.lock', 'c+b');
        if (!$lock) throw new RuntimeException('BARRERA_NO_DISPONIBLE');
        return $lock;
    }
}
