<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Alumno, Maestro, Curso, Materia, Inscripcion, Nota};
use App\Services\{ConsultaSolicitudesInscripcion, SugerenciasFiltros};
use Illuminate\Http\Request;

class SugerenciasFiltroController extends Controller
{
    public function __invoke(Request $request, string $modulo)
    {
        $f = $request->validate(['q' => 'required|string|min:2|max:100', 'curso' => 'nullable|string|max:150',
            'curso_id' => 'nullable|integer|min:1', 'anio' => 'nullable|integer|min:1900|max:2100',
            'turno' => 'nullable|string|max:50', 'estado' => 'nullable|in:todos,pendiente,aprobada,rechazada,inactiva,otros']);
        $persona = in_array($modulo, ['alumnos', 'maestros', 'solicitudes', 'notas-alumnos', 'boletas-alumnos'], true);
        $query = match ($modulo) {
            'alumnos', 'solicitudes', 'notas-alumnos', 'boletas-alumnos' => Alumno::query(),
            'maestros' => Maestro::query(),
            'cursos', 'notas-cursos', 'boletas-cursos' => Curso::query(),
            'materias' => Materia::query(),
            default => abort(404),
        };
        if ($modulo === 'solicitudes') {
            $query->whereIn('id', app(ConsultaSolicitudesInscripcion::class)->consulta($f)->withoutEagerLoads()->reorder()->select('alumno_id'));
        }
        if ($modulo === 'alumnos' && (!empty($f['curso']) || !empty($f['anio']))) {
            $query->whereHas('inscripciones', fn ($i) => $i->aprobada()
                ->where('inscripciones.id', Inscripcion::select('id')->whereColumn('alumno_id', 'alumnos.id')->aprobada()->orderBy('id')->limit(1))
                ->whereHas('curso', fn ($c) => $c
                ->when($f['curso'] ?? null, fn ($q, $v) => $q->whereRaw('LOWER(nombre) = ?', [mb_strtolower($v)]))
                ->when($f['anio'] ?? null, fn ($q, $v) => $q->where('anio_lectivo', $v))));
        }
        if ($modulo === 'cursos' && !empty($f['turno'])) $query->whereRaw("LOWER(nivel) LIKE ? ESCAPE '!'", [$this->patron($f['turno'])]);
        if ($modulo === 'notas-cursos') $query->where('activo', 1);
        if ($modulo === 'boletas-cursos') $query->whereIn('id', Inscripcion::aprobada()->select('curso_id'));
        if ($modulo === 'notas-alumnos') $query->whereIn('id', Nota::whereHas('detalleCurso.curso', fn ($c) => $c->where('activo', 1)
            ->when($f['curso'] ?? null, fn ($q, $v) => $q->whereRaw("LOWER(nombre) LIKE ? ESCAPE '!'", [$this->patron($v)])))->select('alumno_id'));
        if ($modulo === 'boletas-alumnos') {
            // Sin contexto no se ofrecen alumnos de otros cursos.
            $query->whereIn('id', Inscripcion::aprobada()->where('curso_id', $f['curso_id'] ?? 0)->select('alumno_id'));
        }
        foreach (preg_split('/\s+/u', trim($f['q']), -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = $this->patron($termino);
            $query->where(function ($q) use ($patron, $persona, $modulo) {
                $q->whereRaw("LOWER(nombre) LIKE ? ESCAPE '!'", [$patron]);
                if ($persona) $q->orWhereRaw("LOWER(apellido) LIKE ? ESCAPE '!'", [$patron]);
                if (!str_contains($modulo, 'cursos')) $q->orWhereRaw("LOWER(codigo) LIKE ? ESCAPE '!'", [$patron]);
            });
        }
        if ($persona) return SugerenciasFiltros::personas($query);
        $esCurso = str_contains($modulo, 'cursos');
        $filas = $query->orderBy('nombre')->orderBy('id')->limit(8)->get($esCurso ? ['id', 'nombre', 'nivel', 'seccion', 'anio_lectivo'] : ['id', 'nombre', 'codigo']);
        return response()->json(['alumnos' => $filas->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre,
            'codigo' => $esCurso ? "$f->nivel · $f->seccion · $f->anio_lectivo" : $f->codigo])])
            ->header('Cache-Control', 'private, no-store');
    }

    private function patron(string $texto): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($texto)).'%';
    }
}
