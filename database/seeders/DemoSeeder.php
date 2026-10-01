<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Materia;
use App\Models\Alumno;
use App\Models\Maestro;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\AsignacionDocente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos de demostración: docentes, alumnos, matrículas y sus asignaciones.
 *
 * Solo corre en local/testing. Nunca se carga con `php artisan db:seed`
 * (que resuelve DatabaseSeeder); hay que pedirlo explícitamente:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * NINGUNA cuenta tiene contraseña fija. Se genera una aleatoria por cuenta y
 * no se imprime en ningún sitio: así los datos de ejemplo sirven para
 * desarrollar sin dejar una credencial conocida en el repositorio. Si alguien
 * necesita entrar como docente de ejemplo, se cambia la contraseña desde el
 * panel o se regenera la base.
 *
 * Es idempotente: correrlo dos veces no duplica maestros, alumnos,
 * matrículas ni asignaciones, y no sobrescribe asignaciones que ya existían.
 */
class DemoSeeder extends Seeder
{
    private const ANIO_LECTIVO = 2026;

    /** Materias por docente de ejemplo, igual que el seed histórico. */
    private const MATERIAS_CARLOS = ['WIN', 'WRD', 'EXC', 'INT', 'PPT', 'PUB', 'ACC'];

    private const MATERIAS_ANA = ['CDR', 'PSD', 'HTM', 'DRW', 'MPC', 'RED'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder omitido: los datos de demostración solo corren en local/testing.');

            return;
        }

        // Los catálogos son prerrequisito: sin materias y cursos no hay nada
        // que demostrar. Se.seed(DatabaseSeeder) es idempotente, así que
        // invocarlo aquí no altera nada de lo que ya exista.
        $this->call(DatabaseSeeder::class);

        $this->command?->info('DemoSeeder: generando datos de ejemplo con contraseñas aleatorias (no se imprimen).');

        $carlos = $this->maestroDeEjemplo('M001', 'Carlos', 'Mendoza', 'docente@itm.com', self::MATERIAS_CARLOS);
        $ana    = $this->maestroDeEjemplo('M002', 'Ana', 'López', 'docente2@itm.com', self::MATERIAS_ANA);

        $alumnos = $this->alumnosDeEjemplo();
        $this->matricularAlumnos($alumnos);
    }

    /**
     * Crea el User del docente con contraseña aleatoria y su Maestro.
     * `firstOrCreate` sobre el código del maestro: si ya existe, no se toca.
     */
    private function maestroDeEjemplo(string $codigo, string $nombre, string $apellido, string $email, array $materias): Maestro
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $nombre . ' ' . $apellido,
                // Contraseña aleatoria: nunca conocida, nunca impresa.
                'password' => Hash::make(Str::random(40)),
                'role'     => 'docente',
            ]
        );

        $maestro = Maestro::firstOrCreate(
            ['codigo' => $codigo],
            ['user_id' => $user->id, 'nombre' => $nombre, 'apellido' => $apellido, 'activo' => true]
        );

        $this->asignarMaterias($maestro, $materias);

        return $maestro;
    }

    /**
     * Asigna al maestro las materias indicadas en cada curso del programa.
     *
     * Se respeta lo que ya existió: si el detalle ya tiene maestro, no se
     * cambia. La asignación docente se crea con estado 'activa' porque el
     * commit 4 exige una asignación vigente para autorizar asistencia y notas.
     */
    private function asignarMaterias(Maestro $maestro, array $codigos): void
    {
        $idsPorCodigo = Materia::whereIn('codigo', $codigos)->pluck('id', 'codigo');

        $cursos = Curso::where('anio_lectivo', self::ANIO_LECTIVO)->pluck('id');

        foreach ($cursos as $cursoId) {
            foreach ($codigos as $codigo) {
                $materiaId = $idsPorCodigo[$codigo] ?? null;

                if ($materiaId === null) {
                    continue;
                }

                $detalle = DetalleCurso::firstOrCreate(
                    ['curso_id' => $cursoId, 'materia_id' => $materiaId],
                    ['maestro_id' => $maestro->id]
                );

                if ($detalle->maestro_id !== null && $detalle->maestro_id !== $maestro->id) {
                    // Otro maestro lo tenía: no se pisa esa asignación.
                    continue;
                }

                if ($detalle->maestro_id === null) {
                    $detalle->update(['maestro_id' => $maestro->id]);
                }

                $this->asignacionDocenteActiva($detalle->id, $maestro->id);
            }
        }
    }

    /**
     * `asignado_por` queda NULL a propósito: AdminSeeder corre por separado y
     * puede que todavía no exista ningún administrador. La columna es nullable
     * con ON DELETE SET NULL justamente para esto.
     */
    private function asignacionDocenteActiva(int $detalleCursoId, int $maestroId): void
    {
        AsignacionDocente::firstOrCreate(
            ['detalle_curso_id' => $detalleCursoId, 'maestro_id' => $maestroId, 'estado' => 'activa'],
            ['fecha_inicio' => now()->toDateString(), 'asignado_por' => null]
        );
    }

    /** @return array<int,Alumno> */
    private function alumnosDeEjemplo(): array
    {
        $userAlumno = User::firstOrCreate(
            ['email' => 'alumno@itm.com'],
            [
                'name'     => 'Juan Pérez',
                'password' => Hash::make(Str::random(40)),
                'role'     => 'alumno',
            ]
        );

        $alumnos = [
            Alumno::firstOrCreate(
                ['codigo' => 'A001'],
                ['user_id' => $userAlumno->id, 'nombre' => 'Juan', 'apellido' => 'Pérez', 'activo' => true]
            ),
        ];

        $defs = [
            ['A002', 'José',    'Alvarado'],
            ['A003', 'María',   'Benítez'],
            ['A004', 'Pedro',   'Castro'],
            ['A005', 'Lucía',   'Díaz'],
            ['A006', 'Roberto', 'Espinoza'],
            ['A007', 'Karla',   'Flores'],
            ['A008', 'Miguel',  'González'],
            ['A009', 'Sofía',   'Hernández'],
            ['A010', 'Diego',   'Jiménez'],
            ['A011', 'Valeria', 'López'],
            ['A012', 'Andrés',  'Martínez'],
        ];

        foreach ($defs as [$codigo, $nombre, $apellido]) {
            $alumnos[] = Alumno::firstOrCreate(
                ['codigo' => $codigo],
                // Sin user_id: son alumnos de ejemplo sin cuenta de acceso.
                ['nombre' => $nombre, 'apellido' => $apellido, 'activo' => true]
            );
        }

        return $alumnos;
    }

    /**
     * Reparto histórico: sección A y B → 4 alumnos c/u | C, D, F → el resto.
     * `estado` no se fuerza: la columna tiene default 'aprobada', que es lo que
     * exige el scope que usa el commit 4 para resolver alumnos de un curso.
     */
    private function matricularAlumnos(array $alumnos): void
    {
        $cursos = Curso::where('anio_lectivo', self::ANIO_LECTIVO)
            ->pluck('id', 'seccion');

        $reparto = [
            'A' => array_slice($alumnos, 0, 4),
            'B' => array_slice($alumnos, 4, 4),
            'C' => array_slice($alumnos, 8, 2),
            'D' => array_slice($alumnos, 10, 1),
            'F' => array_slice($alumnos, 11, 1),
        ];

        foreach ($reparto as $seccion => $grupo) {
            $cursoId = $cursos[$seccion] ?? null;

            if ($cursoId === null) {
                continue;
            }

            foreach ($grupo as $alumno) {
                Inscripcion::firstOrCreate(
                    ['alumno_id' => $alumno->id, 'curso_id' => $cursoId],
                    ['fecha_inscripcion' => '2026-01-15', 'activa' => true]
                );
            }
        }
    }
}
