<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inscripciones', 'tomara_ingles')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->boolean('tomara_ingles')->default(false)->after('motivo_rechazo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropColumn('tomara_ingles');
        });
    }
};
