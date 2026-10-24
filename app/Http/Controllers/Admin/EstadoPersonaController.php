<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\CambioEstadoPersona;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Services\CambiarEstadoPersona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstadoPersonaController extends Controller
{
    private function persona(string $tipo, int $id)
    {
        abort_unless(in_array($tipo, ['alumnos', 'maestros'], true), 404);

        return ($tipo === 'alumnos' ? Alumno::class : Maestro::class)::findOrFail($id);
    }

    public function show(string $tipo, int $id)
    {
        $persona = $this->persona($tipo, $id);
        $revision = app(CambiarEstadoPersona::class)->revision($persona);
        $historial = CambioEstadoPersona::where($tipo === 'alumnos' ? 'alumno_id' : 'maestro_id', $id)->with('administrador')->orderByDesc('id')->paginate(20);
        $asignaciones = $tipo === 'maestros' ? AsignacionDocente::where('maestro_id', $id)->whereIn('estado', ['activa', 'programada'])->with(['detalleCurso.curso', 'detalleCurso.materia'])->get() : collect();
        $cierres = $tipo === 'maestros' ? DB::table('cierres_asignaciones_programadas as c')->join('asignaciones_docentes as a', 'a.id', '=', 'c.asignacion_id')->join('users as u', 'u.id', '=', 'c.administrador_id')->where('a.maestro_id', $id)->select('c.*', 'u.name as responsable')->orderByDesc('c.id')->paginate(10, ['*'], 'cierres_page') : null;

        return response()->view('admin.estado-persona', compact('tipo', 'persona', 'historial', 'asignaciones', 'cierres', 'revision'))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $tipo, int $id, CambiarEstadoPersona $service)
    {
        $data = $request->validate(['activo' => 'required|boolean', 'esperado' => 'required|boolean', 'motivo' => 'required|string|min:10|max:500', 'revision' => 'required|integer|min:0']);
        $changed = $service->ejecutar($tipo, $id, (bool) $data['activo'], (bool) $data['esperado'], $data['motivo'], $request->user(), (int) $data['revision']);

        return back()->with('success', $changed ? 'Estado actualizado. El historial se conserva.' : 'La persona ya tiene ese estado. No se realizaron cambios.');
    }

    public function finalizarProgramada(Request $request, int $id)
    {
        $data = $request->validate(['motivo' => 'required|string|min:10|max:500']);
        $ref = AsignacionDocente::findOrFail($id);
        DB::transaction(function () use ($ref, $data, $request) {
            Maestro::whereKey($ref->maestro_id)->lockForUpdate()->firstOrFail();
            $detalle = DetalleCurso::whereKey($ref->detalle_curso_id)->lockForUpdate()->firstOrFail();
            $asignacion = AsignacionDocente::whereKey($ref->id)->lockForUpdate()->firstOrFail();
            if ($asignacion->maestro_id !== $ref->maestro_id || $asignacion->detalle_curso_id !== $ref->detalle_curso_id || $asignacion->estado !== 'programada') {
                throw ValidationException::withMessages(['estado' => 'La asignación ya no está programada. Actualiza la página.']);
            }
            $asignacion->update(['estado' => 'finalizada']);
            DB::table('cierres_asignaciones_programadas')->insert(['asignacion_id' => $asignacion->id, 'administrador_id' => $request->user()->id, 'motivo' => $data['motivo'], 'registrado_at' => now()]);
            // No class took place: retain the original dates, including an unknown end.
            if ((int) $detalle->maestro_id === (int) $asignacion->maestro_id && ! AsignacionDocente::where('detalle_curso_id', $detalle->id)->where('maestro_id', $asignacion->maestro_id)->whereIn('estado', ['activa', 'programada'])->lockForUpdate()->first(['id'])) {
                $detalle->update(['maestro_id' => null]);
            }
        }, 3);

        return back()->with('success', 'Asignación programada finalizada sin eliminar su historial ni modificar fechas académicas.');
    }
}
