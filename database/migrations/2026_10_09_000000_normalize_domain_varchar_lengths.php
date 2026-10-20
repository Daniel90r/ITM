<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize domain VARCHAR lengths to appropriate sizes.
     *
     * This migration adjusts 24 VARCHAR columns across 10 tables
     * to lengths that match their actual semantic domain.
     *
     * Excluded intentionally:
     *   - cursos.nivel (semantic rename to turno/jornada pending)
     *   - All framework tables (cache, sessions, jobs, etc.)
     *   - Email, password, token columns
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // alumno_encargado.parentesco: VARCHAR(191) -> VARCHAR(50)
        DB::statement("ALTER TABLE `alumno_encargado` MODIFY COLUMN `parentesco` VARCHAR(50) NOT NULL");

        // alumnos.nombre: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `nombre` VARCHAR(100) NOT NULL");

        // alumnos.apellido: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `apellido` VARCHAR(100) NOT NULL");

        // alumnos.codigo: VARCHAR(191) -> VARCHAR(20) [UNIQUE preserved]
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `codigo` VARCHAR(20) NOT NULL");

        // alumnos.telefono: VARCHAR(191) -> VARCHAR(9) [nullable preserved]
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `telefono` VARCHAR(9) NULL");

        // alumnos.direccion: VARCHAR(191) -> VARCHAR(255) [nullable preserved]
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `direccion` VARCHAR(255) NULL");

        // cursos.nombre: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `cursos` MODIFY COLUMN `nombre` VARCHAR(100) NOT NULL");

        // cursos.seccion: VARCHAR(191) -> VARCHAR(20) [nullable preserved]
        DB::statement("ALTER TABLE `cursos` MODIFY COLUMN `seccion` VARCHAR(20) NULL");

        // directores.nombre: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `nombre` VARCHAR(100) NOT NULL");

        // directores.dui: VARCHAR(191) -> VARCHAR(10) [nullable preserved]
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `dui` VARCHAR(10) NULL");

        // directores.telefono: VARCHAR(191) -> VARCHAR(9) [nullable preserved]
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `telefono` VARCHAR(9) NULL");

        // directores.firma_path: VARCHAR(191) -> VARCHAR(255) [nullable preserved]
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `firma_path` VARCHAR(255) NULL");

        // encargados.nombre: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `encargados` MODIFY COLUMN `nombre` VARCHAR(100) NOT NULL");

        // encargados.apellido: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `encargados` MODIFY COLUMN `apellido` VARCHAR(100) NOT NULL");

        // encargados.telefono: VARCHAR(191) -> VARCHAR(9) [nullable preserved]
        DB::statement("ALTER TABLE `encargados` MODIFY COLUMN `telefono` VARCHAR(9) NULL");

        // inscripciones.documento_path: VARCHAR(191) -> VARCHAR(255) [nullable preserved]
        DB::statement("ALTER TABLE `inscripciones` MODIFY COLUMN `documento_path` VARCHAR(255) NULL");

        // inscripciones.estado: VARCHAR(191) -> VARCHAR(20) [NOT NULL, DEFAULT 'aprobada' preserved]
        DB::statement("ALTER TABLE `inscripciones` MODIFY COLUMN `estado` VARCHAR(20) NOT NULL DEFAULT 'aprobada'");

        // maestros.nombre: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `maestros` MODIFY COLUMN `nombre` VARCHAR(100) NOT NULL");

        // maestros.apellido: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `maestros` MODIFY COLUMN `apellido` VARCHAR(100) NOT NULL");

        // maestros.codigo: VARCHAR(191) -> VARCHAR(20) [UNIQUE preserved]
        DB::statement("ALTER TABLE `maestros` MODIFY COLUMN `codigo` VARCHAR(20) NOT NULL");

        // materias.nombre: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `materias` MODIFY COLUMN `nombre` VARCHAR(100) NOT NULL");

        // materias.codigo: VARCHAR(191) -> VARCHAR(20) [UNIQUE preserved]
        DB::statement("ALTER TABLE `materias` MODIFY COLUMN `codigo` VARCHAR(20) NOT NULL");

        // users.name: VARCHAR(191) -> VARCHAR(100)
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(100) NOT NULL");

        // notas.conducta: VARCHAR(191) -> VARCHAR(2) [nullable preserved]
        // Domain: B, MB, E, null. Legacy value 'P' fits in VARCHAR(2).
        DB::statement("ALTER TABLE `notas` MODIFY COLUMN `conducta` VARCHAR(2) NULL");
    }

    /**
     * Revert all VARCHAR lengths back to 191 (or original where different).
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // alumno_encargado.parentesco: VARCHAR(50) -> VARCHAR(191)
        DB::statement("ALTER TABLE `alumno_encargado` MODIFY COLUMN `parentesco` VARCHAR(191) NOT NULL");

        // alumnos.nombre: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `nombre` VARCHAR(191) NOT NULL");

        // alumnos.apellido: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `apellido` VARCHAR(191) NOT NULL");

        // alumnos.codigo: VARCHAR(20) -> VARCHAR(191) [UNIQUE preserved]
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `codigo` VARCHAR(191) NOT NULL");

        // alumnos.telefono: VARCHAR(9) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `telefono` VARCHAR(191) NULL");

        // alumnos.direccion: VARCHAR(255) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `alumnos` MODIFY COLUMN `direccion` VARCHAR(191) NULL");

        // cursos.nombre: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `cursos` MODIFY COLUMN `nombre` VARCHAR(191) NOT NULL");

        // cursos.seccion: VARCHAR(20) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `cursos` MODIFY COLUMN `seccion` VARCHAR(191) NULL");

        // directores.nombre: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `nombre` VARCHAR(191) NOT NULL");

        // directores.dui: VARCHAR(10) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `dui` VARCHAR(191) NULL");

        // directores.telefono: VARCHAR(9) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `telefono` VARCHAR(191) NULL");

        // directores.firma_path: VARCHAR(255) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `directores` MODIFY COLUMN `firma_path` VARCHAR(191) NULL");

        // encargados.nombre: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `encargados` MODIFY COLUMN `nombre` VARCHAR(191) NOT NULL");

        // encargados.apellido: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `encargados` MODIFY COLUMN `apellido` VARCHAR(191) NOT NULL");

        // encargados.telefono: VARCHAR(9) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `encargados` MODIFY COLUMN `telefono` VARCHAR(191) NULL");

        // inscripciones.documento_path: VARCHAR(255) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `inscripciones` MODIFY COLUMN `documento_path` VARCHAR(191) NULL");

        // inscripciones.estado: VARCHAR(20) -> VARCHAR(191) [NOT NULL, DEFAULT 'aprobada']
        DB::statement("ALTER TABLE `inscripciones` MODIFY COLUMN `estado` VARCHAR(191) NOT NULL DEFAULT 'aprobada'");

        // maestros.nombre: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `maestros` MODIFY COLUMN `nombre` VARCHAR(191) NOT NULL");

        // maestros.apellido: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `maestros` MODIFY COLUMN `apellido` VARCHAR(191) NOT NULL");

        // maestros.codigo: VARCHAR(20) -> VARCHAR(191) [UNIQUE preserved]
        DB::statement("ALTER TABLE `maestros` MODIFY COLUMN `codigo` VARCHAR(191) NOT NULL");

        // materias.nombre: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `materias` MODIFY COLUMN `nombre` VARCHAR(191) NOT NULL");

        // materias.codigo: VARCHAR(20) -> VARCHAR(191) [UNIQUE preserved]
        DB::statement("ALTER TABLE `materias` MODIFY COLUMN `codigo` VARCHAR(191) NOT NULL");

        // users.name: VARCHAR(100) -> VARCHAR(191)
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(191) NOT NULL");

        // notas.conducta: VARCHAR(2) -> VARCHAR(191) [nullable]
        DB::statement("ALTER TABLE `notas` MODIFY COLUMN `conducta` VARCHAR(191) NULL");
    }
};