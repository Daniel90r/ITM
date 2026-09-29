<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->foreignId('detalle_curso_id')->nullable()->constrained('detalle_cursos')->onDelete('set null')->after('curso_id');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropForeign(['detalle_curso_id']);
            $table->dropColumn('detalle_curso_id');
        });
    }
};
