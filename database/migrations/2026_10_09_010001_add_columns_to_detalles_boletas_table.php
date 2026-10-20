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
        Schema::table('detalles_boletas', function (Blueprint $table) {
            $table->unsignedInteger('boleta_id');
            $table->unsignedInteger('detalle_curso_id');
            $table->unsignedInteger('materia_id');
            $table->string('materia_nombre', 100);
            $table->string('materia_codigo', 20)->nullable();
            $table->decimal('laboratorio', 4, 2)->nullable();
            $table->decimal('examen_teorico', 4, 2)->nullable();
            $table->decimal('practica', 4, 2)->nullable();
            $table->decimal('sos', 4, 2)->nullable();
            $table->string('conducta', 2)->nullable();
            $table->unsignedTinyInteger('promedio');

            $table->foreign('boleta_id', 'fk_detalle_boleta_boleta')
                ->references('id')
                ->on('boletas')
                ->onDelete('RESTRICT')
                ->onUpdate('NO ACTION');

            $table->foreign('detalle_curso_id', 'fk_detalle_boleta_detalle_curso')
                ->references('id')
                ->on('detalle_cursos')
                ->onDelete('RESTRICT')
                ->onUpdate('NO ACTION');

            $table->foreign('materia_id', 'fk_detalle_boleta_materia')
                ->references('id')
                ->on('materias')
                ->onDelete('RESTRICT')
                ->onUpdate('NO ACTION');

            $table->unique(['boleta_id', 'detalle_curso_id'], 'uk_detalle_boleta_materia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalles_boletas', function (Blueprint $table) {
            $table->dropForeign('fk_detalle_boleta_boleta');
            $table->dropForeign('fk_detalle_boleta_detalle_curso');
            $table->dropForeign('fk_detalle_boleta_materia');

            $table->dropUnique('uk_detalle_boleta_materia');

            $table->dropColumn([
                'boleta_id',
                'detalle_curso_id',
                'materia_id',
                'materia_nombre',
                'materia_codigo',
                'laboratorio',
                'examen_teorico',
                'practica',
                'sos',
                'conducta',
                'promedio',
            ]);
        });
    }
};