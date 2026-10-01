<?php

namespace Database\Seeders;

use App\Models\Materia;
use App\Models\Curso;
use App\Models\DetalleCurso;
use Illuminate\Database\Seeder;

/**
 * Catálogos académicos: lo mínimo que una instalación necesita para existir.
 *
 * Este seeder NO crea usuarios. En concreto NO crea el administrador: eso es
 * tarea de AdminSeeder, que exige ADMIN_SEED_PASSWORD. Tampoco crea docentes
 * ni alumnos de ejemplo: eso vive en DemoSeeder, que solo corre en
 * local/testing y nunca se carga con `db:seed` a secas.
 *
 * Motivo: cualquier cuenta con contraseña fija escrita en el repositorio es
 * una credencial conocida en cualquier despliegue donde se ejecute el seed.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Materias que forman el programa de cada curso. Inglés queda fuera porque
     * es opcional: no forma parte del programa base y se gestiona por
     * alumno (ver `alumno_materia`).
     */
    private const MATERIAS_DEL_PROGRAMA = [
        'WIN', 'WRD', 'EXC', 'INT', 'PPT', 'PUB', 'ACC',
        'CDR', 'PSD', 'HTM', 'DRW', 'MPC', 'RED',
    ];

    public function run(): void
    {
        $this->catalogoDeMaterias();
        $this->catalogoDeCursos();
        $this->programaDeLosCursos();
    }

    /**
     * `es_opcional` se persiste AL CREAR la materia. La migración
     * 2026_09_23_010202 solo hace el backfill sobre las bases que ya tenían la
     * materia: en un `migrate:fresh --seed` la fila todavía no existe cuando
     * la migración corre.
     */
    private function catalogoDeMaterias(): void
    {
        $materiasDefs = [
            ['nombre' => 'Inglés',                 'codigo' => 'ING', 'es_opcional' => true],
            ['nombre' => 'Microsoft Windows',      'codigo' => 'WIN', 'es_opcional' => false],
            ['nombre' => 'Microsoft Word',         'codigo' => 'WRD', 'es_opcional' => false],
            ['nombre' => 'Microsoft Excel',        'codigo' => 'EXC', 'es_opcional' => false],
            ['nombre' => 'Internet',               'codigo' => 'INT', 'es_opcional' => false],
            ['nombre' => 'Microsoft PowerPoint',   'codigo' => 'PPT', 'es_opcional' => false],
            ['nombre' => 'Microsoft Publisher',    'codigo' => 'PUB', 'es_opcional' => false],
            ['nombre' => 'Microsoft Access',       'codigo' => 'ACC', 'es_opcional' => false],
            ['nombre' => 'CorelDRAW',              'codigo' => 'CDR', 'es_opcional' => false],
            ['nombre' => 'Photoshop',              'codigo' => 'PSD', 'es_opcional' => false],
            ['nombre' => 'HTML',                   'codigo' => 'HTM', 'es_opcional' => false],
            ['nombre' => 'Macromedia Dreamweaver', 'codigo' => 'DRW', 'es_opcional' => false],
            ['nombre' => 'Mantenimiento de PC',    'codigo' => 'MPC', 'es_opcional' => false],
            ['nombre' => 'Redes',                  'codigo' => 'RED', 'es_opcional' => false],
        ];

        foreach ($materiasDefs as $m) {
            $opcional = $m['es_opcional'];

            $materia = Materia::firstOrCreate(
                ['codigo' => $m['codigo']],
                ['nombre' => $m['nombre'], 'activa' => true, 'es_opcional' => $opcional]
            );

            // Corrección segura: solo se toca la bandera de la materia que el
            // catálogo declara opcional. No se sobrescribe nada más ni se
            // tocan las materias administradas por el usuario.
            if ($opcional && ! $materia->es_opcional) {
                $materia->update(['es_opcional' => true]);
            }
        }
    }

    /** Matutino: A, B, C | Vespertino: D, F */
    private function catalogoDeCursos(): void
    {
        $secciones = [
            ['seccion' => 'A', 'turno' => 'Matutino'],
            ['seccion' => 'B', 'turno' => 'Matutino'],
            ['seccion' => 'C', 'turno' => 'Matutino'],
            ['seccion' => 'D', 'turno' => 'Vespertino'],
            ['seccion' => 'F', 'turno' => 'Vespertino'],
        ];

        foreach ($secciones as $s) {
            Curso::firstOrCreate(
                ['nombre' => 'Sección ' . $s['seccion'], 'anio_lectivo' => 2026],
                ['nivel' => $s['turno'], 'seccion' => $s['seccion'], 'activo' => true]
            );
        }
    }

    /**
     * Programa de cada curso: qué materias se imparten.
     *
     * `maestro_id` queda NULL a propósito: la migración
     * 2026_08_31_000002 lo volvió nullable para representar programas sin
     * profesor. Una instalación nueva tiene el programa definido y ningún
     * docente, que es exactamente el estado real. Quien asigne un maestro lo
     * hace desde el panel o con DemoSeeder, y en ese momento se crea también
     * la asignación docente activa que exige la resolución de asistencia y
     * notas del commit 4.
     *
     * `firstOrCreate` no pisa filas existentes: si una base ya tenía esta
     * materia asignada a un docente, esa asignación se respeta.
     */
    private function programaDeLosCursos(): void
    {
        $cursos = Curso::where('anio_lectivo', 2026)->get();

        $idsPorCodigo = Materia::pluck('id', 'codigo');

        foreach ($cursos as $curso) {
            foreach (self::MATERIAS_DEL_PROGRAMA as $codigo) {
                $materiaId = $idsPorCodigo[$codigo] ?? null;

                if ($materiaId === null) {
                    continue;
                }

                DetalleCurso::firstOrCreate(
                    ['curso_id' => $curso->id, 'materia_id' => $materiaId],
                    ['maestro_id' => null]
                );
            }
        }
    }
}
