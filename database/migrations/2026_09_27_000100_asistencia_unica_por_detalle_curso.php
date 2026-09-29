<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La asistencia debe ser POR MATERIA, no por curso completo.
 *
 * Antes: UNIQUE(curso_id, alumno_id, fecha)
 *   Imposible que dos materias del mismo curso registraran asistencia el mismo
 *   día para el mismo alumno: la segunda fila sobrescribía a la primera.
 *   Eso obligaba a tratar el curso como si perteneciera a un solo maestro.
 *
 * Después: UNIQUE(detalle_curso_id, alumno_id, fecha)
 *   detalle_cursos ya identifica de forma única Curso + Materia, y
 *   asignaciones_docentes indica qué maestro tiene esa materia actualmente.
 *
 *   Válido : (Word, alumno 1, 27/09/2026) y (Excel, alumno 1, 27/09/2026)
 *   Inválido: (Word, alumno 1, 27/09/2026) dos veces
 *
 * curso_id NO se elimina: se conserva por compatibilidad e historial.
 *
 * Nota sobre NULL: detalle_curso_id sigue siendo nullable para no romper
 * registros históricos, y su FK es ON DELETE SET NULL, así que puede quedar
 * NULL si se borra el detalle_curso de la materia. MySQL (y SQLite) no
 * evalúan la unicidad entre NULL, por lo que una fila sin detalle_curso_id no
 * colisiona con el índice nuevo.
 */
return new class extends Migration
{
    private const INDICE_ANTERIOR = 'asistencias_curso_id_alumno_id_fecha_unique';

    private const INDICE_NUEVO = 'asistencias_detalle_curso_alumno_fecha_unique';

    private const INDICE_FK_DETALLE = 'asistencias_detalle_curso_id_index';

    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            if (Schema::hasIndex('asistencias', self::INDICE_ANTERIOR)) {
                $table->dropUnique(self::INDICE_ANTERIOR);
            }

            if (! Schema::hasIndex('asistencias', self::INDICE_NUEVO)) {
                $table->unique(
                    ['detalle_curso_id', 'alumno_id', 'fecha'],
                    self::INDICE_NUEVO
                );
            }
        });
    }

    /**
     * Volver al esquema anterior es INCOMPATIBLE con los datos que el esquema
     * nuevo permite: dos materias del mismo curso pueden tener asistencia del
     * mismo alumno en la misma fecha, algo que UNIQUE(curso_id, alumno_id,
     * fecha) prohíbe.
     *
     * Este método NO deduplica ni borra asistencias: si el conflicto existe,
     * se detiene antes de tocar cualquier índice y explica qué hacer. Perder
     * historial académico para que una migración pase es peor que no revertir.
     */
    public function down(): void
    {
        $conflictos = $this->conflictosConElIndiceAnterior();

        if ($conflictos->isNotEmpty()) {
            $ejemplos = $conflictos->take(5)->map(fn ($c) => sprintf(
                'curso_id=%d, alumno_id=%d, fecha=%s (%d filas)',
                $c->curso_id,
                $c->alumno_id,
                $c->fecha,
                $c->total_asistencias
            ))->implode('; ');

            $sufijo = $conflictos->count() > 5
                ? sprintf(' y %d grupo(s) más', $conflictos->count() - 5)
                : '';

            throw new \RuntimeException(
                'No se puede revertir la unicidad de asistencias por materia: '
                . 'existen múltiples asistencias del mismo alumno, curso y fecha '
                . 'pertenecientes a materias distintas, que el índice anterior '
                . 'UNIQUE(curso_id, alumno_id, fecha) prohíbe. No se ha modificado '
                . 'ningún índice ni se ha eliminado información. Grupos afectados: '
                . $ejemplos . $sufijo
                . '. Resuelva esos registros antes de revertir.'
            );
        }

        Schema::table('asistencias', function (Blueprint $table) {
            // MySQL no permite eliminar el único índice que respalda la FK de
            // detalle_curso_id, así que se asegura uno plano antes de soltar
            // el único. Ese índice se conserva: es útil para consultar por
            // detalle y hace la migración idempotente en ambos sentidos.
            if (! Schema::hasIndex('asistencias', self::INDICE_FK_DETALLE)) {
                $table->index('detalle_curso_id', self::INDICE_FK_DETALLE);
            }

            if (Schema::hasIndex('asistencias', self::INDICE_NUEVO)) {
                $table->dropUnique(self::INDICE_NUEVO);
            }

            if (! Schema::hasIndex('asistencias', self::INDICE_ANTERIOR)) {
                $table->unique(
                    ['curso_id', 'alumno_id', 'fecha'],
                    self::INDICE_ANTERIOR
                );
            }
        });
    }

    /**
     * Grupos (curso_id, alumno_id, fecha) que violarían el índice anterior.
     *
     * El GROUP BY se hace sobre datos, no sobre el índice, así que el
     * diagnóstico funciona igual en MySQL y en SQLite (las pruebas corren en
     * :memory:). La semántica de NULL del índice nuevo solo importa en MySQL
     * y no se afirma nada sobre ella aquí.
     */
    private function conflictosConElIndiceAnterior()
    {
        return DB::table('asistencias')
            ->select('curso_id', 'alumno_id', 'fecha', DB::raw('COUNT(*) as total_asistencias'))
            ->groupBy('curso_id', 'alumno_id', 'fecha')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('curso_id')
            ->orderBy('alumno_id')
            ->orderBy('fecha')
            ->get();
    }
};
