<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use Throwable;

/**
 * Genera copias de seguridad de la base de datos como archivos .sql.
 *
 * El volcado se escribe en PHP, sin depender de `mysqldump`: en alojamientos
 * compartidos (cPanel) la ejecución de programas externos suele estar
 * deshabilitada. El archivo resultante se restaura importándolo en phpMyAdmin
 * o con `mysql < archivo.sql`.
 *
 * Los respaldos se guardan en el disco privado `local`, fuera de public/.
 */
class RespaldoBaseDatos
{
    public const CARPETA = 'respaldos';

    public const PATRON_ARCHIVO = '/^respaldo-itm-\d{4}-\d{2}-\d{2}_\d{6}(-\d+)?\.(sql|zip)$/';

    /**
     * Tablas cuyo contenido es temporal o sensible (sesiones activas, tokens de
     * restablecimiento, caché, colas). Se respalda su estructura, no sus filas.
     */
    private const SIN_DATOS = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    private const FILAS_POR_INSERT = 200;

    /** Crea un respaldo nuevo y devuelve el nombre del archivo. */
    public function generar(): string
    {
        $disco = Storage::disk('local');
        $disco->makeDirectory(self::CARPETA);

        $base = 'respaldo-itm-' . now()->format('Y-m-d_His');
        $nombre = $base . '.sql';
        for ($i = 2; $disco->exists(self::CARPETA . '/' . $nombre); $i++) {
            $nombre = "{$base}-{$i}.sql";
        }

        // Se escribe en un archivo temporal y se renombra al terminar, para que
        // un respaldo interrumpido nunca aparezca en la lista como si estuviera completo.
        $destino = $disco->path(self::CARPETA . '/' . $nombre);
        $temporal = $destino . '.parcial';
        $archivo = fopen($temporal, 'wb');

        try {
            $this->escribir($archivo);
            fclose($archivo);
            rename($temporal, $destino);
        } catch (Throwable $e) {
            if (is_resource($archivo)) {
                fclose($archivo);
            }
            @unlink($temporal);
            throw $e;
        }

        return $nombre;
    }

    /** Crea un ZIP con el SQL y los archivos de los discos de la aplicación. */
    public function generarCompleto(): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión ZIP de PHP no está habilitada.');
        }

        $disco = Storage::disk('local');
        $disco->makeDirectory(self::CARPETA);
        $base = 'respaldo-itm-' . now()->format('Y-m-d_His');
        $nombre = $base . '.zip';
        for ($i = 2; $disco->exists(self::CARPETA . '/' . $nombre); $i++) {
            $nombre = "{$base}-{$i}.zip";
        }

        $destino = $disco->path(self::CARPETA . '/' . $nombre);
        $temporalZip = $destino . '.parcial';
        $temporalSql = tempnam(sys_get_temp_dir(), 'itm-respaldo-');
        if ($temporalSql === false) {
            throw new \RuntimeException('No se pudo preparar el archivo SQL temporal.');
        }

        try {
            $archivoSql = fopen($temporalSql, 'wb');
            if ($archivoSql === false) throw new \RuntimeException('No se pudo crear el archivo SQL temporal.');
            try {
                $this->escribir($archivoSql);
            } finally {
                fclose($archivoSql);
            }

            $zip = new \ZipArchive();
            if ($zip->open($temporalZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el archivo ZIP.');
            }
            try {
                if (! $zip->addFile($temporalSql, 'base-de-datos.sql')) {
                    throw new \RuntimeException('No se pudo agregar la base de datos al ZIP.');
                }
                if (! $zip->addFromString('LEEME.txt', "Respaldo completo ITM Aguilares\n\n"
                    . "base-de-datos.sql contiene la base de datos.\n"
                    . "archivos/ contiene los archivos guardados por la aplicación.\n"
                    . "Para restaurar, importe base-de-datos.sql en phpMyAdmin y recupere los archivos conservando sus carpetas.\n"
                    . "Mantenga este respaldo en un lugar seguro: contiene datos personales y documentos.\n")) {
                    throw new \RuntimeException('No se pudo agregar la guía de restauración al ZIP.');
                }
                $this->agregarArchivosDeDisco($zip, 'local', 'archivos/local', true);
                $this->agregarArchivosDeDisco($zip, 'public', 'archivos/public');
                $this->agregarArchivosDeDisco($zip, 'inscripciones', 'archivos/inscripciones', false, 'local');
            } finally {
                if (! $zip->close()) throw new \RuntimeException('No se pudo finalizar el contenido del ZIP.');
            }

            if (! rename($temporalZip, $destino)) {
                throw new \RuntimeException('No se pudo finalizar el archivo ZIP.');
            }
        } catch (Throwable $e) {
            @unlink($temporalZip);
            throw $e;
        } finally {
            @unlink($temporalSql);
        }

        return $nombre;
    }

    private function agregarArchivosDeDisco(\ZipArchive $zip, string $disco, string $prefijo, bool $excluirRespaldos = false, ?string $evitarDuplicadoCon = null): void
    {
        $almacenamiento = Storage::disk($disco);
        $raiz = realpath($almacenamiento->path(''));
        $raizDuplicada = $evitarDuplicadoCon ? realpath(Storage::disk($evitarDuplicadoCon)->path('')) : false;

        foreach ($almacenamiento->allFiles() as $ruta) {
            $ruta = str_replace('\\', '/', $ruta);
            if ($excluirRespaldos && str_starts_with($ruta, self::CARPETA . '/')) continue;

            $real = realpath($almacenamiento->path($ruta));
            if ($real === false || ! is_file($real)) continue;
            if ($raiz && ! str_starts_with($real, rtrim($raiz, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) continue;
            if ($raizDuplicada && ($real === $raizDuplicada || str_starts_with($real, rtrim($raizDuplicada, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) continue;

            $nombreInterno = trim($prefijo . '/' . $ruta, '/');
            if (! $zip->addFile($real, $nombreInterno)) {
                throw new \RuntimeException("No se pudo agregar el archivo {$nombreInterno} al ZIP.");
            }
        }
    }

    /** Respaldos guardados, del más reciente al más antiguo. */
    public function listar(): Collection
    {
        $disco = Storage::disk('local');

        return collect($disco->files(self::CARPETA))
            ->map(fn (string $ruta) => basename($ruta))
            ->filter(fn (string $nombre) => $this->esValido($nombre))
            ->map(fn (string $nombre) => [
                'nombre' => $nombre,
                'bytes' => $disco->size(self::CARPETA . '/' . $nombre),
                'fecha' => Carbon::createFromTimestamp($disco->lastModified(self::CARPETA . '/' . $nombre)),
            ])
            ->sortByDesc('fecha')
            ->values();
    }

    public function existe(string $nombre): bool
    {
        return $this->esValido($nombre) && Storage::disk('local')->exists(self::CARPETA . '/' . $nombre);
    }

    public function ruta(string $nombre): string
    {
        return self::CARPETA . '/' . $nombre;
    }

    public function eliminar(string $nombre): bool
    {
        return $this->existe($nombre) && Storage::disk('local')->delete($this->ruta($nombre));
    }

    private function esValido(string $nombre): bool
    {
        return preg_match(self::PATRON_ARCHIVO, $nombre) === 1;
    }

    /** @param resource $archivo */
    private function escribir($archivo): void
    {
        $conexion = DB::connection();
        $pdo = $conexion->getPdo();
        $esMysql = in_array($conexion->getDriverName(), ['mysql', 'mariadb'], true);

        fwrite($archivo, "-- Respaldo de la base de datos del Sistema Académico ITM Aguilares\n");
        fwrite($archivo, '-- Base de datos: ' . $conexion->getDatabaseName() . "\n");
        fwrite($archivo, '-- Generado: ' . now()->format('Y-m-d H:i:s') . "\n");
        fwrite($archivo, "-- Restaurar: importar este archivo en phpMyAdmin o con `mysql -u USUARIO -p BASE < archivo.sql`\n\n");

        if ($esMysql) {
            fwrite($archivo, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
        }

        foreach ($this->tablas() as $tabla) {
            fwrite($archivo, "-- --------------------------------------------------------\n-- Tabla `{$tabla}`\n\n");
            fwrite($archivo, "DROP TABLE IF EXISTS `{$tabla}`;\n");
            fwrite($archivo, $this->definicion($tabla, $esMysql) . ";\n\n");

            if (! in_array($tabla, self::SIN_DATOS, true)) {
                $this->escribirFilas($archivo, $pdo, $tabla);
            }
        }

        if ($esMysql) {
            fwrite($archivo, "SET FOREIGN_KEY_CHECKS = 1;\n");
        }
        fwrite($archivo, "\n-- Fin del respaldo\n");
    }

    /** @return string[] */
    private function tablas(): array
    {
        return collect(Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false))
            ->reject(fn (string $nombre) => str_starts_with($nombre, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }

    private function definicion(string $tabla, bool $esMysql): string
    {
        if ($esMysql) {
            $fila = (array) DB::selectOne("SHOW CREATE TABLE `{$tabla}`");

            return $fila['Create Table'];
        }

        return DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$tabla])->sql;
    }

    /** @param resource $archivo */
    private function escribirFilas($archivo, PDO $pdo, string $tabla): void
    {
        $columnas = null;
        $lote = [];

        foreach (DB::table($tabla)->cursor() as $fila) {
            $fila = (array) $fila;
            $columnas ??= '`' . implode('`, `', array_keys($fila)) . '`';
            $lote[] = '(' . implode(', ', array_map(fn ($valor) => $this->valor($pdo, $valor), $fila)) . ')';

            if (count($lote) === self::FILAS_POR_INSERT) {
                fwrite($archivo, "INSERT INTO `{$tabla}` ({$columnas}) VALUES\n" . implode(",\n", $lote) . ";\n");
                $lote = [];
            }
        }

        if ($lote !== []) {
            fwrite($archivo, "INSERT INTO `{$tabla}` ({$columnas}) VALUES\n" . implode(",\n", $lote) . ";\n");
        }
        fwrite($archivo, "\n");
    }

    private function valor(PDO $pdo, mixed $valor): string
    {
        return match (true) {
            $valor === null => 'NULL',
            is_bool($valor) => $valor ? '1' : '0',
            is_int($valor), is_float($valor) => (string) $valor,
            default => $pdo->quote((string) $valor),
        };
    }
}
