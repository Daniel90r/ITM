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

    private function alumnos(array $f): array
    {
        $aprobadas = fn ($q) => $q->aprobada()
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id));
        $q = Alumno::select('id', 'nombre', 'apellido', 'codigo', 'activo')
            ->whereHas('inscripciones', $aprobadas)
            ->when(isset($f['estado']), fn ($q) => $q->where('activo', $f['estado'] === 'activo'));
        $total = (clone $q)->count();
        $filas = $q->with(['inscripciones' => fn ($q) => $aprobadas($q)
            ->select('id', 'alumno_id', 'curso_id', 'estado')->with('curso:id,nombre,nivel,seccion,anio_lectivo')])
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate(25)->appends($f);
        $filas->through(fn ($a) => [$a->codigo, $a->nombre_completo,
            $a->inscripciones->pluck('curso')->filter()->unique('id')->map(fn ($c) => $this->curso($c))->implode(' · '),
            'Aprobada', $a->activo ? 'Activo' : 'Inactivo']);
        return $this->resultado($filas, ['Código', 'Alumno', 'Cursos aprobados', 'Inscripción', 'Estado del alumno'],
            ['Alumnos únicos' => $total], 'Un alumno por fila. Solo inscripciones aprobadas; cursos históricos incluidos y repetidos del mismo curso agrupados. El filtro de estado corresponde al alumno.');
    }

    private function cursos(array $f): array
    {
        $q = Curso::query()->withCount(['inscripciones as aprobadas_count' => fn ($q) => $q->aprobada()])
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('id', $id));
        if (!empty($f['estado'])) {
            // Reutiliza el accesor canónico (incluye fecha vencida y finalización manual).
            $ids = $this->catalogoCursos()->filter(fn ($c) => $c->estado_curso === $f['estado'])->pluck('id');
            $q->whereIn('id', $ids);
        }
        $total = (clone $q)->count();
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
            ->leftJoin('materias as m', 'm.id', '=', 'd.materia_id')
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('c.id', $id))
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('s.estado', $estado))
            ->when($f['fecha_desde'] ?? null, fn ($q, $v) => $q->whereDate('s.fecha', '>=', $v))
            ->when($f['fecha_hasta'] ?? null, fn ($q, $v) => $q->whereDate('s.fecha', '<=', $v));
        $conteos = (clone $q)->selectRaw('s.estado, COUNT(*) as cantidad')->groupBy('s.estado')->pluck('cantidad', 'estado');
        $filas = $q->select('s.id', 'a.codigo', 'a.nombre', 'a.apellido', 'c.nombre as curso', 'c.seccion', 'c.anio_lectivo',
            'm.nombre as materia', 's.fecha', 's.estado')->orderByDesc('s.fecha')->orderByDesc('s.id')->paginate(25)->appends($f);
        $filas->through(fn ($s) => [$s->fecha, $s->codigo, "$s->nombre $s->apellido", "$s->curso · $s->seccion · $s->anio_lectivo", $s->materia, ucfirst($s->estado)]);
        return $this->resultado($filas, ['Fecha', 'Código', 'Alumno', 'Curso', 'Materia', 'Estado'],
            ['Registros' => $conteos->sum(), 'Presentes' => $conteos->get('presente', 0), 'Ausentes' => $conteos->get('ausente', 0),
                'Permisos' => $conteos->get('permiso', 0)], 'Solo registros capturados, incluidos los históricos. No se suponen ausencias en fechas sin registro.');
    }

    private function mensualidades(array $f): array
    {
        $q = DB::table('mensualidades as v')->join('alumnos as a', 'a.id', '=', 'v.alumno_id')
            ->join('cursos as c', 'c.id', '=', 'v.curso_id')
            ->when($f['curso_id'] ?? null, fn ($q, $id) => $q->where('c.id', $id))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('v.estado', $v))
            ->when($f['anio'] ?? null, fn ($q, $v) => $q->where('v.anio', $v))
            ->when($f['mes'] ?? null, fn ($q, $v) => $q->whereIn('v.mes', [self::MESES[(int) $v],
                (string) (int) $v, str_pad((string) (int) $v, 2, '0', STR_PAD_LEFT)]));
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

    private function maestros(array $f): array
    {
        $asignaciones = fn ($q) => $q->activas()->when($f['curso_id'] ?? null,
            fn ($q, $id) => $q->whereHas('detalleCurso', fn ($q) => $q->where('curso_id', $id)));
        $q = Maestro::select('id', 'nombre', 'apellido', 'codigo', 'activo')
            ->when(isset($f['estado']), fn ($q) => $q->where('activo', $f['estado'] === 'activo'))
            ->when($f['curso_id'] ?? null, fn ($q) => $q->whereHas('asignacionesDocentes', $asignaciones));
        $total = (clone $q)->count();
        $filas = $q->with(['asignacionesDocentes' => fn ($q) => $asignaciones($q)
            ->select('id', 'maestro_id', 'detalle_curso_id', 'estado')
            ->with(['detalleCurso' => fn ($q) => $q->select('id', 'curso_id', 'materia_id', 'maestro_id')
                ->withCount(['asignacionesDocentes as activas_count' => fn ($q) => $q->activas()]),
                'detalleCurso.curso:id,nombre,nivel,seccion,anio_lectivo,activo', 'detalleCurso.materia:id,nombre,activa'])])
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate(25)->appends($f);
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

    private function curso($c): string
    {
        return "$c->nombre · $c->nivel · $c->seccion · $c->anio_lectivo";
    }

    private function resultado($filas, array $columnas, array $resumen, string $descripcion): array
    {
        return compact('filas', 'columnas', 'resumen', 'descripcion');
    }
}
