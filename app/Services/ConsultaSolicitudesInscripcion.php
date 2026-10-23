<?php

namespace App\Services;

use App\Models\Inscripcion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

/** Criterio común del listado de solicitudes y del informe PDF. */
class ConsultaSolicitudesInscripcion
{
    public const REGLAS = [
        'estado' => 'nullable|in:todos,pendiente,aprobada,rechazada,inactiva,otros',
        'q' => 'nullable|string|max:100',
    ];

    public const ESTADOS = ['pendiente', 'aprobada', 'rechazada', 'inactiva'];

    public function consulta(array $filtros)
    {
        $consulta = Inscripcion::with(['alumno', 'curso']);
        if (Schema::hasTable('reactivaciones_inscripcion')) {
            $consulta->with('reactivacion.administrador');
        }
        $estado = $filtros['estado'] ?? 'todos';
        if (in_array($estado, self::ESTADOS, true)) {
            $consulta->where('estado', $estado);
        } elseif ($estado === 'otros') {
            $consulta->where(fn ($q) => $q->whereNotIn('estado', self::ESTADOS)->orWhereNull('estado'));
        }
        foreach (preg_split('/\s+/u', trim($filtros['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $consulta->whereHas('alumno', fn ($q) => $q
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]));
        }
        return $consulta->orderByDesc('created_at')->orderByDesc('id');
    }

    public function informe(array $filtros): array
    {
        $filas = $this->consulta($filtros)->get();
        $conteos = $filas->countBy('estado');
        return [
            'filas' => $filas,
            'detalle' => $filas->values()->flatMap(function ($inscripcion, $numero) {
                $reactivacion = $inscripcion->relationLoaded('reactivacion') ? $inscripcion->reactivacion : null;
                // El portal utiliza observacion como snapshot JSON del encargado.
                // Es información del expediente, no una observación administrativa.
                $observacion = $inscripcion->observacion;
                if ($observacion !== null) {
                    $estructurada = json_decode($observacion, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($estructurada)) $observacion = null;
                }
                $motivos = collect([
                    $inscripcion->motivo_rechazo ? 'Rechazo: '.$inscripcion->motivo_rechazo : null,
                    $observacion ? 'Observación: '.$observacion : null,
                    $reactivacion ? 'Reactivación: '.$reactivacion->motivo : null,
                ])->filter()->implode("\n");
                $curso = $inscripcion->curso;
                $celdas = [
                    (string) ($numero + 1),
                    $inscripcion->alumno?->nombre_completo ?? 'Alumno no disponible',
                    $curso ? "$curso->nombre · $curso->nivel · $curso->seccion · $curso->anio_lectivo" : 'Curso no disponible',
                    $inscripcion->fecha_inscripcion ? Carbon::parse($inscripcion->fecha_inscripcion)->format('d/m/Y') : '—',
                    ucfirst($inscripcion->estado ?? 'No registrado'),
                    $reactivacion ? ($reactivacion->administrador?->name ?? 'No registrado').' (reactivación)' : 'No registrado',
                    $motivos ?: '—',
                    $reactivacion ? 'Reactivación registrada el '.$reactivacion->reactivada_at->timezone('America/El_Salvador')->format('d/m/Y H:i') : 'No registrada',
                ];
                // Un texto extenso continúa en filas pequeñas, que Dompdf puede paginar.
                $fragmentos = array_map(fn ($texto) => mb_str_split($texto, 180), $celdas);
                $cantidad = max(array_map('count', $fragmentos));
                $renglones = [];
                for ($i = 0; $i < $cantidad; $i++) {
                    $renglon = array_map(fn ($partes) => $partes[$i] ?? '', $fragmentos);
                    if ($i > 0) $renglon[0] = ($numero + 1).' (cont.)';
                    $renglones[] = $renglon;
                }
                return $renglones;
            }),
            'total' => $filas->count(),
            'pendientes' => $conteos->get('pendiente', 0),
            'aprobadas' => $conteos->get('aprobada', 0),
            'rechazadas' => $conteos->get('rechazada', 0),
            'inactivas' => $conteos->get('inactiva', 0),
            'otros' => $filas->whereNotIn('estado', self::ESTADOS)->count(),
        ];
    }
}
