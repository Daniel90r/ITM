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
        $anioSeleccionado = (int) ($request->query('anio', date('Y')));

        $cursos = Curso::where('activo', 1)
            ->with(['inscripciones' => fn($q) => $q
                ->where('activa', 1)
                ->where('estado', 'aprobada')
                ->with('alumno')
            ])
            ->orderBy('nombre')
            ->get();

        $mensualidadesPorAlumno = Mensualidad::where('anio', $anioSeleccionado)
            ->orderByRaw(
                "FIELD(mes, 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre')"
            )
            ->get()
            ->groupBy('alumno_id');

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
            'cursos', 'mensualidadesPorAlumno', 'totalPendiente', 'totalPagado', 'totalAlumnos',
            'anioSeleccionado', 'aniosDisponibles'
        ) + ['meses' => $this->meses]);
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
