<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('pagos', 'mensualidad_id')) {
                $table->foreignId('mensualidad_id')
                    ->constrained('mensualidades')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('pagos', 'fecha_pago')) {
                $table->date('fecha_pago');
            }

            if (! Schema::hasColumn('pagos', 'monto_pagado')) {
                $table->decimal('monto_pagado', 10, 2);
            }

            if (! Schema::hasColumn('pagos', 'observacion')) {
                $table->text('observacion')->nullable();
            }
        });

        if (! Schema::hasIndex('pagos', ['fecha_pago'])) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->index('fecha_pago');
            });
        }

        if (Schema::hasColumn('pagos', 'mensualidad_id')) {
            $existing = DB::table('pagos')
                ->whereNotIn('mensualidad_id', function ($q) {
                    $q->select('id')->from('mensualidades');
                })
                ->exists();

            if ($existing) {
                throw new \RuntimeException(
                    'Existen registros en pagos con mensualidad_id que no referencian una mensualidad válida. ' .
                    'Resuelva los datos huérfanos antes de aplicar esta migración.'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasIndex('pagos', ['fecha_pago'])) {
                $table->dropIndex(['fecha_pago']);
            }

            if (Schema::hasColumn('pagos', 'mensualidad_id')) {
                if (Schema::hasForeignKey('pagos', 'mensualidad_id')) {
                    $table->dropForeign(['mensualidad_id']);
                }
                $table->dropUnique(['mensualidad_id']);
                $table->dropColumn(['mensualidad_id', 'fecha_pago', 'monto_pagado', 'observacion']);
            }
        });
    }
};
