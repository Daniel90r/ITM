<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_horarios', function (Blueprint $table) {
            $table->id();
            // cursos.id es INT UNSIGNED en la base local normalizada.
            $table->unsignedInteger('curso_id');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();
            $table->foreign('curso_id')->references('id')->on('cursos')->restrictOnDelete();
            $table->unique(['curso_id', 'dia_semana', 'hora_inicio', 'hora_fin'], 'curso_horarios_bloque_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_horarios');
    }
};
