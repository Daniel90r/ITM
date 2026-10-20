<?php

namespace App\Services;

use RuntimeException;

class IdentidadEjecutorDocumental
{
    public function sid(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') throw new RuntimeException('IDENTIDAD_EJECUTORA_NO_VERIFICADA');
        $p = proc_open(['C:/Windows/System32/whoami.exe', '/user', '/fo', 'csv', '/nh'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($p)) throw new RuntimeException('IDENTIDAD_EJECUTORA_NO_VERIFICADA');
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($p);
        $row = str_getcsv(trim($out), ',', '"', '');
        if ($exit !== 0 || !preg_match('/\AS-1-5-21-(?:[0-9]+-){3}[0-9]+\z/D', $row[1] ?? '')) {
            throw new RuntimeException('IDENTIDAD_EJECUTORA_NO_VERIFICADA');
        }
        return $row[1];
    }

    public function exigir(string $esperada): void
    {
        if (!preg_match('/\AS-1-5-21-(?:[0-9]+-){3}[0-9]+\z/D', $esperada)
            || $esperada !== config('documentos_inscripcion.executor_sid') || $this->sid() !== $esperada) {
            throw new RuntimeException('IDENTIDAD_EJECUTORA_NO_COINCIDE');
        }
    }
}
