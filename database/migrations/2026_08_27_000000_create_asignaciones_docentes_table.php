<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_docentes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('detalle_curso_id')
                ->constrained('detalle_cursos')
                ->onDelete('restrict');
            $table->foreignId('maestro_id')
                ->constrained('maestros')
                ->onDelete('restrict');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['programada', 'activa', 'finalizada']);
            $table->string('motivo_cambio', 255)->nullable();
            $table->foreignId('asignado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['detalle_curso_id', 'estado']);
            $table->index(['maestro_id', 'estado']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });

        DB::table('asignaciones_docentes')->insertUsing(
            ['detalle_curso_id', 'maestro_id', 'estado', 'created_at', 'updated_at'],
            DB::table('detalle_cursos')
                ->select('id', 'maestro_id', DB::raw("'activa'"), 'created_at', 'updated_at')
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_docentes');
    }
};
