<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactivaciones_inscripcion', function (Blueprint $table) {
            // Los identificadores del dominio ITM son INT UNSIGNED.
            $table->increments('id');
            $table->unsignedInteger('inscripcion_id')->unique();
            $table->unsignedInteger('administrador_id');
            $table->string('estado_anterior', 20);
            $table->string('estado_nuevo', 20);
            $table->dateTime('reactivada_at');
            $table->string('motivo', 500);
            $table->foreign('inscripcion_id')->references('id')->on('inscripciones')->restrictOnDelete();
            $table->foreign('administrador_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactivaciones_inscripcion');
    }
};
