<?php

namespace App\Services;

use RuntimeException;

/** Solo inspecciona: no crea raíces, marcadores ni ACL. */
class RutasAutorizacionDocumental
{
    public function __construct(private DocumentosInscripcion $archivos, private IdentidadEjecutorDocumental $identidad) {}

    public function raiz(string $nombre): string
    {
        $valor = match ($nombre) {
            'privado' => config('filesystems.disks.inscripciones.root'),
            'barrera' => config('documentos_inscripcion.transition_control_root'),
            'autoridad' => config('documentos_inscripcion.authorization_root'),
            'permisos' => config('documentos_inscripcion.permits_root'),
            'consumos' => config('documentos_inscripcion.consumption_root'),
            'cuarentena' => config('documentos_inscripcion.quarantine_root'),
            default => null,
        };
        if (!is_string($valor) || $valor === '') throw new RuntimeException('RUTAS_REALES_NO_CONFIGURADAS');
        $this->absoluta($valor);
        return rtrim(str_replace('\\', '/', $valor), '/');
    }

    public function verificar(): array
    {
        $this->identidad->exigir((string) config('documentos_inscripcion.executor_sid'));
        $publicaciones = config('documentos_inscripcion.http_roots');
        if (!is_array($publicaciones) || !$publicaciones) throw new RuntimeException('PUBLICACIONES_HTTP_NO_CONFIGURADAS');
        $publicaciones = array_merge($publicaciones, [base_path(), dirname(base_path()), public_path(), config('filesystems.disks.public.root')]);
        foreach ($publicaciones as $web) {
            if (!is_string($web)) throw new RuntimeException('PUBLICACIONES_HTTP_NO_CONFIGURADAS');
            $this->absoluta($web);
        }
        $control = app(ControlTransicionDocumental::class);
        $raices = []; $pines = [];
        foreach (['autoridad', 'permisos', 'consumos', 'cuarentena', 'barrera', 'privado'] as $nombre) {
            $raiz = $this->raiz($nombre);
            if (!is_dir($raiz)) throw new RuntimeException('RAIZ_REAL_NO_PREPARADA');
            foreach ($publicaciones as $web) {
                if ($control->dentro($raiz, $web) || $control->dentro($web, $raiz)) throw new RuntimeException('DESTINO_PRIVADO_PUBLICABLE');
            }
            foreach ($raices as $otra) {
                if ($control->dentro($raiz, $otra) || $control->dentro($otra, $raiz)) throw new RuntimeException('RAICES_PRIVADAS_SUPERPUESTAS');
            }
            $pin = config('documentos_inscripcion.root_identity_sha256.'.$nombre);
            $marker = $raiz.'/.itm-raiz';
            $this->archivos->verificarRutaAbsoluta($marker);
            if (!is_string($pin) || !preg_match('/\A[a-f0-9]{64}\z/D', $pin) || !is_file($marker)
                || !is_readable($marker) || filesize($marker) < 16 || filesize($marker) > 4096 || !hash_equals($pin, hash_file('sha256', $marker))) {
                throw new RuntimeException('IDENTIDAD_DE_RAIZ_NO_VERIFICADA');
            }
            if (in_array($pin, $pines, true)) throw new RuntimeException('IDENTIDADES_DE_RAIZ_REPETIDAS');
            $raices[$nombre] = PHP_OS_FAMILY === 'Windows' ? strtolower($raiz) : $raiz;
            $pines[$nombre] = $pin;
        }
        return ['rutas' => $raices, 'identidades' => $pines];
    }

    public function exigirPermiso(string $archivo): void
    {
        $this->absoluta($archivo);
        if (!app(ControlTransicionDocumental::class)->dentro($archivo, $this->raiz('permisos')) || !is_file($archivo)) {
            throw new RuntimeException('PERMISO_FUERA_DE_RAIZ_APROBADA');
        }
    }

    private function absoluta(string $ruta): void
    {
        if (preg_match('/[%$]/', $ruta) || str_starts_with(str_replace('\\', '/', $ruta), '//')
            || (PHP_OS_FAMILY === 'Windows' && !preg_match('~\A[A-Za-z]:[/\\\\]~', $ruta))) {
            throw new RuntimeException('RUTA_REAL_AMBIGUA');
        }
        $this->archivos->verificarRutaAbsoluta($ruta);
    }
}
