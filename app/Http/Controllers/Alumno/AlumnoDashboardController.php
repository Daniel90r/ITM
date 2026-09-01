<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Nota;
use App\Models\Mensualidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AlumnoDashboardController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // PRIVADO: Obtener alumno autenticado
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    private function getAlumno()
    {
        $alumno = Alumno::where('user_id', Auth::id())->first();
        if (!$alumno) {
            abort(403, 'Acceso no autorizado para este rol.');
        }
        return $alumno;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    public function index()
    {
        $alumno = $this->getAlumno();

        $inscripcion = Inscripcion::with('curso')
            ->where('alumno_id', $alumno->id)
            ->where('estado', 'aprobada')
            ->first();

        $notas = collect();
        if ($inscripcion) {
            $notas = Nota::with(['detalleCurso.materia', 'detalleCurso.maestro'])
                ->where('alumno_id', $alumno->id)
                ->whereHas('detalleCurso', fn($q) => $q->where('curso_id', $inscripcion->curso_id))
                ->get();
        }

        $cursosDisponibles = collect();
        if (!$inscripcion) {
            $cursosDisponibles = Curso::where('activo', true)
                ->where('anio_lectivo', (int) now()->year)
                ->noFinalizado()
                ->get();
        }

        $promedio = $notas->whereNotNull('promedio')->avg('promedio');

        $mensualidades = Mensualidad::where('alumno_id', $alumno->id)
            ->orderBy('mes', 'asc')
            ->get();

        return view('alumno.dashboard', compact(
            'alumno', 'inscripcion', 'notas', 'cursosDisponibles', 'promedio', 'mensualidades'
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // INSCRIPCIÓN
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    public function inscripcion()
    {
        $alumno = $this->getAlumno();

        $inscripcion = Inscripcion::with('curso')
            ->where('alumno_id', $alumno->id)
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->latest()
            ->first();

        $cursosDisponibles = collect();
        if (!$inscripcion) {
            $cursosDisponibles = Curso::where('activo', true)
                ->where('anio_lectivo', (int) now()->year)
                ->noFinalizado()
                ->get();
        }

        return view('alumno.inscripcion', compact('alumno', 'inscripcion', 'cursosDisponibles'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // ENVIAR SOLICITUD CON FORMULARIO DIGITAL + FIRMA + PDF AUTOMÁTICO
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    public function enviarSolicitud(Request $request)
    {
        $alumno = $this->getAlumno();

        $esMayorDeEdad = $alumno->es_mayor_de_edad;

        $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'tomara_ingles' => 'sometimes|boolean',

            'alumno_dui' => [
                $esMayorDeEdad ? 'required' : 'nullable',
                'string',
                'regex:/^\d{8}-\d$/'
            ],


            'encargado_nombre' => [
                $esMayorDeEdad ? 'nullable' : 'required',
                'string',
                'max:255'
            ],


            'encargado_parentesco' => [
                $esMayorDeEdad ? 'nullable' : 'required',
                'string',
                'max:50'
            ],


            'encargado_dui' => [
                $esMayorDeEdad ? 'nullable' : 'required',
                'string',
                'regex:/^\d{8}-\d$/'
            ],


            'encargado_telefono' => 'nullable|string|max:20',


            'firma_alumno' => 'required|string',


            'firma_encargado' => [
                $esMayorDeEdad ? 'nullable' : 'required',
                'string'
            ],
        ]);

        if ($esMayorDeEdad) {
            $alumno->update([
                'dui' => $request->alumno_dui
            ]);
        }

        // Verificar que no tenga solicitud activa o pendiente
        $existente = Inscripcion::where('alumno_id', $alumno->id)
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->first();

        if ($existente) {
            return back()->with('error', 'Ya tienes una solicitud pendiente o inscripción activa.');
        }

        $curso = Curso::findOrFail($request->curso_id);

        if ($curso->estado_curso === 'FINALIZADO') {
            return back()->with('error', 'No es posible inscribirse en un curso finalizado.');
        }

        // Generar PDF con datos y firmas digitales
        $pdf = Pdf::loadView('alumno.pdf.solicitud-inscripcion', [
            'alumno'               => $alumno,
            'curso'                => $curso,
            'cursosDisponibles'    => collect([$curso]),
            'encargado_nombre'     => $request->encargado_nombre,
            'encargado_telefono'   => $request->encargado_telefono,
            'encargado_parentesco' => $request->encargado_parentesco,
            'encargado_dui'        => $request->encargado_dui,
            'firma_alumno'         => $request->firma_alumno,
            'firma_encargado'      => $request->firma_encargado,
            'fecha'                => now(),
        ])->setPaper('a4', 'portrait');

        // Guardar PDF en storage
        $filename = 'solicitudes/solicitud-' . $alumno->codigo . '-' . time() . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());

        // Crear inscripción en estado pendiente
        Inscripcion::create([
            'alumno_id'         => $alumno->id,
            'curso_id'          => $request->curso_id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa'            => false,
            'estado'            => 'pendiente',
            'documento_path'    => $filename,
            'tomara_ingles'     => $request->boolean('tomara_ingles'),
            'observacion'       => json_encode([
                'encargado_nombre'     => $request->encargado_nombre,
                'encargado_telefono'   => $request->encargado_telefono ?? '—',
                'encargado_parentesco' => $request->encargado_parentesco,
                'encargado_dui'        => $request->encargado_dui ?? '—',
            ]),
        ]);

        return back()->with('success', '¡Solicitud enviada correctamente! El administrador la revisará pronto.');
    }

    public function notas()
    {
        $alumno = $this->getAlumno();

        $inscripcion = Inscripcion::with('curso')
            ->where('alumno_id', $alumno->id)
            ->where('estado', 'aprobada')
            ->first();

        $notas = collect();
        if ($inscripcion) {
            $notas = Nota::with(['detalleCurso.materia', 'detalleCurso.maestro'])
                ->where('alumno_id', $alumno->id)
                ->whereHas('detalleCurso', fn($q) => $q->where('curso_id', $inscripcion->curso_id))
                ->get();
        }

        $promedio = $notas->whereNotNull('promedio')->avg('promedio');

        return view('alumno.notas', compact('alumno', 'inscripcion', 'notas', 'promedio'));
    }

    public function pagos()
    {
        $alumno = $this->getAlumno();

        $mensualidades = Mensualidad::where('alumno_id', $alumno->id)
            ->orderBy('mes', 'asc')
            ->get();

        return view('alumno.pagos', compact('alumno', 'mensualidades'));
    }

    public function inscribirse(Request $request)
    {
        $alumno = $this->getAlumno();

        if (Inscripcion::bloqueaNuevaSolicitud($alumno->id)) {
            return back()->with('error', 'Ya tienes una solicitud pendiente o una inscripción aprobada.');
        }

        $request->validate(['curso_id' => 'required|exists:cursos,id']);

        try {
            DB::transaction(function () use ($request, $alumno) {
                $curso = Curso::query()
                    ->lockForUpdate()
                    ->findOrFail($request->curso_id);

                if ($curso->estado_curso === 'FINALIZADO') {
                    throw ValidationException::withMessages([
                        'curso_id' => 'No es posible inscribirse en un curso finalizado.',
                    ]);
                }

                $inscritosActuales = Inscripcion::query()
                    ->where('curso_id', $curso->id)
                    ->where('estado', 'aprobada')
                    ->count();

                if ($inscritosActuales >= Curso::CUPOS_MAXIMO) {
                    throw ValidationException::withMessages([
                        'curso_id' => 'El curso ya alcanzó el límite de 20 alumnos.',
                    ]);
                }

                Inscripcion::create([
                    'alumno_id' => $alumno->id,
                    'curso_id' => $request->curso_id,
                    'fecha_inscripcion' => now()->toDateString(),
                    'activa' => true,
                    'estado' => 'aprobada',
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', '¡Inscripción realizada correctamente!');
    }

    public function descargarPdf()
    {
        $alumno            = $this->getAlumno();
        $cursosDisponibles = Curso::where('activo', true)->where('anio_lectivo', 2026)->noFinalizado()->get();

        $pdf = Pdf::loadView('alumno.pdf.solicitud-inscripcion', compact('alumno', 'cursosDisponibles'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('solicitud-inscripcion-' . $alumno->codigo . '.pdf');
    }

    public function subirDocumento(Request $request)
    {
        $alumno = $this->getAlumno();

        $request->validate([
            'curso_id'  => 'required|exists:cursos,id',
            'documento' => 'required|file|mimes:pdf|max:5120',
            'tomara_ingles' => 'sometimes|boolean',
        ]);

        $curso = Curso::findOrFail($request->curso_id);

        if ($curso->estado_curso === 'FINALIZADO') {
            return back()->with('error', 'No es posible inscribirse en un curso finalizado.');
        }

        $existente = Inscripcion::where('alumno_id', $alumno->id)
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->first();

        if ($existente) {
            return back()->with('error', 'Ya tienes una solicitud pendiente o inscripción activa.');
        }

        $path = $request->file('documento')->store('solicitudes', 'public');

        Inscripcion::create([
            'alumno_id'         => $alumno->id,
            'curso_id'          => $request->curso_id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa'            => false,
            'estado'            => 'pendiente',
            'documento_path'    => $path,
            'tomara_ingles'     => $request->boolean('tomara_ingles'),
        ]);

        return back()->with('success', 'Solicitud enviada correctamente. El administrador la revisará pronto.');
    }
}
