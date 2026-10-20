<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Limpieza irreversible de datos: elimina las materias 'Lenguaje', 'Matemática' y 'Matematica'
     * junto con sus dependencias directas e indirectas, incluida la boleta ID 1 que contiene
     * exclusivamente esos detalles. No restaura datos inventados en down().
     */
    public function up(): void
    {
        // 1. Localizar materias objetivo por nombre normalizado
        $nombresObjetivo = ['lenguaje', 'matemática', 'matematica'];
        $materias = DB::table('materias')
            ->where(function ($query) use ($nombresObjetivo) {
                foreach ($nombresObjetivo as $nombre) {
                    $query->orWhereRaw('LOWER(TRIM(nombre)) = ?', [$nombre]);
                }
            })
            ->get(['id', 'nombre', 'codigo']);

        if ($materias->isEmpty()) {
            return;
        }

        $materiaIds = $materias->pluck('id')->all();

        // 2. Capturar IDs dependientes antes de borrar
        $detalleCursoIds = DB::table('detalle_cursos')
            ->whereIn('materia_id', $materiaIds)
            ->pluck('id')
            ->all();

        $notaIds = DB::table('notas')
            ->whereIn('detalle_curso_id', $detalleCursoIds)
            ->pluck('id')
            ->all();

        $asistenciaIds = DB::table('asistencias')
            ->whereIn('detalle_curso_id', $detalleCursoIds)
            ->pluck('id')
            ->all();

        $asignacionIds = DB::table('asignaciones_docentes')
            ->whereIn('detalle_curso_id', $detalleCursoIds)
            ->pluck('id')
            ->all();

        $alumnoMateriaIds = DB::table('alumno_materia')
            ->whereIn('materia_id', $materiaIds)
            ->pluck('id')
            ->all();

        $detalleBoletaIds = DB::table('detalles_boletas')
            ->whereIn('materia_id', $materiaIds)
            ->pluck('id')
            ->all();

        // 3. Identificar boletas que quedarían vacías
        $boletaIdsConDetallesObjetivo = DB::table('detalles_boletas')
            ->whereIn('materia_id', $materiaIds)
            ->pluck('boleta_id')
            ->unique()
            ->all();

        $boletasAVaciar = [];
        foreach ($boletaIdsConDetallesObjetivo as $boletaId) {
            $totalDetalles = DB::table('detalles_boletas')
                ->where('boleta_id', $boletaId)
                ->count();
            $detallesObjetivo = DB::table('detalles_boletas')
                ->where('boleta_id', $boletaId)
                ->whereIn('materia_id', $materiaIds)
                ->count();
            if ($totalDetalles === $detallesObjetivo) {
                $boletasAVaciar[] = $boletaId;
            }
        }

        // 4. Verificar condiciones de seguridad: no eliminar cabeceras no autorizadas
        $boletasNoAutorizadas = array_diff($boletaIdsConDetallesObjetivo, $boletasAVaciar);
        if (!empty($boletasNoAutorizadas)) {
            throw new \RuntimeException(
                'Se detectaron boletas que quedarían parcialmente vacías y no están autorizadas para eliminación: ' .
                implode(', ', $boletasNoAutorizadas) . '. Deteniendo migración.'
            );
        }

        // 5. Eliminar en orden seguro respetando FKs
        DB::transaction(function () use (
            $detalleBoletaIds,
            $boletasAVaciar,
            $notaIds,
            $asistenciaIds,
            $asignacionIds,
            $alumnoMateriaIds,
            $detalleCursoIds,
            $materiaIds
        ) {
            // 5.1 Detalles de boletas
            if (!empty($detalleBoletaIds)) {
                DB::table('detalles_boletas')->whereIn('id', $detalleBoletaIds)->delete();
            }

            // 5.2 Cabeceras de boletas que quedan vacías (solo las autorizadas)
            if (!empty($boletasAVaciar)) {
                DB::table('boletas')->whereIn('id', $boletasAVaciar)->delete();
            }

            // 5.3 Notas
            if (!empty($notaIds)) {
                DB::table('notas')->whereIn('id', $notaIds)->delete();
            }

            // 5.4 Asistencias
            if (!empty($asistenciaIds)) {
                DB::table('asistencias')->whereIn('id', $asistenciaIds)->delete();
            }

            // 5.5 Asignaciones docentes
            if (!empty($asignacionIds)) {
                DB::table('asignaciones_docentes')->whereIn('id', $asignacionIds)->delete();
            }

            // 5.6 Alumno_materia
            if (!empty($alumnoMateriaIds)) {
                DB::table('alumno_materia')->whereIn('id', $alumnoMateriaIds)->delete();
            }

            // 5.7 Detalle_cursos
            if (!empty($detalleCursoIds)) {
                DB::table('detalle_cursos')->whereIn('id', $detalleCursoIds)->delete();
            }

            // 5.8 Materias objetivo
            if (!empty($materiaIds)) {
                DB::table('materias')->whereIn('id', $materiaIds)->delete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * No restaura datos: es una limpieza irreversible. down() solo informa.
     */
    public function down(): void
    {
        // No-op: esta migración es una limpieza irreversible de datos.
        // Para revertir, restaure desde el respaldo: itm_before_remove_lenguaje_matematica_20261013_045500.sql
    }
};