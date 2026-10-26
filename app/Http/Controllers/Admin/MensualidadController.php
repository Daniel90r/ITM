<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mensualidad;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Pago;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MensualidadController extends Controller
{
    private array $meses = [
        '01' => 'Enero',   '02' => 'Febrero', '03' => 'Marzo',
        '04' => 'Abril',   '05' => 'Mayo',     '06' => 'Junio',
        '07' => 'Julio',   '08' => 'Agosto',   '09' => 'Septiembre',
        '10' => 'Octubre', '11' => 'Noviembre','12' => 'Diciembre',
    ];

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $cursosFiltro = app(\App\Services\CursosConsulta::class)->opciones($filtros);
        $anioSeleccionado = $filtros['anio'];

        // Conservar el alcance original: mensualidades por alumno y ciclo,
        // no por la inscripción del curso donde aparece en el listado.
        $resumen = $this->mensualidadesFiltradas($filtros)->select('alumno_id')
            ->selectRaw('COUNT(*) AS registros')
            ->selectRaw("SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes")
            ->selectRaw("SUM(CASE WHEN estado = 'Pagado' THEN 1 ELSE 0 END) AS pagadas")
            ->selectRaw("SUM(CASE WHEN estado = 'Pendiente' THEN monto ELSE 0 END) AS saldo")
            ->groupBy('alumno_id');
        $consulta = $this->inscripcionesVisibles($filtros)
            ->leftJoinSub($resumen, 'resumen_mensual', 'resumen_mensual.alumno_id', '=', 'inscripciones.alumno_id')
            ->select(['inscripciones.alumno_id', 'inscripciones.curso_id', 'cursos.nombre as orden_curso',
                'alumnos.apellido as orden_apellido', 'alumnos.nombre as orden_nombre'])
            ->selectRaw('COALESCE(resumen_mensual.registros, 0) AS registros, COALESCE(resumen_mensual.pendientes, 0) AS pendientes, COALESCE(resumen_mensual.pagadas, 0) AS pagadas, COALESCE(resumen_mensual.saldo, 0) AS saldo')
            ->when(!empty($filtros['mes']) || !empty($filtros['estado']), fn ($q) => $q->where('resumen_mensual.registros', '>', 0));
        if ($request->routeIs('admin.sugerencias.mensualidades')) {
            return \App\Services\SugerenciasFiltros::personas(\App\Services\SugerenciasFiltros::alumnosDe($consulta, 'inscripciones.alumno_id'));
        }
        $cursos = Curso::where('activo', 1)
            ->with(['inscripciones' => fn($q) => $q
                ->where('activa', 1)->where('estado', 'aprobada')
                ->select(['id', 'alumno_id', 'curso_id'])->with('alumno:id,nombre,apellido,codigo')
            ])->orderBy('nombre')->get();
        $listado = $consulta->distinct()->with(['alumno:id,nombre,apellido,codigo', 'curso'])
            ->orderBy('orden_curso')->orderBy('inscripciones.curso_id')
            ->orderBy('orden_apellido')->orderBy('orden_nombre')->orderBy('inscripciones.alumno_id')
            ->paginate(25)->appends($filtros);
        $grupos = $listado->getCollection()->groupBy('curso_id');

        $totalPendiente = Mensualidad::where('estado', 'Pendiente')->where('anio', $anioSeleccionado)->sum('monto');
        $totalPagado    = Mensualidad::where('estado', 'Pagado')->where('anio', $anioSeleccionado)->sum('monto');
        $totalAlumnos   = Inscripcion::aprobada()
                            ->distinct('alumno_id')
                            ->count('alumno_id');

        $aniosDisponibles = Mensualidad::distinct()->orderBy('anio', 'desc')->pluck('anio');
        if (!$aniosDisponibles->contains($anioSeleccionado)) {
            $aniosDisponibles = $aniosDisponibles->push($anioSeleccionado)->sort()->values();
        }

        return view('admin.mensualidades', compact(
            'cursos', 'cursosFiltro', 'listado', 'grupos', 'filtros', 'totalPendiente', 'totalPagado', 'totalAlumnos',
            'anioSeleccionado', 'aniosDisponibles'
        ) + ['meses' => $this->meses]);
    }

    public function detalles(Request $request, Curso $curso, Alumno $alumno)
    {
        $filtros = $this->filtros($request);
        abort_unless($this->inscripcionesVisibles($filtros)
            ->where('inscripciones.curso_id', $curso->id)->where('inscripciones.alumno_id', $alumno->id)->exists(), 404);
        $consulta = $this->mensualidadesFiltradas($filtros)->where('alumno_id', $alumno->id);
        // CASE funciona tanto en MySQL como en SQLite y admite los meses históricos numéricos.
        $orden = 'CASE'; $bindings = [];
        foreach ($this->meses as $numero => $nombre) {
            $orden .= ' WHEN mes IN (?, ?, ?) THEN '.(int) $numero;
            array_push($bindings, $nombre, (string) $numero, (string) (int) $numero);
        }
        $mensualidades = $consulta->orderByRaw($orden.' ELSE 13 END', $bindings)->orderBy('id')
            ->paginate(6, ['*'], 'mensualidad_page')->appends($filtros);
        return response()->json(['html' => view('admin.partials.mensualidades-detalle', compact('mensualidades', 'curso', 'alumno'))->render()])
            ->header('Cache-Control', 'private, no-store');
    }

    private function filtros(Request $request): array
    {
        $filtros = $request->validate([
            'incluir_historicos' => 'nullable|boolean',
            'anio' => 'nullable|integer', 'q' => 'nullable|string|max:100',
            'curso_id' => 'nullable|integer|min:1',
            'mes' => 'nullable|string|in:'.implode(',', $this->meses),
            'estado' => 'nullable|in:Pendiente,Pagado',
            'page' => 'nullable|integer|min:1', 'mensualidad_page' => 'nullable|integer|min:1',
        ]);
        unset($filtros['page'], $filtros['mensualidad_page']);
        $filtros['anio'] = (int) ($filtros['anio'] ?? date('Y'));
        $filtros['q'] = trim($filtros['q'] ?? '');
        return app(\App\Services\CursosConsulta::class)->normalizar($filtros);
    }

    private function mensualidadesFiltradas(array $filtros)
    {
        $query = Mensualidad::where('anio', $filtros['anio'])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado));
        if (!empty($filtros['mes'])) {
            $numero = array_search($filtros['mes'], $this->meses, true);
            $query->whereIn('mes', [$filtros['mes'], (string) $numero, (string) (int) $numero]);
        }
        return $query;
    }

    private function inscripcionesVisibles(array $filtros)
    {
        $query = Inscripcion::query()->join('cursos', 'cursos.id', '=', 'inscripciones.curso_id')
            ->join('alumnos', 'alumnos.id', '=', 'inscripciones.alumno_id')
            ->where('inscripciones.activa', 1)->where('inscripciones.estado', 'aprobada')
            ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->where('inscripciones.curso_id', $id));
        app(\App\Services\CursosConsulta::class)->aplicar($query, $filtros, 'inscripciones.curso_id');
        foreach (preg_split('/\s+/u', $filtros['q'], -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $query->where(fn ($q) => $q->whereRaw("alumnos.nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("alumnos.apellido LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("alumnos.codigo LIKE ? ESCAPE '!'", [$patron]));
        }
        return $query;
    }

    public function generar(Request $request)
    {
        $request->validate([
            'mes'       => ['required', 'string', 'in:' . implode(',', $this->meses)],
            'anio'      => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'tipo'      => ['required', 'in:todos,curso,uno'],
            'curso_id'  => ['required_if:tipo,curso', 'nullable', 'exists:cursos,id'],
            'alumno_id' => ['required_if:tipo,uno', 'nullable', 'exists:alumnos,id'],
        ]);

        $nombreMes = $request->mes;
        $numMes    = array_search($nombreMes, $this->meses, true);
        $anio      = (int) $request->anio;

        $query = Inscripcion::aprobada()->with('alumno');

        if ($request->tipo === 'curso') {
            $query->where('curso_id', $request->curso_id);
        }

        if ($request->tipo === 'uno') {
            $query->where('alumno_id', $request->alumno_id);
        }

        $inscripciones = $query->get()->unique('alumno_id');

        if ($inscripciones->isEmpty()) {
            throw ValidationException::withMessages([
                $request->tipo === 'uno' ? 'alumno_id' : ($request->tipo === 'curso' ? 'curso_id' : 'tipo')
                    => 'No hay inscripciones aprobadas para generar mensualidades con esta selección.',
            ]);
        }

        $generadas = 0;
        $omitidas  = 0;
        $errores   = 0;

        foreach($inscripciones as $ins) {
            $existe = Mensualidad::where('alumno_id', $ins->alumno_id)
                ->where('mes', $nombreMes)
                ->where('anio', $anio)
                ->exists();

            if (!$existe) {
                try {
                    Mensualidad::create([
                        'alumno_id'         => $ins->alumno_id,
                        'curso_id'          => $ins->curso_id,
                        'mes'               => $nombreMes,
                        'anio'              => $anio,
                        'monto'             => 25.00,
                        'estado'            => 'Pendiente',
                        'fecha_vencimiento' => now()
                            ->setMonth((int) $numMes)
                            ->setYear($anio)
                            ->endOfMonth()
                            ->toDateString(),
                    ]);
                    $generadas++;
                } catch (\Exception $e) {
                    Log::error('Error al generar mensualidad para alumno_id='.$ins->alumno_id.' mes='.$nombreMes.' anio='.$anio, ['exception' => $e]);
                    $errores++;
                }
            } else {
                $omitidas++;
            }
        }

        $msg = "Se generaron {$generadas} mensualidades para {$nombreMes} {$anio}.";
        if ($omitidas > 0) {
            $msg .= " {$omitidas} alumno(s) ya tenían mensualidad para este mes y año.";
        }
        if ($errores > 0) {
            $msg .= " {$errores} registro(s) falló(ron) por un error interno y se omitieron.";
        }

        if ($errores > 0) {
            return redirect()->route('admin.mensualidades')->with('error', $msg);
        }

        return redirect()->route('admin.mensualidades')->with('success', $msg);
    }

    public function pagar(Request $request, $id)
    {
        $mensualidad = Mensualidad::findOrFail($id);

        if ($mensualidad->estado === 'Pagado') {
            return redirect()->back()->with('error', 'Esta mensualidad ya fue pagada.');
        }

        Pago::create([
            'mensualidad_id' => $mensualidad->id,
            'fecha_pago'     => now()->toDateString(),
            'monto_pagado'   => $mensualidad->monto,
            'observacion'    => $request->observacion,
        ]);

        $mensualidad->update(['estado' => 'Pagado']);

        return redirect()->back()->with('success', 'Pago de $' . number_format($mensualidad->monto, 2) . ' registrado correctamente.');
    }

    // ═══════════════════════════════════════════════════════════════
    // COBRO EN LOTE: registra el pago de varias mensualidades a la vez
    // ═══════════════════════════════════════════════════════════════
    public function pagarLote(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:mensualidades,id'],
        ], [
            'ids.required' => 'No seleccionaste ninguna mensualidad.',
        ]);

        $cobradas = 0;
        $omitidas = 0;
        $total    = 0;

        DB::transaction(function () use ($request, &$cobradas, &$omitidas, &$total) {

            $mensualidades = Mensualidad::whereIn('id', $request->ids)->get();

            foreach ($mensualidades as $mensualidad) {

                // Solo se cobran las pendientes; las ya pagadas se omiten
                if ($mensualidad->estado === 'Pagado') {
                    $omitidas++;
                    continue;
                }

                Pago::create([
                    'mensualidad_id' => $mensualidad->id,
                    'fecha_pago'     => now()->toDateString(),
                    'monto_pagado'   => $mensualidad->monto,
                    'observacion'    => 'Cobro en lote',
                ]);

                $mensualidad->update(['estado' => 'Pagado']);

                $cobradas++;
                $total += (float) $mensualidad->monto;
            }
        });

        if ($cobradas === 0) {
            return redirect()->route('admin.mensualidades')
                ->with('error', 'Las mensualidades seleccionadas ya estaban pagadas.');
        }

        $msg = "Se registraron {$cobradas} pagos por \$" . number_format($total, 2) . '.';
        if ($omitidas > 0) {
            $msg .= " ({$omitidas} ya estaban pagadas y se omitieron)";
        }

        return redirect()->route('admin.mensualidades')->with('success', $msg);
    }

    // ═══════════════════════════════════════════════════════════════
    // REVERTIR PAGO
    // ═══════════════════════════════════════════════════════════════
    public function revertir($id)
    {
        $mensualidad = Mensualidad::findOrFail($id);

        if ($mensualidad->estado !== 'Pagado') {
            return redirect()->back()->with('error', 'Esta mensualidad no está pagada.');
        }

        // Eliminar el pago asociado
        Pago::where('mensualidad_id', $mensualidad->id)->delete();

        // Revertir estado a Pendiente
        $mensualidad->update(['estado' => 'Pendiente']);

        return redirect()->back()->with('success', 'Pago revertido correctamente. La mensualidad volvió a estado Pendiente.');
    }
}
