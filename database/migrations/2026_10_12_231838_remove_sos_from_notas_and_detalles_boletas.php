<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // notas.sos
        if (Schema::hasColumn('notas', 'sos')) {
            Schema::table('notas', function (Blueprint $table) {
                $table->dropColumn('sos');
            });
        }

        // detalles_boletas.sos
        if (Schema::hasColumn('detalles_boletas', 'sos')) {
            Schema::table('detalles_boletas', function (Blueprint $table) {
                $table->dropColumn('sos');
            });
        }
    }

    /**
     * Reverse the migrations.
     * Note: Down cannot restore lost SOS data. Structural rollback only.
     */
    public function down(): void
    {
        // Cannot restore lost SOS data. This down() only restores the column structure.
        // If you need to rollback, restore from backup: itm_before_remove_sos_20261012_155500.sql

        if (!Schema::hasColumn('notas', 'sos')) {
            Schema::table('notas', function (Blueprint $table) {
                $table->decimal('sos', 4, 2)->nullable()->after('practica');
            });
        }

        if (!Schema::hasColumn('detalles_boletas', 'sos')) {
            Schema::table('detalles_boletas', function (Blueprint $table) {
                $table->decimal('sos', 4, 2)->nullable()->after('practica');
            });
        }
    }
};