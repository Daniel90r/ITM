<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RELATIONS = [
        ['table' => 'alumnos', 'column' => 'user_id', 'references' => 'id', 'on' => 'users', 'name' => 'alumnos_user_id_foreign', 'onDelete' => 'SET NULL'],
        ['table' => 'alumno_encargado', 'column' => 'alumno_id', 'references' => 'id', 'on' => 'alumnos', 'name' => 'alumno_encargado_alumno_id_foreign', 'onDelete' => 'CASCADE'],
        ['table' => 'alumno_encargado', 'column' => 'encargado_id', 'references' => 'id', 'on' => 'encargados', 'name' => 'alumno_encargado_encargado_id_foreign', 'onDelete' => 'CASCADE'],
        ['table' => 'maestros', 'column' => 'user_id', 'references' => 'id', 'on' => 'users', 'name' => 'maestros_user_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'detalle_cursos', 'column' => 'curso_id', 'references' => 'id', 'on' => 'cursos', 'name' => 'detalle_cursos_curso_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'detalle_cursos', 'column' => 'materia_id', 'references' => 'id', 'on' => 'materias', 'name' => 'detalle_cursos_materia_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'detalle_cursos', 'column' => 'maestro_id', 'references' => 'id', 'on' => 'maestros', 'name' => 'detalle_cursos_maestro_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'inscripciones', 'column' => 'alumno_id', 'references' => 'id', 'on' => 'alumnos', 'name' => 'inscripciones_alumno_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'inscripciones', 'column' => 'curso_id', 'references' => 'id', 'on' => 'cursos', 'name' => 'inscripciones_curso_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'mensualidades', 'column' => 'alumno_id', 'references' => 'id', 'on' => 'alumnos', 'name' => 'mensualidades_alumno_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'mensualidades', 'column' => 'curso_id', 'references' => 'id', 'on' => 'cursos', 'name' => 'mensualidades_curso_id_foreign', 'onDelete' => 'SET NULL'],
        ['table' => 'notas', 'column' => 'alumno_id', 'references' => 'id', 'on' => 'alumnos', 'name' => 'notas_alumno_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'notas', 'column' => 'detalle_curso_id', 'references' => 'id', 'on' => 'detalle_cursos', 'name' => 'notas_detalle_curso_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'notas', 'column' => 'registrado_por', 'references' => 'id', 'on' => 'maestros', 'name' => 'notas_registrado_por_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'asistencias', 'column' => 'curso_id', 'references' => 'id', 'on' => 'cursos', 'name' => 'asistencias_curso_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'asistencias', 'column' => 'alumno_id', 'references' => 'id', 'on' => 'alumnos', 'name' => 'asistencias_alumno_id_foreign', 'onDelete' => 'RESTRICT'],
        ['table' => 'asistencias', 'column' => 'registrado_por', 'references' => 'id', 'on' => 'maestros', 'name' => 'asistencias_registrado_por_foreign', 'onDelete' => 'RESTRICT'],
    ];

    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::RELATIONS as $relation) {
            $this->dropExistingOnColumn($relation['table'], $relation['column']);
        }

        $this->addForeignKeys();
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::RELATIONS as $relation) {
            $this->dropExistingOnColumn($relation['table'], $relation['column']);
        }
    }

    private function dropExistingOnColumn(string $table, string $column): void
    {
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', '=', DB::getDatabaseName())
            ->where('TABLE_NAME', '=', $table)
            ->where('COLUMN_NAME', '=', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $name) {
            DB::statement(sprintf(
                'ALTER TABLE %s DROP FOREIGN KEY %s',
                $this->escapeIdentifier($table),
                $this->escapeIdentifier($name)
            ));
        }
    }

    private function addForeignKeys(): void
    {
        $byTable = [];

        foreach (self::RELATIONS as $relation) {
            $byTable[$relation['table']][] = $relation;
        }

        foreach ($byTable as $table => $relations) {
            Schema::table($table, function (Blueprint $table) use ($relations) {
                foreach ($relations as $relation) {
                    $table->foreign($relation['column'], $relation['name'])
                        ->references($relation['references'])
                        ->on($relation['on'])
                        ->onDelete($relation['onDelete']);
                }
            });
        }
    }

    private function escapeIdentifier(string $identifier): string
    {
        return sprintf('`%s`', str_replace('`', '``', $identifier));
    }
};
