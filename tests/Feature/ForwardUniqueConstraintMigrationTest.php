<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForwardUniqueConstraintMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_payment_unique_when_a_normal_index_already_exists(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropUnique('pagos_mensualidad_id_unique');
        });

        if (! Schema::hasIndex('pagos', 'pagos_mensualidad_id_index')) {
            Schema::table('pagos', function (Blueprint $table): void {
                $table->index(
                    'mensualidad_id',
                    'pagos_mensualidad_id_index'
                );
            });
        }

        $this->assertFalse(
            Schema::hasIndex('pagos', ['mensualidad_id'], 'unique')
        );

        $this->runReconciliationMigration();

        $this->assertTrue(
            Schema::hasIndex('pagos', ['mensualidad_id'], 'unique')
        );
    }

    public function test_restores_enrollment_unique_when_a_normal_index_already_exists(): void
    {
        Schema::table('inscripciones', function (Blueprint $table): void {
            $table->dropUnique(
                'inscripciones_alumno_id_curso_id_unique'
            );
        });

        if (! Schema::hasIndex(
            'inscripciones',
            'inscripciones_alumno_id_curso_id_index'
        )) {
            Schema::table('inscripciones', function (Blueprint $table): void {
                $table->index(
                    ['alumno_id', 'curso_id'],
                    'inscripciones_alumno_id_curso_id_index'
                );
            });
        }

        $this->assertFalse(
            Schema::hasIndex(
                'inscripciones',
                ['alumno_id', 'curso_id'],
                'unique'
            )
        );

        $this->runReconciliationMigration();

        $this->assertTrue(
            Schema::hasIndex(
                'inscripciones',
                ['alumno_id', 'curso_id'],
                'unique'
            )
        );
    }

    private function runReconciliationMigration(): void
    {
        $migration = require database_path(
            'migrations/'.
            '2026_09_09_024644_'.
            'ensure_unique_constraints_on_pagos_and_inscripciones.php'
        );

        $migration->up();
    }
}
