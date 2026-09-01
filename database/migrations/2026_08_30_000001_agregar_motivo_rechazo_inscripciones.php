<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inscripciones', 'motivo_rechazo')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->text('motivo_rechazo')->nullable()->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inscripciones', 'motivo_rechazo')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->dropColumn('motivo_rechazo');
            });
        }
    }
};
