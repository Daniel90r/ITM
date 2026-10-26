<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cursos', function (Blueprint $table) {
            $table->unsignedSmallInteger('orden_academico')->nullable()->after('maestro_id');
            $table->index(['curso_id', 'orden_academico']);
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cursos', function (Blueprint $table) {
            $table->dropIndex(['curso_id', 'orden_academico']);
            $table->dropColumn('orden_academico');
        });
    }
};
