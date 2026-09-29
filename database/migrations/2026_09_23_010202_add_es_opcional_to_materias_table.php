<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materias', function (Blueprint $table) {
            $table->boolean('es_opcional')->default(false)->after('activa');
        });

        // Marcar Inglés como opcional
        DB::table('materias')
            ->where('codigo', 'ING')
            ->orWhere('nombre', 'like', '%Ingles%')
            ->orWhere('nombre', 'like', '%Inglés%')
            ->update(['es_opcional' => true]);
    }

    public function down(): void
    {
        Schema::table('materias', function (Blueprint $table) {
            $table->dropColumn('es_opcional');
        });
    }
};
