<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maestros', function (Blueprint $table) {
            if (! Schema::hasColumn('maestros', 'telefono')) {
                $table->string('telefono', 9)->nullable();
            }
            if (! Schema::hasColumn('maestros', 'dui')) {
                $table->string('dui', 10)->nullable();
            }
        });

        if (Schema::hasColumn('maestros', 'dui') && ! Schema::hasIndex('maestros', ['dui'])) {
            DB::statement('CREATE UNIQUE INDEX maestros_dui_unique ON maestros (dui)');
        }
    }

    public function down(): void
    {
        Schema::table('maestros', function (Blueprint $table) {
            if (Schema::hasIndex('maestros', ['dui'])) {
                $table->dropUnique('maestros_dui_unique');
            }

            $columns = [];
            if (Schema::hasColumn('maestros', 'telefono')) {
                $columns[] = 'telefono';
            }
            if (Schema::hasColumn('maestros', 'dui')) {
                $columns[] = 'dui';
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
