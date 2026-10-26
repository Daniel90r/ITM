<?php

namespace App\Services;

use App\Models\{Alumno, Curso, Maestro};
use Illuminate\Support\Facades\DB;

/** Consultas de presentación: no guarda modelos ni emite documentos. */
class ReportesAdministrativos
{
    public const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public const TIPOS = [
        'alumnos' => 'Alumnos inscritos', 'cursos' => 'Cursos',
        'calificaciones' => 'Calificaciones', 'asistencias' => 'Asistencias',
        'mensualidades' => 'Mensualidades', 'maestros' => 'Maestros',
    ];

    public function catalogoCursos()
    {
        return Curso::select('id', 'nombre', 'nivel', 'seccion', 'anio_lectivo', 'activo',
            'fecha_inicio', 'fecha_fin', 'estado', 'finalizado_manualmente')->orderBy('nombre')->orderBy('id')->get();
    }

    public function consultar(string $tipo, array $f): array
    {
        return $this->{$tipo}($f);
    }

    private function inscripcionesAlumnos($q, array $f)
    {
        return $q->aprobada()
            ->when(array_key_exists('incluir_historicos', $f), fn ($q) => app(CursosConsulta::class)->aplicar($q, $f))
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id))
            ->when($f['anio'] ?? null, function ($q, $anio) use ($f) {
                $inicio = \Carbon\CarbonImmutable::create((int) $anio, (int) ($f['mes'] ?? 1), 1)->startOfDay();
                $fin = isset($f['mes']) ? $inicio->addMonth() : $inicio->addYear();
                $q->where('fecha_inscripcion', '>=', $inicio->toDateString())->where('fecha_inscripcion', '<', $fin->toDateString());
            });
    }

    private function consultaAlumnos(array $f)
    {
        return Alumno::select('id', 'nombre', 'apellido', 'codigo', 'activo')
            ->whereHas('inscripciones', fn ($q) => $this->inscripcionesAlumnos($q, $f))
            ->when(isset($f['estado']), fn ($q) => $q->where('activo', $f['estado'] === 'activo'))
            ->with(['inscripciones' => fn ($q) => $this->inscripcionesAlumnos($q, $f)
                ->select('id', 'alumno_id', 'curso_id', 'estado', 'fecha_inscripcion')
                ->orderBy('fecha_inscripcion')->orderBy('id')->with('curso:id,nombre,nivel,seccion,anio_lectivo')])
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id');
    }

    private function filaAlumno(Alumno $a): array
    {
        return [$a->codigo, $a->nombre_completo,
            $a->inscripciones->pluck('curso')->filter()->unique('id')->map(fn ($c) => $this->curso($c))->implode(' / '),
            'Aprobada', $a->activo ? 'Activo' : 'Inactivo',
            $a->inscripciones->pluck('fecha_inscripcion')->filter()->unique()->map(fn ($fecha) => \Carbon\Carbon::parse($fecha)->format('d/m/Y'))->implode(', ')];
    }

    private function alumnos(array $f): array
    {
        $filas = $this->consultaAlumnos($f)->paginate(25)->appends($f);
        $total = $filas->total();
        $filas->through(fn ($a) => $this->filaAlumno($a));
        return $this->resultado($filas, ['Código', 'Alumno', 'Cursos aprobados', 'Inscripción', 'Estado del alumno', 'Fecha registrada de solicitud/inscripción'],
            ['Alumnos únicos' => $total], 'Un alumno por fila. Solo inscripciones actualmente aprobadas. El período corresponde a la fecha registrada de solicitud/inscripción, no a la fecha de aprobación. El estado corresponde al alumno.');
    }

    public function alumnosPdf(array $f): array
    {
        $filas = $this->consultaAlumnos($f)->get()->map(fn ($a) => $this->filaAlumno($a));
        return ['filas' => $filas, 'totalAlumnos' => $filas->count(),
            'cursoFiltro' => isset($f['curso_id']) ? Curso::find($f['curso_id']) : null];
    }

    private function consultaCursos(array $f)
    {
        $q = Curso::query()->withCount(['inscripciones as aprobadas_count' => fn ($q) => $q->aprobada()])
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('id', $id));
        app(CursosConsulta::class)->aplicar($q, $f, 'cursos.id');
        if (!empty($f['modalidad'])) {
            $inicio = \Carbon\CarbonImmutable::create((int)$f['anio'], $f['modalidad'] === 'mensual' ? (int)$f['mes'] : 1, 1)->startOfDay();
            $fin = $f['modalidad'] === 'mensual' ? $inicio->endOfMonth() : $inicio->endOfYear();
            $periodo = fn ($i) => $i->aprobada()->whereBetween('fecha_inscripcion', [$inicio->toDateString(), $fin->toDateString()]);
            $q->whereHas('inscripciones', $periodo)->withCount(['inscripciones as periodo_count'=>$periodo]);
        }
        if (!empty($f['estado'])) {
            // Reutiliza el accesor canónico (incluye fecha vencida y finalización manual).
            $ids = $this->catalogoCursos()->filter(fn ($c) => $c->estado_curso === $f['estado'])->pluck('id');
            $q->whereIn('id', $ids);
        }
        return $q;
    }

    public function cursosPdf(array $f): array
    {
        $filas = $this->consultaCursos($f)->orderBy('nombre')->orderBy('id')->get();
        return [
            'filas' => $filas,
            'totalCursos' => $filas->count(),
            'totalInscritos' => $filas->sum('aprobadas_count'),
            'totalDisponibles' => $filas->sum(fn ($c) => max(0, Curso::CUPOS_MAXIMO - $c->aprobadas_count)),
            'totalPeriodo' => $filas->sum('periodo_count'),
        ];
    }

    private function cursos(array $f): array
    {
        $q = $this->consultaCursos($f);
        $total = (clone $q)->count();
        if (!empty($f['modalidad'])) {
            $resumen = (clone $q)->get()->reduce(function ($s, $c) {
                $s['Aprobadas con fecha en el período'] += $c->periodo_count;
                $s['Aprobadas actuales'] += $c->aprobadas_count;
                $s['Disponibles actuales'] += max(0, Curso::CUPOS_MAXIMO - $c->aprobadas_count);
                return $s;
            }, ['Cursos'=>$total,'Aprobadas con fecha en el período'=>0,'Aprobadas actuales'=>0,'Disponibles actuales'=>0]);
            $filas = $q->orderBy('nombre')->orderBy('id')->paginate(25)->appends($f);
            $filas->through(fn ($c) => [$this->curso($c),$c->estado_curso,$c->activo?'Visible':'Oculto',$c->periodo_count,$c->aprobadas_count,Curso::CUPOS_MAXIMO,max(0,Curso::CUPOS_MAXIMO-$c->aprobadas_count)]);
            $columnas = ['Curso','Estado actual','Visibilidad actual','Aprobadas con fecha en el período','Aprobadas actuales','Cupo máximo actual','Disponibles actuales'];
            if ($f['modalidad'] === 'anual') {
                $columnas = array_map(fn ($texto) => str_replace('período', 'año', $texto), $columnas);
                $resumen = array_combine(array_map(fn ($texto) => str_replace('período', 'año', $texto), array_keys($resumen)), array_values($resumen));
            }
            return $this->resultado($filas,$columnas,$resumen,
                'Fecha registrada de solicitud/inscripción, no de aprobación. Estado, visibilidad y cupos son actuales; no representan una situación histórica.');
        }
        $filas = $q->orderBy('nombre')->orderBy('id')->paginate(25)->appends($f);
        $filas->through(fn ($c) => [$this->curso($c), $c->estado_curso, $c->activo ? 'Visible' : 'Oculto',
            Curso::CUPOS_MAXIMO, $c->aprobadas_count, max(0, Curso::CUPOS_MAXIMO - $c->aprobadas_count)]);
        return $this->resultado($filas, ['Curso', 'Estado', 'Visibilidad', 'Cupos', 'Inscripciones aprobadas', 'Disponibles'],
            ['Cursos' => $total], 'Cupo máximo: '.Curso::CUPOS_MAXIMO.'. Se cuentan inscripciones aprobadas, igual que Cursos; la disponibilidad nunca es negativa. Un curso puede estar oculto aunque su estado académico sea ACTIVO.');
    }

    private function calificaciones(array $f): array
    {
        $q = DB::table('notas as n')->join('alumnos as a', 'a.id', '=', 'n.alumno_id')
            ->join('detalle_cursos as d', 'd.id', '=', 'n.detalle_curso_id')
            ->join('cursos as c', 'c.id', '=', 'd.curso_id')->join('materias as m', 'm.id', '=', 'd.materia_id')
            ->where('m.activa', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('inscripciones as i')
                ->whereColumn('i.alumno_id', 'a.id')->whereColumn('i.curso_id', 'c.id')->where('i.estado', 'aprobada'))
            ->where(fn ($q) => $q->where('m.es_opcional', false)->orWhereExists(fn ($q) => $q->selectRaw('1')
                ->from('alumno_materia as am')->whereColumn('am.alumno_id', 'a.id')
                ->whereColumn('am.curso_id', 'c.id')->whereColumn('am.materia_id', 'm.id')))
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('c.id', $id))
            ->when($f['materia_id'] ?? null, fn ($q, $id) => $q->where('m.id', $id));
        app(CursosConsulta::class)->aplicar($q, $f, 'c.id');
        $total = (clone $q)->count();
        $filas = $q->select('n.id', 'a.codigo', 'a.nombre', 'a.apellido', 'c.nombre as curso', 'c.seccion',
            'c.anio_lectivo', 'm.nombre as materia', 'n.laboratorio', 'n.examen_teorico', 'n.practica', 'n.conducta', 'n.promedio')
            ->orderBy('a.apellido')->orderBy('a.nombre')->orderBy('n.id')->paginate(25)->appends($f);
        $filas->through(fn ($n) => [$n->codigo, "$n->nombre $n->apellido", "$n->curso · $n->seccion · $n->anio_lectivo",
            $n->materia, $n->laboratorio, $n->examen_teorico, $n->practica, $n->conducta, $n->promedio]);
        return $this->resultado($filas, ['Código', 'Alumno', 'Curso', 'Materia', 'Laboratorio', 'Examen teórico', 'Práctica', 'Conducta', 'Promedio almacenado'],
            ['Registros de notas' => $total], 'Promedio almacenado por materia: el sistema redondea la media de laboratorio, examen y práctica no nulos; conducta no interviene. Este reporte no recalcula ni guarda notas. Solo materias activas y alumnos con inscripción aprobada; las opcionales requieren asignación en ese mismo curso. — significa sin captura.');
    }

    private function asistencias(array $f): array
    {
        $q = DB::table('asistencias as s')->join('alumnos as a', 'a.id', '=', 's.alumno_id')
            ->join('cursos as c', 'c.id', '=', 's.curso_id')
            ->leftJoin('detalle_cursos as d', 'd.id', '=', 's.detalle_curso_id')
            ->leftJoin('materias as m', 'm.id', '=', 'd.materia_id');
        $q = app(FiltrosHistoricosAsistencia::class)->aplicar($q, $f, 's.');
        app(CursosConsulta::class)->aplicar($q, $f, 's.curso_id');
        $conteos = (clone $q)->selectRaw('s.estado, COUNT(*) as cantidad')->groupBy('s.estado')->pluck('cantidad', 'estado');
        $filas = $q->select('s.id', 'a.codigo', 'a.nombre', 'a.apellido', 'c.nombre as curso', 'c.seccion', 'c.anio_lectivo',
            'm.nombre as materia', 's.fecha', 's.estado')->orderByDesc('s.fecha')->orderByDesc('s.id')->paginate(25)->appends($f);
        $filas->through(fn ($s) => [$s->fecha, $s->codigo, "$s->nombre $s->apellido", "$s->curso · $s->seccion · $s->anio_lectivo", $s->materia, ucfirst($s->estado)]);
        return $this->resultado($filas, ['Fecha', 'Código', 'Alumno', 'Curso', 'Materia', 'Estado'],
            ['Registros' => $conteos->sum(), 'Presentes' => $conteos->get('presente', 0), 'Ausentes' => $conteos->get('ausente', 0),
                'Permisos' => $conteos->get('permiso', 0)], 'Solo registros capturados, incluidos los históricos. No se suponen ausencias en fechas sin registro.');
    }

    private function consultaMensualidades(array $f)
    {
        return DB::table('mensualidades as v')->join('alumnos as a', 'a.id', '=', 'v.alumno_id')
            ->when(array_key_exists('incluir_historicos', $f), fn ($q) => app(CursosConsulta::class)->aplicar($q, $f, 'v.curso_id'))
            ->join('cursos as c', 'c.id', '=', 'v.curso_id')
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('c.id', $id))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('v.estado', $v))
            ->when($f['anio'] ?? null, fn ($q, $v) => $q->where('v.anio', $v))
            ->when($f['mes'] ?? null, fn ($q, $v) => $q->whereIn('v.mes', [self::MESES[(int) $v],
                (string) (int) $v, str_pad((string) (int) $v, 2, '0', STR_PAD_LEFT)]));
    }

    private function mensualidades(array $f): array
    {
        $q = $this->consultaMensualidades($f);
        $s = (clone $q)->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN v.estado = 'Pendiente' THEN v.monto ELSE 0 END), 0) as pendiente, COALESCE(SUM(CASE WHEN v.estado = 'Pagado' THEN v.monto ELSE 0 END), 0) as pagado")->first();
        $filas = $q->select('v.id', 'a.codigo', 'a.nombre', 'a.apellido', 'c.nombre as curso', 'c.seccion', 'c.anio_lectivo',
            'v.mes', 'v.anio', 'v.monto', 'v.estado', 'v.fecha_vencimiento')
            ->orderByDesc('v.anio')->orderByDesc('v.mes')->orderBy('v.id')->paginate(25)->appends($f);
        $filas->through(fn ($v) => [$v->codigo, "$v->nombre $v->apellido", "$v->curso · $v->seccion · $v->anio_lectivo",
            "$v->mes / $v->anio", '$'.number_format((float) $v->monto, 2), $v->estado, $v->fecha_vencimiento]);
        return $this->resultado($filas, ['Código', 'Alumno', 'Curso', 'Mes / año', 'Monto', 'Estado', 'Vencimiento'],
            ['Mensualidades' => $s->total, 'Monto pendiente' => '$'.number_format((float) $s->pendiente, 2),
                'Monto marcado pagado' => '$'.number_format((float) $s->pagado, 2)],
            'Se suma el monto de cada mensualidad una sola vez según su estado Pendiente o Pagado, igual que el panel de mensualidades. No representa una conciliación de caja ni suma movimientos de pagos. Incluye obligaciones históricas.');
    }

    public function mensualidadesPdf(array $f): array
    {
        $filas = $this->consultaMensualidades($f)->select('v.*', 'a.nombre', 'a.apellido',
            'c.nombre as curso', 'c.seccion', 'c.anio_lectivo')->orderByDesc('v.anio')->orderByDesc('v.mes')->orderBy('v.id')->get();
        $pagos = DB::table('pagos')->whereIn('mensualidad_id', $filas->pluck('id'))
            ->selectRaw('mensualidad_id, COUNT(*) as cantidad, SUM(monto_pagado) as monto')->groupBy('mensualidad_id')->get()->keyBy('mensualidad_id');
        $pagosRegistrados = 0;
        $incidencias = 0;
        $pendienteCentavos = 0;
        $pagadoCentavos = 0;
        foreach ($filas as $fila) {
            $centavos = (int) round((float) $fila->monto * 100);
            $pago = $pagos->get($fila->id);
            $fila->pagadoCentavos = $fila->estado === 'Pagado' ? $centavos : 0;
            $fila->pendienteCentavos = $fila->estado === 'Pendiente' ? $centavos : 0;
            $pagadoCentavos += $fila->pagadoCentavos;
            $pendienteCentavos += $fila->pendienteCentavos;
            if ($fila->estado === 'Pagado') {
                $pagosRegistrados += (int) ($pago?->cantidad ?? 0);
                if (!$pago || (int) $pago->cantidad !== 1 || (int) round((float) $pago->monto * 100) !== $centavos) $incidencias++;
            } elseif ($pago) {
                // Un movimiento residual de una mensualidad pendiente no es un pago vigente.
                $incidencias++;
            }
        }
        return compact('filas', 'pagosRegistrados', 'incidencias', 'pendienteCentavos', 'pagadoCentavos') + [
            'totalAlumnos' => $filas->pluck('alumno_id')->unique()->count(),
            'pendientes' => $filas->where('estado', 'Pendiente')->count(),
        ];
    }

    private function consultaMaestros(array $f)
    {
        $asignaciones = fn ($q) => $q->activas()
            ->when(array_key_exists('incluir_historicos', $f), fn ($q) => $q->whereHas('detalleCurso', fn ($d) => app(CursosConsulta::class)->aplicar($d, $f)))
            ->when($f['curso_id'] ?? null,
            fn ($q, $id) => $q->whereHas('detalleCurso', fn ($q) => $q->where('curso_id', $id)));
        $q = Maestro::select('id', 'nombre', 'apellido', 'codigo', 'activo')
            ->when(isset($f['estado']), fn ($q) => $q->where('activo', $f['estado'] === 'activo'))
            ->when($f['curso_id'] ?? null, fn ($q) => $q->whereHas('asignacionesDocentes', $asignaciones));
        return $q->with(['asignacionesDocentes' => fn ($q) => $asignaciones($q)
            ->select('id', 'maestro_id', 'detalle_curso_id', 'estado', 'fecha_inicio', 'fecha_fin', 'motivo_cambio')
            ->with(['detalleCurso' => fn ($q) => $q->select('id', 'curso_id', 'materia_id', 'maestro_id')
                ->withCount(['asignacionesDocentes as activas_count' => fn ($q) => $q->activas()]),
                'detalleCurso.curso:id,nombre,nivel,seccion,anio_lectivo,activo', 'detalleCurso.materia:id,nombre,activa'])])
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id');
    }

    private function maestros(array $f): array
    {
        $q = $this->consultaMaestros($f);
        $total = (clone $q)->count();
        $filas = $q->paginate(25)->appends($f);
        $filas->through(function ($m) {
            $asignadas = $m->asignacionesDocentes->map(function ($a) use ($m) {
                $d = $a->detalleCurso;
                $valida = $m->activo && $d?->curso?->activo && $d?->materia?->activa
                    && (int) $d->maestro_id === (int) $m->id && (int) $d->activas_count === 1;
                return ($d?->curso ? $this->curso($d->curso) : 'Curso no disponible').' / '.($d?->materia?->nombre ?? 'Materia no disponible')
                    .($valida ? ' (activa)' : ' (activa; revisar disponibilidad o asignación)');
            })->unique()->implode(' · ');
            return [$m->codigo, $m->nombre_completo, $m->activo ? 'Activo' : 'Inactivo', $asignadas ?: 'Sin asignaciones activas'];
        });
        return $this->resultado($filas, ['Código', 'Maestro', 'Estado', 'Cursos / materias'], ['Maestros' => $total],
            'Asignaciones con estado activa en asignaciones_docentes. Las programadas y finalizadas no se presentan como vigentes. Se señalan maestros o catálogos inactivos y diferencias con detalle_cursos.');
    }

    public function maestrosPdf(array $f): array
    {
        $maestros = $this->consultaMaestros($f)->get();
        $asignaciones = $maestros->flatMap->asignacionesDocentes;
        $anios = $asignaciones->map(fn ($a) => $a->detalleCurso?->curso?->anio_lectivo)->filter()->unique()->sort()->values();
        if ($anios->isEmpty() && isset($f['curso_id'])) {
            $anio = Curso::whereKey($f['curso_id'])->value('anio_lectivo');
            if ($anio) $anios->push($anio);
        }
        $detalle = $maestros->flatMap(function ($m) {
            if ($m->asignacionesDocentes->isEmpty()) {
                return [[$m->nombre_completo, 'Sin asignaciones activas', '—', '—', 'Estado del docente: '.($m->activo ? 'Activo' : 'Inactivo')]];
            }
            return $m->asignacionesDocentes->sortBy('id')->flatMap(function ($a) use ($m) {
                $d = $a->detalleCurso;
                $notas = collect([$a->motivo_cambio]);
                if (!$m->activo) $notas->push('Docente inactivo.');
                if (!$d?->curso?->activo) $notas->push('Curso no disponible o inactivo.');
                if (!$d?->materia?->activa) $notas->push('Materia no disponible o inactiva.');
                if ((int) $d?->maestro_id !== (int) $m->id || (int) $d?->activas_count !== 1) {
                    $notas->push('Revisar disponibilidad o asignación: no coincide el docente o hay varias asignaciones activas.');
                }
                $celdas = [$m->nombre_completo,
                    ($d?->curso ? $this->curso($d->curso) : 'Curso no disponible')."\nMateria: ".($d?->materia?->nombre ?? 'No disponible'),
                    ucfirst($a->estado), $a->fecha_inicio?->format('d/m/Y') ?? '—', $notas->filter()->implode("\n") ?: '—'];
                $fragmentos = array_map(fn ($texto) => mb_str_split($texto, 240), $celdas);
                $renglones = [];
                for ($i = 0; $i < max(array_map('count', $fragmentos)); $i++) {
                    $fila = array_map(fn ($partes) => $partes[$i] ?? '', $fragmentos);
                    if ($i > 0) $fila[0] = $m->nombre_completo.' (cont.)';
                    $renglones[] = $fila;
                }
                return $renglones;
            });
        });
        return ['detalle' => $detalle, 'totalDocentes' => $maestros->count(),
            'totalActivas' => $asignaciones->count(), 'anios' => $anios];
    }

    private function curso($c): string
    {
        return "$c->nombre · $c->nivel · $c->seccion · $c->anio_lectivo";
    }

    private function resultado($filas, array $columnas, array $resumen, string $descripcion): array
    {
        return compact('filas', 'columnas', 'resumen', 'descripcion');
    }
}
