<?php

namespace App\Services;

use App\Models\{Alumno, Curso, Inscripcion, Mensualidad};
use Carbon\CarbonImmutable;

class HistorialMensualidades
{
    public function consultar(Alumno $alumno, int $anio): array
    {
        $obligaciones = Mensualidad::where('alumno_id', $alumno->id)->where('anio', $anio)
            ->with('pagos')->orderBy('id')->get();
        $cursos = Curso::where(fn ($q) => $q
            ->whereIn('id', Mensualidad::where('alumno_id', $alumno->id)->whereNotNull('curso_id')->select('curso_id'))
            ->orWhereIn('id', Inscripcion::where('alumno_id', $alumno->id)->select('curso_id')))->orderBy('nombre')->get();
        $nombresCursos = $cursos->keyBy('id');
        $grupos = $obligaciones->groupBy(fn ($m) => $this->numeroMes($m->mes) ?? 'invalido-'.$m->id);
        $resumen = ['pagado' => 0, 'pendiente' => 0, 'vencido' => 0, 'en_revision' => 0, 'posibles_duplicados' => 0];
        $hoy = CarbonImmutable::now('America/El_Salvador')->startOfDay();
        $filas = $obligaciones->map(function ($m) use ($grupos, $nombresCursos, $hoy, &$resumen) {
            $mes = $this->numeroMes($m->mes);
            $duplicada = $mes !== null && $grupos->get($mes)->count() > 1;
            $centavos = (int) round((float) $m->monto * 100);
            $vence = $m->fecha_vencimiento ? CarbonImmutable::parse($m->fecha_vencimiento, 'America/El_Salvador')->startOfDay() : null;
            $vencida = $m->estado === 'Pendiente' && $vence && $vence->lt($hoy);
            $estado = $vencida ? 'Vencido' : $m->estado;
            // No elegir arbitrariamente una obligación de un grupo ambiguo ni inflar el saldo.
            if (!$duplicada) {
                if ($m->estado === 'Pagado') $resumen['pagado'] += $centavos;
                if ($m->estado === 'Pendiente') $resumen['pendiente'] += $centavos;
                if ($vencida) $resumen['vencido'] += $centavos;
            }
            $pagoConsistente = $m->estado === 'Pagado'
                ? $m->pagos->count() === 1 && (int) round((float) $m->pagos->first()->monto_pagado * 100) === $centavos
                : $m->pagos->isEmpty();
            $advertencias = [];
            if ($duplicada) { $advertencias[] = 'Posible duplicado: varias obligaciones del alumno para este mes y año, incluso en otros cursos.'; $resumen['posibles_duplicados']++; }
            if (!$pagoConsistente) $advertencias[] = 'El estado y los movimientos de pago requieren revisión.';
            if (!in_array($m->estado, ['Pagado', 'Pendiente'], true)) $advertencias[] = 'Estado no reconocido; importe excluido de los totales por estado.';
            if ($mes === null) $advertencias[] = 'Mes no reconocido; consultar el registro original.';
            if ($advertencias) $resumen['en_revision'] += $centavos;
            return ['registro' => $m, 'mes' => $mes, 'estado' => $estado, 'centavos' => $centavos,
                'vencimiento' => $vence?->format('d/m/Y'),
                'curso' => $m->curso_id === null ? 'Sin curso asociado' : ($nombresCursos->get($m->curso_id)?->nombre.' · '.$nombresCursos->get($m->curso_id)?->seccion.' · '.$nombresCursos->get($m->curso_id)?->anio_lectivo),
                'fechas_pago' => $m->estado === 'Pagado' ? $m->pagos->map(fn ($p) => $p->fecha_pago?->format('d/m/Y'))->filter()->unique()->implode(', ') : '',
                'advertencias' => $advertencias];
        });
        $referencia = $this->referencia($alumno);
        $meses = collect(ReportesAdministrativos::MESES)->filter(function ($nombre, $numero) use ($referencia, $anio, $hoy, $filas) {
            // Las obligaciones existentes prevalecen sobre cualquier intervalo de visualización.
            if ($filas->contains('mes', $numero)) return true;
            if ($referencia['fecha'] === null) return true;
            $mes = CarbonImmutable::create($anio, $numero, 1, 0, 0, 0, 'America/El_Salvador');
            return $mes->gte($referencia['fecha']->startOfMonth()) && $mes->lte($hoy->startOfMonth());
        })->map(fn ($nombre, $numero) => [
            'nombre' => $nombre, 'numero' => $numero, 'filas' => $filas->where('mes', $numero)->values(),
        ]);
        $sinMes = $filas->filter(fn ($fila) => $fila['mes'] === null)->values();
        $asignaciones = Inscripcion::where('alumno_id', $alumno->id)->where('estado', 'aprobada')
            ->where('activa', 1)->with('curso')->get();
        $cursoAsignado = $asignaciones->count() === 1 ? $asignaciones->first()->curso : null;
        $asignacionAmbigua = $asignaciones->count() > 1;
        return compact('alumno', 'anio', 'meses', 'sinMes', 'resumen', 'referencia', 'cursoAsignado', 'asignacionAmbigua');
    }

    private function referencia(Alumno $alumno): array
    {
        $insegura = ['fecha' => null, 'explicacion' => 'No se pudo determinar el inicio. Se muestran los 12 meses sin crear deudas.'];
        $inscripciones = Inscripcion::where('alumno_id', $alumno->id)->get(['curso_id', 'fecha_inscripcion']);
        if ($inscripciones->isEmpty()) return $insegura;
        // No atribuir a obligaciones de origen desconocido la inscripción de otro curso.
        $origenes = Mensualidad::where('alumno_id', $alumno->id)->distinct()->pluck('curso_id');
        foreach ($origenes as $id) {
            if ($id === null || !$inscripciones->contains('curso_id', $id)) return $insegura;
        }
        $fechas = collect();
        foreach ($inscripciones as $inscripcion) {
            $valor = (string) $inscripcion->getRawOriginal('fecha_inscripcion');
            if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $valor)) return $insegura;
            try {
                $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor, 'America/El_Salvador');
                if (!$fecha || $fecha->year < 1 || $fecha->format('Y-m-d') !== $valor) return $insegura;
            } catch (\Throwable) { return $insegura; }
            $fechas->push($fecha);
        }
        if ($fechas->map->toDateString()->unique()->count() !== 1) {
            return ['fecha' => null, 'explicacion' => 'Hay distintas fechas de solicitud/inscripción. Se muestran los 12 meses para conservar el historial completo.'];
        }
        return ['fecha' => $fechas->first(), 'explicacion' => 'Referencia visual; no indica aprobación ni inicio obligatorio de cobro.'];
    }

    private function numeroMes($valor): ?int
    {
        $valor = trim((string) $valor);
        if (ctype_digit($valor)) return (int) $valor >= 1 && (int) $valor <= 12 ? (int) $valor : null;
        foreach (ReportesAdministrativos::MESES as $numero => $nombre) {
            if (mb_strtolower($valor) === mb_strtolower($nombre)) return $numero;
        }
        return null;
    }
}
