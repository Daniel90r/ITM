<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inscripciones', 'observacion')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->text('observacion')->nullable()->after('activa');
            });
        }

        if (! Schema::hasColumn('inscripciones', 'documento_path')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->string('documento_path')->nullable()->after('activa');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            if (Schema::hasColumn('inscripciones', 'documento_path')) {
                $table->dropColumn('documento_path');
            }
            if (Schema::hasColumn('inscripciones', 'observacion')) {
                $table->dropColumn('observacion');
            }
        });
    }
};
