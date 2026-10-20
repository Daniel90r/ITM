<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VarcharDomainLengthsTest extends TestCase
{
    /**
     * Get the path to the new migration file.
     */
    private function getMigrationPath(): string
    {
        $files = File::glob(database_path('migrations/*_normalize_domain_varchar_lengths.php'));
        $this->assertNotEmpty($files, 'La migración normalize_domain_varchar_lengths no existe');
        return $files[0];
    }

    public function test_migration_file_exists()
    {
        $path = $this->getMigrationPath();
        $this->assertTrue(File::exists($path), 'El archivo de migración existe');
    }

    public function test_migration_contains_exactly_24_columns()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $expectedColumns = [
            'alumno_encargado.parentesco' => 'VARCHAR(50)',
            'alumnos.nombre' => 'VARCHAR(100)',
            'alumnos.apellido' => 'VARCHAR(100)',
            'alumnos.codigo' => 'VARCHAR(20)',
            'alumnos.telefono' => 'VARCHAR(9)',
            'alumnos.direccion' => 'VARCHAR(255)',
            'cursos.nombre' => 'VARCHAR(100)',
            'cursos.seccion' => 'VARCHAR(20)',
            'directores.nombre' => 'VARCHAR(100)',
            'directores.dui' => 'VARCHAR(10)',
            'directores.telefono' => 'VARCHAR(9)',
            'directores.firma_path' => 'VARCHAR(255)',
            'encargados.nombre' => 'VARCHAR(100)',
            'encargados.apellido' => 'VARCHAR(100)',
            'encargados.telefono' => 'VARCHAR(9)',
            'inscripciones.documento_path' => 'VARCHAR(255)',
            'inscripciones.estado' => 'VARCHAR(20)',
            'maestros.nombre' => 'VARCHAR(100)',
            'maestros.apellido' => 'VARCHAR(100)',
            'maestros.codigo' => 'VARCHAR(20)',
            'materias.nombre' => 'VARCHAR(100)',
            'materias.codigo' => 'VARCHAR(20)',
            'users.name' => 'VARCHAR(100)',
            'notas.conducta' => 'VARCHAR(2)',
        ];

        $count = 0;
        foreach ($expectedColumns as $col => $type) {
            $this->assertStringContainsString($col, $content, "La migración menciona {$col}");
            $this->assertStringContainsString($type, $content, "La migración define {$type} para {$col}");
            $count++;
        }

        $this->assertEquals(24, $count, 'La migración contiene exactamente 24 columnas');
    }

    public function test_migration_excludes_cursos_nivel()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        // cursos.nivel should NOT appear in any MODIFY COLUMN statement
        $this->assertStringNotContainsString('MODIFY COLUMN `nivel`', $content, 'No hay MODIFY COLUMN para nivel');
        // Also ensure the table/column combo is not in any ALTER statement
        $this->assertStringNotContainsString('cursos.*nivel', $content, 'cursos.nivel no está en ALTER TABLE');
    }

    public function test_has_notas_conducta_at_2()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('notas', $content);
        $this->assertStringContainsString('conducta', $content);
        $this->assertStringContainsString('VARCHAR(2)', $content);
    }

    public function test_has_alumnos_telefono_at_9()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('alumnos', $content);
        $this->assertStringContainsString('telefono', $content);
        $this->assertStringContainsString('VARCHAR(9)', $content);
    }

    public function test_has_directores_dui_at_10()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('directores', $content);
        $this->assertStringContainsString('dui', $content);
        $this->assertStringContainsString('VARCHAR(10)', $content);
    }

    public function test_has_directores_telefono_at_9()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('directores', $content);
        $this->assertStringContainsString('telefono', $content);
        $this->assertStringContainsString('VARCHAR(9)', $content);
    }

    public function test_has_three_codigos_at_20()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('alumnos.codigo', $content);
        $this->assertStringContainsString('maestros.codigo', $content);
        $this->assertStringContainsString('materias.codigo', $content);

        $matches = preg_match_all('/VARCHAR\(20\)/', $content);
        $this->assertGreaterThanOrEqual(3, $matches, 'Al menos 3 columnas con VARCHAR(20) para los códigos');
    }

    public function test_has_names_at_100()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $nameColumns = [
            'alumnos.nombre',
            'alumnos.apellido',
            'cursos.nombre',
            'directores.nombre',
            'encargados.nombre',
            'encargados.apellido',
            'maestros.nombre',
            'maestros.apellido',
            'materias.nombre',
            'users.name',
        ];

        foreach ($nameColumns as $col) {
            $this->assertStringContainsString($col, $content, "Contiene {$col}");
        }

        $matches = preg_match_all('/VARCHAR\(100\)/', $content);
        $this->assertGreaterThanOrEqual(10, $matches, 'Al menos 10 columnas con VARCHAR(100) para nombres/apellidos');
    }

    public function test_has_paths_at_255()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('alumnos.direccion', $content);
        $this->assertStringContainsString('directores.firma_path', $content);
        $this->assertStringContainsString('inscripciones.documento_path', $content);

        $matches = preg_match_all('/VARCHAR\(255\)/', $content);
        $this->assertGreaterThanOrEqual(3, $matches, 'Al menos 3 columnas con VARCHAR(255) para paths/direcciones');
    }

    public function test_has_mysql_guard()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString("DB::getDriverName() !== 'mysql'", $content);
        $this->assertStringContainsString('return;', $content);
    }

    public function test_down_has_mysql_guard()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $downMethod = $this->extractMethod($content, 'down');
        $this->assertStringContainsString("DB::getDriverName() !== 'mysql'", $downMethod);
        $this->assertStringContainsString('return;', $downMethod);
    }

    public function test_has_no_dangerous_operations()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $dangerous = [
            'DROP TABLE',
            'TRUNCATE',
            'DELETE FROM',
            'UPDATE ',
            'INSERT INTO',
        ];

        foreach ($dangerous as $op) {
            $this->assertStringNotContainsString($op, $content, "La migración NO contiene {$op}");
        }
    }

    public function test_preserves_inscripciones_estado_default()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString("DEFAULT 'aprobada'", $content);
        $this->assertStringContainsString('inscripciones', $content);
        $this->assertStringContainsString('estado', $content);
    }

    public function test_uses_explicit_alter_table()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $this->assertStringContainsString('ALTER TABLE', $content);
        $this->assertStringContainsString('MODIFY COLUMN', $content);
    }

    public function test_preserves_not_null_constraints()
    {
        $path = $this->getMigrationPath();
        $content = File::get($path);

        $notNullColumns = [
            'alumnos.nombre',
            'alumnos.apellido',
            'alumnos.codigo',
            'cursos.nombre',
            'directores.nombre',
            'encargados.nombre',
            'encargados.apellido',
            'maestros.nombre',
            'maestros.apellido',
            'maestros.codigo',
            'materias.nombre',
            'materias.codigo',
            'users.name',
            'inscripciones.estado',
        ];

        foreach ($notNullColumns as $col) {
            $this->assertStringContainsString($col, $content);
        }

        $this->assertStringContainsString('NOT NULL', $content);
    }

    private function extractMethod(string $content, string $methodName): string
    {
        $pattern = '/public function ' . $methodName . '\s*\([^)]*\)\s*:\s*void\s*{/';
        if (!preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $start = $matches[0][1] + strlen($matches[0][0]);
        $braceCount = 1;
        $end = $start;

        while ($braceCount > 0 && $end < strlen($content)) {
            if ($content[$end] === '{') $braceCount++;
            elseif ($content[$end] === '}') $braceCount--;
            $end++;
        }

        return substr($content, $start, $end - $start);
    }
}