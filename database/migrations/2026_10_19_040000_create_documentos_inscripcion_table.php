<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_inscripcion', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('inscripcion_id');
            $table->unsignedInteger('version');
            $table->string('ruta', 255);
            $table->enum('disco', ['inscripciones', 'public']);
            $table->char('sha256', 64);
            $table->unsignedInteger('bytes');
            $table->timestamp('registrado_at')->useCurrent();
            // NULL para históricos: UNIQUE admite varios NULL en MySQL y SQLite.
            // ENUM restringe el único valor no nulo a '1' (vigente).
            $table->enum('vigente', ['1'])->nullable();
            $table->foreign('inscripcion_id')->references('id')->on('inscripciones')->restrictOnDelete();
            $table->unique(['inscripcion_id', 'version'], 'documentos_inscripcion_version_unique');
            $table->unique(['inscripcion_id', 'vigente'], 'documentos_inscripcion_vigente_unique');
            $table->unique(['disco', 'ruta'], 'documentos_inscripcion_archivo_unique');
            $table->index(['inscripcion_id', 'sha256'], 'documentos_inscripcion_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_inscripcion');
    }
};
