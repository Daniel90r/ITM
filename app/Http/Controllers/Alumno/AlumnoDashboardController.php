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
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            $cursosDisponibles = Curso::proximo()
                ->where('anio_lectivo', (int) now()->year)
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

        if (! $inscripcion) {
            $inscripcion = Inscripcion::with('curso')
                ->where('alumno_id', $alumno->id)
                ->where('estado', 'rechazada')
                ->latest()
                ->first();
        }

        $cursosDisponibles = collect();
        if (! $inscripcion || $inscripcion->estado === 'rechazada') {
            $cursosDisponibles = Curso::proximo()
                ->where('anio_lectivo', (int) now()->year)
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

        // Comprobación rápida ANTES de generar el PDF: si ya no tiene sentido
        // seguir, no se genera el documento ni se deja un archivo huérfano en
        // storage. La verificación autoritativa se repite dentro de la
        // transacción de crearSolicitudPendiente().
        $existente = Inscripcion::where('alumno_id', $alumno->id)
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->first();

        if ($existente) {
            return back()->with('error', 'Ya tienes una solicitud pendiente o inscripción activa.');
        }

        $curso = Curso::findOrFail($request->curso_id);

        if (! $curso->admiteInscripcionPorEstado()) {
            return back()->with('error', 'Solo es posible inscribirse en cursos con estado Próximo.');
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

        // Guardar PDF en storage.
        //
        // El identificador es un UUID, no time(): dos peticiones dentro del
        // mismo segundo generaban el MISMO nombre y la segunda sobrescribía el
        // PDF de la primera, perdiendo el documento ya generado.
        $filename = 'solicitudes/solicitud-' . $alumno->codigo . '-' . Str::uuid() . '.pdf';

        if (! Storage::disk('public')->put($filename, $pdf->output())) {
            return back()->with('error', 'No se pudo guardar el documento de la solicitud. Inténtalo de nuevo.');
        }

        // Crear la SOLICITUD en estado pendiente. Mismo núcleo que usa
        // inscribirse(): el alumno solicita y el administrador aprueba.
        //
        // Si el registro falla (por ejemplo porque otra petición del mismo alumno
        // se adelantó y ya dejó una solicitud), el PDF que acabamos de generar se
        // borra para no dejar archivos huérfanos. Solo se toca ESTE archivo: los
        // de solicitudes anteriores no se ven afectados porque cada uno lleva un
        // UUID propio.
        try {
            $resultado = $this->crearSolicitudPendiente($alumno->id, (int) $curso->id, [
                'documento_path'  => $filename,
                'tomara_ingles'   => $request->boolean('tomara_ingles'),
                'observacion'     => json_encode([
                    'encargado_nombre'     => $request->encargado_nombre,
                    'encargado_telefono'   => $request->encargado_telefono ?? '—',
                    'encargado_parentesco' => $request->encargado_parentesco,
                    'encargado_dui'        => $request->encargado_dui ?? '—',
                ]),
            ]);
        } catch (Throwable $e) {
            // Solo se elimina el archivo de ESTA petición.
            Storage::disk('public')->delete($filename);

            // Se propaga tal cual: una ValidationException la traduce Laravel al
            // formulario y cualquier otro fallo sigue siendo un error visible, no
            // un mensaje amable que oculte un problema real.
            throw $e;
        }

        // Si fue un reintento de solicitud rechazada, eliminar el PDF anterior
        // solo después de que la transacción haya confirmado correctamente.
        if ($resultado['reutilizada'] && $resultado['documento_anterior'] && $resultado['documento_anterior'] !== $filename) {
            Storage::disk('public')->delete($resultado['documento_anterior']);
        }

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

        $request->validate(['curso_id' => 'required|exists:cursos,id']);

        // Un alumno NUNCA se auto-aprueba. Este endpoint existe por
        // compatibilidad con clientes que lo invocan, pero su resultado es
        // exactamente el mismo que el del formulario: una SOLICITUD pendiente.
        // La única forma de obtener una inscripción aprobada es que un
        // administrador la apruebe (ver InscripcionController@aprobar).
        //
        // Comprobación rápida con el mensaje que este endpoint siempre mostró.
        // La verificación autoritativa se repite dentro de la transacción.
        if (Inscripcion::bloqueaNuevaSolicitud($alumno->id)) {
            return back()->with('error', 'Ya tienes una solicitud pendiente o una inscripción aprobada.');
        }

        try {
            $this->crearSolicitudPendiente($alumno->id, (int) $request->curso_id, verificarCupo: true);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', '¡Solicitud enviada! Quedará pendiente de aprobación por la administración.');
    }

/**
     * Núcleo compartido por todos los caminos de inscripción del alumno.
     *
     * Deja SIEMPRE una inscripción en estado 'pendiente' e inactiva: el alumno
     * solicita, el administrador aprueba. Ningún endpoint del portal del alumno
     * puede producir una inscripción 'aprobada'.
     *
     * ORDEN DE BLOQUEOS: primero la fila del ALUMNO, después la del CURSO.
     * Motivo: la regla que hay que hacer cumplir es "un alumno, como mucho una
     * solicitud pendiente o una inscripción aprobada", y esa regla es por
     * alumno, no por curso. Tomando el alumno primero, dos peticiones
     * simultáneas del mismo alumno quedan serializadas aunque apuntan a cursos
     * distintos, y la comprobación posterior ya ve el dato recién confirmado.
     * Ese orden no puede formar un ciclo de interbloqueo: los procedimientos de
     * administración (InscripcionController@store y @aprobar) solo bloquean el
     * curso y nunca el alumno, y MaestroController solo toca maestros,
     * materias y detalle_cursos.
     *
     * @return array{inscripcion: Inscripcion, reutilizada: bool, documento_anterior: string|null}
     */
    private function crearSolicitudPendiente(int $alumnoId, int $cursoId, array $atributos = [], bool $verificarCupo = false): array
    {
        try {
            return DB::transaction(function () use ($alumnoId, $cursoId, $atributos, $verificarCupo) {
                // 1. Bloqueo del alumno. En InnoDB, SELECT ... FOR UPDATE es una
                //    lectura "actual": ve la última fila confirmada, no una
                //    instantánea obsoleta como la de REPEATABLE READ.
                $alumno = Alumno::query()
                    ->lockForUpdate()
                    ->findOrFail($alumnoId);

                // 2. Bloqueo del curso (orden global alumno -> curso).
                $curso = Curso::query()
                    ->lockForUpdate()
                    ->findOrFail($cursoId);

                // 3. Buscar inscripción existente para este alumno+curso.
                //     Debe hacerse DESPUÉS de los locks de alumno y curso para
                //     no alterar el orden de bloqueo ya validado.
                $inscripcionExistente = Inscripcion::where('alumno_id', $alumnoId)
                    ->where('curso_id', $cursoId)
                    ->lockForUpdate()
                    ->first();

                // 4. Validaciones — SIEMPRE antes de cualquier transición.
                //    Una fila rechazada NO puede reabrirse si el alumno tiene
                //    otra solicitud pendiente o aprobada, o si el curso ya no
                //    admite inscripción.
                if (Inscripcion::bloqueaNuevaSolicitud($alumno->id)) {
                    throw ValidationException::withMessages([
                        'curso_id' => 'Ya tienes una solicitud pendiente o inscripción aprobada.',
                    ]);
                }

                if (! $curso->admiteInscripcionPorEstado()) {
                    throw ValidationException::withMessages([
                        'curso_id' => 'Solo es posible inscribirse en cursos con estado Próximo.',
                    ]);
                }

                // Aviso temprano de cupo. NO es lo que autoriza la inscripción:
                // el cupo se vuelve a verificar al aprobar, que es el momento en
                // que realmente se consume.
                if ($verificarCupo && ! $curso->admiteNuevaInscripcion()) {
                    throw ValidationException::withMessages([
                        'curso_id' => 'El curso ya alcanzó el límite de ' . Curso::CUPOS_MAXIMO . ' alumnos.',
                    ]);
                }

                // 5. Transición o creación.
                if ($inscripcionExistente && $inscripcionExistente->estado === 'rechazada') {
                    $documentoAnterior = $inscripcionExistente->documento_path;

                    $inscripcionExistente->update(array_merge($atributos, [
                        'estado' => 'pendiente',
                        'activa' => false,
                        'motivo_rechazo' => null,
                        'fecha_inscripcion' => now()->toDateString(),
                    ]));

                    return [
                        'inscripcion' => $inscripcionExistente,
                        'reutilizada' => true,
                        'documento_anterior' => $documentoAnterior,
                    ];
                }

                if (! $inscripcionExistente) {
                    $nueva = Inscripcion::create(array_merge($atributos, [
                        'alumno_id'         => $alumno->id,
                        'curso_id'          => $curso->id,
                        'fecha_inscripcion' => now()->toDateString(),
                        'activa'            => false,
                        'estado'            => 'pendiente',
                    ]));

                    return [
                        'inscripcion' => $nueva,
                        'reutilizada' => false,
                        'documento_anterior' => null,
                    ];
                }

                throw ValidationException::withMessages([
                    'curso_id' => 'Ya existe una solicitud para este curso.',
                ]);
            });
        } catch (QueryException $e) {
            // Solo se traduce la violación conocida de la restricción UNIQUE de
            // inscripciones. Cualquier otro error de base de datos se relanza
            // para no convertir un fallo real en un mensaje engañoso.
            if ($this->esViolacionUnicaDeInscripciones($e)) {
                throw ValidationException::withMessages([
                    'curso_id' => 'Ya tienes una solicitud o inscripción para este curso.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * ¿Esta excepción es exactamente el UNIQUE de inscripciones?
     *
     * MySQL devuelve SQLSTATE 23000 con código 1062 (Duplicate entry) y SQLite
     * el mismo SQLSTATE con el código 19. Se exige que coincidan los dos para no
     * capturar otros errores de integridad ni fallos de conexión.
     */
    private function esViolacionUnicaDeInscripciones(QueryException $e): bool
    {
        if ((string) $e->getCode() !== '23000') {
            return false;
        }

        $codigoMotor = $e->errorInfo[1] ?? null;

        if (! in_array($codigoMotor, [1062, 19], true)) {
            return false;
        }

        return str_contains(strtolower($e->getMessage()), 'inscripciones');
    }

    public function descargarPdf()
    {
        $alumno            = $this->getAlumno();
        $cursosDisponibles = Curso::proximo()
            ->where('anio_lectivo', (int) now()->year)
            ->get();

        $pdf = Pdf::loadView('alumno.pdf.solicitud-inscripcion', compact('alumno', 'cursosDisponibles'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('solicitud-inscripcion-' . $alumno->codigo . '.pdf');
    }

    public function subirDocumento(Request $request)
    {
        $alumno = $this->getAlumno();

        $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'documento' => 'required|file|mimes:pdf|max:5120',
            'tomara_ingles' => 'sometimes|boolean',
        ]);

        $curso = Curso::findOrFail($request->curso_id);

        if (! $curso->admiteInscripcionPorEstado()) {
            return back()->with('error', 'Solo es posible inscribirse en cursos con estado Próximo.');
        }

        $existente = Inscripcion::where('alumno_id', $alumno->id)
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->first();

        if ($existente) {
            return back()->with('error', 'Ya tienes una solicitud pendiente o inscripción activa.');
        }

        $path = $request->file('documento')->store('solicitudes', 'public');

        try {
            $resultado = $this->crearSolicitudPendiente($alumno->id, (int) $request->curso_id, [
                'documento_path' => $path,
                'tomara_ingles' => $request->boolean('tomara_ingles'),
            ]);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($resultado['reutilizada'] && $resultado['documento_anterior'] && $resultado['documento_anterior'] !== $path) {
            Storage::disk('public')->delete($resultado['documento_anterior']);
        }

        return back()->with('success', 'Solicitud enviada correctamente. El administrador la revisará pronto.');
    }
}
