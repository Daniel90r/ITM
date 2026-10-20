<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Encargado;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Mensualidad;
use App\Models\Nota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminDashboardController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // ADMINISTRADOR: DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    public function index()
    {
        // ── Tarjetas de estadísticas ─────────────────────────────────────────
        $totalAlumnos = Inscripcion::aprobada()
            ->where('activa', true)
            ->distinct()
            ->count('alumno_id');
        $totalMaestros = Maestro::where('activo', 1)->count();
        $totalMaterias = Materia::where('activa', 1)->count();

        // ── Mensualidades: pendientes y % al día (solo año actual) ─────────
        $anioActual = (int) now()->year;
        $totalMensualidades = Mensualidad::where('anio', $anioActual)->count();
        $pagosPendientes = Mensualidad::where('estado', 'Pendiente')->where('anio', $anioActual)->count();

        $pagosAlDia = $totalMensualidades > 0
            ? (int) round((($totalMensualidades - $pagosPendientes) / $totalMensualidades) * 100)
            : 0;

        // ── Promedio general de notas ────────────────────────────────────────
        $promedioGeneral = Nota::whereNotNull('promedio')->avg('promedio');
        $promedioGeneral = $promedioGeneral !== null ? round($promedioGeneral, 1) : null;

        // ── Maestros activos con sus materias ────────────────────────────────
        $maestrosActivos = Maestro::where('activo', 1)
            ->with('detalleCursos.materia')
            ->orderBy('nombre')
            ->take(4)
            ->get();

        // ── Estado de mensualidades por mes ──────────────────────────────────
        // El accessor nombre_mes soporta mes guardado como número o como nombre
        $ordenMeses = [
            'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4,
            'Mayo' => 5, 'Junio' => 6, 'Julio' => 7, 'Agosto' => 8,
            'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12,
        ];

        $estadoMensualidades = Mensualidad::all()
            ->groupBy('nombre_mes')
            ->map(function ($grupo, $nombreMes) {
                $total = $grupo->count();
                $pagadas = $grupo->where('estado', 'Pagado')->count();

                $anio = null;
                if ($grupo->first()->fecha_vencimiento) {
                    try {
                        $anio = Carbon::parse($grupo->first()->fecha_vencimiento)->year;
                    } catch (\Exception $e) {
                        $anio = null;
                    }
                }

                return [
                    'mes' => $nombreMes,
                    'anio' => $anio ?? now()->year,
                    'total' => $total,
                    'pagadas' => $pagadas,
                    'completo' => $total > 0 && $pagadas === $total,
                ];
            })
            ->sortBy(fn ($m) => $ordenMeses[$m['mes']] ?? 99)
            ->values();

        return view('admin.dashboard', compact(
            'totalAlumnos',
            'totalMaestros',
            'totalMaterias',
            'pagosPendientes',
            'pagosAlDia',
            'promedioGeneral',
            'maestrosActivos',
            'estadoMensualidades'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ADMINISTRADOR: Alumnos
    // ─────────────────────────────────────────────────────────────────────────────────────────
    public function alumnos()
    {
        $alumnos = Alumno::with([
            'inscripciones' => fn ($q) => $q->aprobada()->with('curso'),
            'user',
        ])->orderByDesc('created_at')->orderByDesc('id')->get();

        $cursos = Curso::where('activo', 1)->orderBy('nombre')->get();
        $totalAlumnos = $alumnos->count();
        $activos = $alumnos->where('activo', 1)->count();
        $inactivos = $alumnos->where('activo', 0)->count();

        return view('admin.alumnos', compact('alumnos', 'cursos', 'totalAlumnos', 'activos', 'inactivos'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // CÓDIGO AUTOMÁTICO POR INICIALES
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    private function generarCodigoAlumno(string $nombre, string $apellido): string
    {
        $limpiar = function (string $texto): string {
            $texto = trim($texto);
            $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
            $texto = preg_replace('/[^a-zA-Z\s]/', '', $texto);

            return $texto;
        };

        $nombreLimpio = $limpiar($nombre);
        $apellidoLimpio = $limpiar($apellido);

        $palabrasNombre = array_values(array_filter(explode(' ', $nombreLimpio)));
        $palabrasApellido = array_values(array_filter(explode(' ', $apellidoLimpio)));

        $iniciales = '';
        foreach (array_slice($palabrasNombre, 0, 2) as $p) {
            $iniciales .= strtoupper($p[0] ?? '');
        }
        foreach (array_slice($palabrasApellido, 0, 2) as $p) {
            $iniciales .= strtoupper($p[0] ?? '');
        }

        $max = Alumno::where('codigo', 'like', $iniciales.'%')->max('codigo');
        $numero = 1;
        if ($max) {
            $numStr = preg_replace('/[^0-9]/', '', $max);
            $numero = (int) $numStr + 1;
        }

        return $iniciales.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    public function siguienteCodigo(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
        ]);

        $codigo = $this->generarCodigoAlumno($request->nombre, $request->apellido);

        return response()->json([
            'codigo' => $codigo,
        ]);
    }

    public function storeAlumno(Request $request)
    {
        // Determinar mayoría de edad a partir de la fecha de nacimiento (cálculo en servidor,
        // nunca confiar solo en lo que muestre/oculte el frontend)
        $esMayorDeEdad = false;
        $edad = null;
        if ($request->filled('fecha_nacimiento')) {
            try {
                $fechaNac = Carbon::parse($request->fecha_nacimiento);
                $edad = $fechaNac->age;
                $esMayorDeEdad = $edad >= 18;
            } catch (\Exception $e) {
                $esMayorDeEdad = false;
                $edad = null;
            }
        }

        if ($edad !== null && $edad < 14) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha_nacimiento' => 'El alumno debe tener al menos 14 años para poder registrarse.',
            ]);
        }

        $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'sexo' => 'nullable|in:M,F',
            'telefono' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'direccion' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|min:8|confirmed',

            'dui' => [
                $esMayorDeEdad ? 'required' : 'nullable',
                'string',
                'regex:/^\d{8}-\d$/',
                'unique:alumnos,dui',
            ],

            'encargado_nombre' => [$esMayorDeEdad ? 'nullable' : 'required', 'string', 'max:100'],
            'encargado_apellido' => [$esMayorDeEdad ? 'nullable' : 'required', 'string', 'max:100'],
            'encargado_parentesco' => [$esMayorDeEdad ? 'nullable' : 'required', 'string', 'max:50'],
            'encargado_dui' => [$esMayorDeEdad ? 'nullable' : 'required', 'string', 'regex:/^\d{8}-\d$/'],
            'encargado_telefono' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'encargado_email' => 'nullable|email|max:255',
        ], [
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria para determinar si el alumno es mayor o menor de edad.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'dui.required' => 'El DUI del alumno es obligatorio cuando es mayor de edad.',
            'dui.regex' => 'El DUI del alumno debe tener el formato 01234567-8.',
            'dui.unique' => 'Ya existe un alumno registrado con ese DUI.',
            'encargado_nombre.required' => 'El nombre del encargado es obligatorio para alumnos menores de edad.',
            'encargado_apellido.required' => 'El apellido del encargado es obligatorio para alumnos menores de edad.',
            'encargado_parentesco.required' => 'El parentesco del encargado es obligatorio para alumnos menores de edad.',
            'encargado_dui.required' => 'El DUI del encargado es obligatorio para alumnos menores de edad.',
            'encargado_dui.regex' => 'El DUI del encargado debe tener el formato 01234567-8.',
            'telefono.regex' => 'El teléfono debe tener el formato 7777-8888.',
            'encargado_telefono.regex' => 'El teléfono del encargado debe tener el formato 7777-8888.',
        ]);

        DB::transaction(function () use ($request, $esMayorDeEdad) {

            $codigo = $this->generarCodigoAlumno($request->nombre, $request->apellido);
            $email = trim(strtolower($request->email));

            $user = User::create([
                'name' => $request->nombre.' '.$request->apellido,
                'email' => $email,
                'password' => Hash::make($request->password),
                'role' => 'alumno',
            ]);

            $alumno = Alumno::create([
                'user_id' => $user->id,
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'codigo' => $codigo,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'dui' => $esMayorDeEdad ? $request->dui : null,
                'genero' => $request->sexo,
                'telefono' => $request->telefono,
                'direccion' => $request->direccion,
                'activo' => 1,
            ]);

            // Menor de edad: crear (o reutilizar) encargado y vincularlo
            if (! $esMayorDeEdad) {

                // Si ya existe un encargado con ese DUI (ej. hermano del alumno), se reutiliza
                $encargado = Encargado::where('dui', $request->encargado_dui)->first();

                if (! $encargado) {
                    $encargado = Encargado::create([
                        'nombre' => $request->encargado_nombre,
                        'apellido' => $request->encargado_apellido,
                        'telefono' => $request->encargado_telefono,
                        'dui' => $request->encargado_dui,
                        'email' => $request->encargado_email,
                        'activo' => 1,
                    ]);
                }

                $alumno->encargados()->attach($encargado->id, [
                    'parentesco' => $request->encargado_parentesco,
                ]);
            }
        });

        return redirect()->route('admin.alumnos')->with('success', 'Alumno registrado correctamente.');
    }

    public function updateAlumno(Request $request, $id)
    {
        $alumno = Alumno::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'telefono' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'email' => 'required|email|max:255|unique:users,email,'.$alumno->user_id,
            'password' => 'nullable|min:8|confirmed',
        ], [
            'telefono.regex' => 'El teléfono debe tener el formato 7777-8888.',
        ]);

        DB::transaction(function () use ($request, $alumno) {

            // Actualizar datos personales del alumno
            $alumno->update([
                'nombre'    => $request->nombre,
                'apellido'  => $request->apellido,
                'telefono'  => $request->telefono,
            ]);

            // Actualizar email en users (normalizado a minúsculas, sin espacios)
            $alumno->user->update([
                'name'  => $request->nombre.' '.$request->apellido,
                'email' => trim(strtolower($request->email)),
            ]);

            // Actualizar contraseña solo si se ingresó
            if ($request->filled('password')) {
                $alumno->user->update([
                    'password' => Hash::make($request->password),
                ]);
            }

            // No permitir modificar la inscripción desde la edición de datos personales
            // La asignación a curso debe realizarse únicamente mediante aprobación de solicitud
        });

        return redirect()->route('admin.alumnos')->with('success', 'Alumno actualizado correctamente.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ADMINISTRADOR: Maestros
    // ─────────────────────────────────────────────────────────────────────────────────────────
    public function maestros()
    {
        $maestros = Maestro::with(['user', 'detalleCursos.materia'])->get();

        return view('admin.maestros', compact('maestros'));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ADMINISTRADOR: Materias
    // ─────────────────────────────────────────────────────────────────────────────────────────
    public function materias()
    {
        $materias = Materia::orderBy('nombre')->get();

        return view('admin.materias', compact('materias'));
    }

    public function storeMateria(Request $request)
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                $normalizado = $this->normalizarNombreMateria($value);
                if (Materia::whereRaw('UPPER(REPLACE(nombre, " ", "")) = ?', [strtoupper(str_replace(' ', '', $normalizado))])->exists()) {
                    $fail('Ya existe una materia con ese nombre.');
                }
            }],
        ]);

        $codigo = $this->generarCodigoMateria($request->nombre);

        Materia::create([
            'codigo' => $codigo,
            'nombre' => $request->nombre,
            'activa' => 1,
        ]);

        return redirect()->route('admin.materias')->with('success', 'Materia registrada correctamente.');
    }

    private function normalizarNombreMateria(string $nombre): string
    {
        $texto = trim($nombre);
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = preg_replace('/[^a-zA-Z\s]/', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        $stopWords = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'o', 'u', 'a', 'en', 'con', 'para', 'por', 'sin', 'sobre', 'entre', 'hacia', 'desde', 'hasta', 'mediante', 'según', 'durante', 'frente', 'bajo', 'tras', 'versus', 'via'];
        $palabras = array_values(array_filter(explode(' ', $texto), fn ($p) => $p !== '' && ! in_array(strtolower($p), $stopWords)));

        return implode(' ', $palabras);
    }

    private function generarCodigoMateria(string $nombre): string
    {
        $normalizado = $this->normalizarNombreMateria($nombre);
        $palabras = array_values(array_filter(explode(' ', $normalizado)));

        $iniciales = '';
        foreach ($palabras as $p) {
            if ($p !== '') {
                $iniciales .= strtoupper($p[0]);
            }
        }

        $candidato = $iniciales;
        if (! Materia::where('codigo', $candidato)->exists()) {
            return $candidato;
        }

        $palabrasUpper = array_map('strtoupper', $palabras);
        $numPalabras = count($palabrasUpper);

        for ($idxPalabra = $numPalabras - 1; $idxPalabra >= 0; $idxPalabra--) {
            $palabra = $palabrasUpper[$idxPalabra];
            $longitudPalabra = strlen($palabra);

            for ($posLetra = 1; $posLetra < $longitudPalabra; $posLetra++) {
                $prefijo = '';
                for ($i = 0; $i < $numPalabras; $i++) {
                    if ($i === $idxPalabra) {
                        $prefijo .= substr($palabrasUpper[$i], 0, $posLetra + 1);
                    } else {
                        $prefijo .= $palabrasUpper[$i][0];
                    }
                }

                if (! Materia::where('codigo', $prefijo)->exists()) {
                    return $prefijo;
                }
            }
        }

        $firma = strtoupper(str_replace(' ', '', $normalizado));
        for ($len = strlen($iniciales) + 1; $len <= strlen($firma); $len++) {
            $candidato = substr($firma, 0, $len);
            if (! Materia::where('codigo', $candidato)->exists()) {
                return $candidato;
            }
        }

        $hash = substr(strtoupper(hash('crc32', $firma)), 0, 3);
        $candidato = substr($firma, 0, min(5, strlen($firma))) . $hash;
        $contador = 1;
        while (Materia::where('codigo', $candidato)->exists()) {
            $candidato = substr($firma, 0, min(5, strlen($firma))) . $hash . $contador;
            $contador++;
        }

        return $candidato;
    }

    public function siguienteCodigoMateria(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
        ]);

        $codigo = $this->generarCodigoMateria($request->nombre);

        return response()->json([
            'codigo' => $codigo,
        ]);
    }

    public function toggleMateria(Materia $materia)
    {
        $materia->update(['activa' => ! $materia->activa]);

        return response()->json(['activa' => $materia->activa]);
    }
}
