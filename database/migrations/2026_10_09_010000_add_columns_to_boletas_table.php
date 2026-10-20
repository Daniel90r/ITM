<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('boletas', function (Blueprint $table) {
            $table->unsignedInteger('alumno_id');
            $table->unsignedInteger('curso_id');
            $table->unsignedInteger('director_id')->nullable();
            $table->unsignedInteger('inscripcion_id')->nullable();
            $table->dateTime('fecha_emision');
            $table->string('alumno_nombre', 100);
            $table->string('alumno_apellido', 100);
            $table->string('alumno_codigo', 20);
            $table->string('curso_nombre', 100);
            $table->string('curso_turno', 20);
            $table->string('curso_seccion', 20)->nullable();
            $table->year('curso_anio_lectivo');
            $table->string('director_nombre', 100);
            $table->string('director_firma_snapshot_path', 255);
            $table->unsignedTinyInteger('promedio_general');
            $table->char('concepto_general', 2);

            $table->foreign('alumno_id', 'fk_boleta_alumno')
                ->references('id')
                ->on('alumnos')
                ->onDelete('RESTRICT')
                ->onUpdate('NO ACTION');

            $table->foreign('curso_id', 'fk_boleta_curso')
                ->references('id')
                ->on('cursos')
                ->onDelete('RESTRICT')
                ->onUpdate('NO ACTION');

            $table->foreign('director_id', 'fk_boleta_director')
                ->references('id')
                ->on('directores')
                ->onDelete('SET NULL')
                ->onUpdate('NO ACTION');

            $table->foreign('inscripcion_id', 'fk_boleta_inscripcion')
                ->references('id')
                ->on('inscripciones')
                ->onDelete('SET NULL')
                ->onUpdate('NO ACTION');

            $table->unique(['alumno_id', 'curso_id'], 'uk_boleta_alumno_curso');
            $table->index('fecha_emision', 'idx_boleta_fecha_emision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boletas', function (Blueprint $table) {
            $table->dropForeign('fk_boleta_alumno');
            $table->dropForeign('fk_boleta_curso');
            $table->dropForeign('fk_boleta_director');
            $table->dropForeign('fk_boleta_inscripcion');

            $table->dropUnique('uk_boleta_alumno_curso');
            $table->dropIndex('idx_boleta_fecha_emision');

            $table->dropColumn([
                'alumno_id',
                'curso_id',
                'director_id',
                'inscripcion_id',
                'fecha_emision',
                'alumno_nombre',
                'alumno_apellido',
                'alumno_codigo',
                'curso_nombre',
                'curso_turno',
                'curso_seccion',
                'curso_anio_lectivo',
                'director_nombre',
                'director_firma_snapshot_path',
                'promedio_general',
                'concepto_general',
            ]);
        });
    }
};