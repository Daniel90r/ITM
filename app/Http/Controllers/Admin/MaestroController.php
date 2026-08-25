<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMaestroRequest;
use App\Http\Requests\Admin\UpdateMaestroRequest;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MaestroController extends Controller
{
    public function index()
    {
        $maestros = Maestro::with('detalleCursos.materia', 'detalleCursos.curso', 'user')->get();

        return view('admin.maestros', compact('maestros'));
    }

    public function show($id)
    {
        $maestro = Maestro::with('detalleCursos.materia', 'detalleCursos.curso', 'user')->findOrFail($id);
        $maestros = Maestro::with('detalleCursos.materia', 'user')->get();
        $cursos = Curso::where('activo', 1)->get();
        $materias = Materia::orderBy('nombre')->get();
        $totalMaestros = Maestro::count();

        return view('admin.maestro-detalle', compact('maestro', 'maestros', 'cursos', 'materias', 'totalMaestros'));
    }

    private function generarCodigoMaestro(string $nombre, string $apellido): string
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

        $max = Maestro::where('codigo', 'like', $iniciales.'%')->max('codigo');
        $numero = 1;
        if ($max) {
            $numStr = preg_replace('/[^0-9]/', '', $max);
            $numero = (int) $numStr + 1;
        }

        return $iniciales.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    public function siguienteCodigoMaestro(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
        ]);

        $codigo = $this->generarCodigoMaestro($request->nombre, $request->apellido);

        return response()->json([
            'codigo' => $codigo,
            'email' => strtolower($codigo).'@itm.edu.sv',
        ]);
    }

    public function store(StoreMaestroRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $codigo = $this->generarCodigoMaestro($validated['nombre'], $validated['apellido']);

            $user = User::create([
                'name' => $validated['nombre'].' '.$validated['apellido'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'docente',
            ]);

            Maestro::create([
                'user_id' => $user->id,
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'telefono' => $validated['telefono'],
                'dui' => $validated['dui'],
                'codigo' => $codigo,
                'activo' => true,
            ]);
        });

        return redirect()->route('admin.maestros')->with('success', 'Maestro registrado correctamente.');
    }

    public function asignar(Request $request, $id)
    {
        DetalleCurso::firstOrCreate([
            'maestro_id' => $id,
            'curso_id' => $request->curso_id,
            'materia_id' => $request->materia_id,
        ]);

        return redirect()->route('admin.maestros.show', $id)->with('success', 'Asignación realizada correctamente.');
    }

    public function desasignar($maestro_id, $detalle_id)
    {
        return DB::transaction(function () use ($maestro_id, $detalle_id) {
            $detalle = DetalleCurso::where('id', $detalle_id)
                ->where('maestro_id', $maestro_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($detalle->notas()->exists()) {
                return redirect()->route('admin.maestros.show', $maestro_id)
                    ->with('error', 'No se puede eliminar la asignación porque contiene notas registradas.');
            }

            $detalle->delete();

            return redirect()->route('admin.maestros.show', $maestro_id)
                ->with('success', 'Asignación eliminada correctamente.');
        });
    }

    public function update(UpdateMaestroRequest $request, $id)
    {
        $maestro = Maestro::findOrFail($id);
        $user = $maestro->user;
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $user, $maestro, $request) {
            $user->update([
                'name' => $validated['nombre'].' '.$validated['apellido'],
                'email' => $validated['email'],
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            $maestro->update([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'telefono' => $validated['telefono'],
                'dui' => $validated['dui'],
            ]);
        });

        return redirect()->route('admin.maestros')->with('success', 'Maestro actualizado correctamente.');
    }
}
