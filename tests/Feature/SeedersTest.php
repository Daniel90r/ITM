<?php

namespace Tests\Feature;

use App\Models\Materia;
use App\Models\User;
use App\Models\Alumno;
use App\Models\Maestro;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\AsignacionDocente;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Los seeders no pueden introducir credenciales conocidas ni datos que
 * contradigan el catálogo de materias.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════════════════
    // DatabaseSeeder: la materia opcional se persiste al CREAR
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_el_seeder_deja_ingles_como_materia_opcional(): void
    {
        $this->seed(DatabaseSeeder::class);

        $ingles = Materia::where('codigo', 'ING')->firstOrFail();

        $this->assertTrue((bool) $ingles->es_opcional, 'ING debe quedar es_opcional = true tras el seed.');
    }

    #[Test]
    public function test_el_seeder_no_marca_como_opcionales_las_materias_normales(): void
    {
        $this->seed(DatabaseSeeder::class);

        $opcionales = Materia::where('es_opcional', true)->pluck('codigo')->all();

        $this->assertEquals(['ING'], $opcionales);
    }

    #[Test]
    public function test_el_seeder_corrige_una_ingles_que_habia_quedado_no_opcional(): void
    {
        // Estado heredado: la materia existe pero con la bandera equivocada.
        Materia::create([
            'nombre' => 'Inglés',
            'codigo' => 'ING',
            'activa' => true,
            'es_opcional' => false,
        ]);

        $otraPropia = Materia::create([
            'nombre' => 'Materia del usuario',
            'codigo' => 'MIO',
            'activa' => true,
            'es_opcional' => false,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue((bool) Materia::where('codigo', 'ING')->firstOrFail()->es_opcional);

        // No se tocan las materias que el usuario administra.
        $this->assertFalse((bool) $otraPropia->fresh()->es_opcional);
        $this->assertEquals('Materia del usuario', $otraPropia->fresh()->nombre);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AdminSeeder: sin contraseña por defecto
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_admin_seeder_crea_el_admin_cuando_la_variable_esta_definida(): void
    {
        config(['app.env' => 'local']);
        putenv('ADMIN_SEED_PASSWORD=clave-de-prueba-segura');
        $_ENV['ADMIN_SEED_PASSWORD'] = 'clave-de-prueba-segura';

        try {
            $this->seed(AdminSeeder::class);

            $admin = User::where('email', 'admin@itm.com')->firstOrFail();

            $this->assertEquals('admin', $admin->role);
            $this->assertTrue(Hash::check('clave-de-prueba-segura', $admin->password));
        } finally {
            putenv('ADMIN_SEED_PASSWORD');
            unset($_ENV['ADMIN_SEED_PASSWORD']);
        }
    }

    #[Test]
    public function test_admin_seeder_no_crea_ninguna_cuenta_si_falta_la_variable(): void
    {
        config(['app.env' => 'local']);
        putenv('ADMIN_SEED_PASSWORD');
        unset($_ENV['ADMIN_SEED_PASSWORD']);

        $this->expectException(RuntimeException::class);

        try {
            $this->seed(AdminSeeder::class);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    #[Test]
    public function test_admin_seeder_no_crea_cuentas_en_produccion(): void
    {
        // Application::environment() lee el binding 'env' del contenedor, no
        // config('app.env'), así que hay que sobreescribir el binding.
        app()['env'] = 'production';

        // El seeder avisa por consola cuando se omite; el mock de salida de
        // las pruebas no admite ese aviso.
        $this->withoutMockingConsoleOutput();

        putenv('ADMIN_SEED_PASSWORD=clave-de-prueba-segura');
        $_ENV['ADMIN_SEED_PASSWORD'] = 'clave-de-prueba-segura';

        try {
            $this->assertTrue(app()->environment('production'));

            $this->seed(AdminSeeder::class);

            $this->assertDatabaseCount('users', 0);
        } finally {
            app()['env'] = 'testing';
            putenv('ADMIN_SEED_PASSWORD');
            unset($_ENV['ADMIN_SEED_PASSWORD']);
        }
    }

    #[Test]
    public function test_admin_seeder_no_modifica_un_admin_existente(): void
    {
        config(['app.env' => 'local']);

        $existente = User::create([
            'name' => 'Admin Real',
            'email' => 'admin@itm.com',
            'password' => Hash::make('otra-clave-original'),
            'role' => 'admin',
        ]);

        putenv('ADMIN_SEED_PASSWORD=clave-nueva');
        $_ENV['ADMIN_SEED_PASSWORD'] = 'clave-nueva';

        try {
            $this->seed(AdminSeeder::class);

            $existente->refresh();

            $this->assertEquals('Admin Real', $existente->name);
            $this->assertTrue(Hash::check('otra-clave-original', $existente->password));
            $this->assertEquals(1, User::where('email', 'admin@itm.com')->count());
        } finally {
            putenv('ADMIN_SEED_PASSWORD');
            unset($_ENV['ADMIN_SEED_PASSWORD']);
        }
    }

    #[Test]
    public function test_env_example_documenta_la_variable_sin_valor(): void
    {
        $contenido = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^ADMIN_SEED_PASSWORD=\s*$/m',
            $contenido,
            'ADMIN_SEED_PASSWORD debe documentarse vacía en .env.example.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DatabaseSeeder: catálogos solamente, ninguna cuenta
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_database_seeder_no_crea_ningun_usuario(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function test_database_seeder_no_llama_al_admin_seeder(): void
    {
        // Sin ADMIN_SEED_PASSWORD, AdminSeeder lanzaría RuntimeException. Si
        // DatabaseSeeder lo invocara, este test reventaría.
        putenv('ADMIN_SEED_PASSWORD');
        unset($_ENV['ADMIN_SEED_PASSWORD']);

        try {
            $this->seed(DatabaseSeeder::class);

            $this->assertDatabaseCount('users', 0);
        } finally {
            putenv('ADMIN_SEED_PASSWORD');
        }
    }

    #[Test]
    public function test_database_seeder_no_crea_cuentas_con_contrasena_fija(): void
    {
        $this->seed(DatabaseSeeder::class);

        // La cuenta de admin histórica era admin@itm.com / 'password'.
        $this->assertDatabaseMissing('users', ['email' => 'admin@itm.com']);
        $this->assertDatabaseMissing('users', ['email' => 'docente@itm.com']);
        $this->assertDatabaseMissing('users', ['email' => 'docente2@itm.com']);
        $this->assertDatabaseMissing('users', ['email' => 'alumno@itm.com']);
    }

    #[Test]
    public function test_database_seeder_no_crea_docentes_ni_alumnos_de_ejemplo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('maestros', ['codigo' => 'M001']);
        $this->assertDatabaseMissing('maestros', ['codigo' => 'M002']);
        $this->assertDatabaseMissing('alumnos', ['codigo' => 'A001']);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DatabaseSeeder: los catálogos académicos siguen completos
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_database_seeder_conserva_los_catalogos_academicos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('materias', 14);
        $this->assertDatabaseCount('cursos', 5);

        // 13 materias de programa (Inglés es opcional) × 5 cursos.
        $this->assertDatabaseCount('detalle_cursos', 65);
    }

    #[Test]
    public function test_database_seeder_deja_el_programa_sin_docente_asignado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, DetalleCurso::whereNotNull('maestro_id')->count());
        $this->assertSame(0, AsignacionDocente::count());
    }

    #[Test]
    public function test_database_seeder_no_pisa_una_asignacion_docente_existente(): void
    {
        $this->seed(DatabaseSeeder::class);

        $detalle = DetalleCurso::whereNull('maestro_id')->firstOrFail();

        // maestros.user_id es NOT NULL, así que el docente necesita su User.
        $user = User::create([
            'name'     => 'Docente Real',
            'email'    => 'docente.real@itm.com',
            'password' => Hash::make(Str::random(40)),
            'role'     => 'docente',
        ]);

        $maestro = Maestro::create([
            'user_id'  => $user->id,
            'nombre'   => 'Docente',
            'apellido' => 'Real',
            'codigo'   => 'M999',
            'activo'   => true,
        ]);

        $detalle->update(['maestro_id' => $maestro->id]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($maestro->id, $detalle->fresh()->maestro_id);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DemoSeeder: datos de ejemplo sin credenciales conocidas
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_demo_seeder_crea_las_relaciones_completas(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('maestros', ['codigo' => 'M001', 'activo' => true]);
        $this->assertDatabaseHas('maestros', ['codigo' => 'M002', 'activo' => true]);
        $this->assertDatabaseCount('alumnos', 12);
        $this->assertDatabaseCount('inscripciones', 12);
    }

    #[Test]
    public function test_demo_seeder_no_usa_ninguna_contrasena_conocida(): void
    {
        $this->seed(DemoSeeder::class);

        $usuarios = User::all();

        $this->assertGreaterThan(0, $usuarios->count());

        foreach ($usuarios as $usuario) {
            $this->assertFalse(
                Hash::check('password', $usuario->password),
                "La cuenta {$usuario->email} no debe usar la contraseña 'password'."
            );
        }
    }

    #[Test]
    public function test_demo_seeder_no_pisa_la_contrasena_de_una_cuenta_existente(): void
    {
        $existente = User::create([
            'name'     => 'Docente Real',
            'email'    => 'docente@itm.com',
            'password' => Hash::make('mi-clave-original'),
            'role'     => 'docente',
        ]);

        $this->seed(DemoSeeder::class);

        $existente->refresh();

        $this->assertEquals('Docente Real', $existente->name);
        $this->assertTrue(Hash::check('mi-clave-original', $existente->password));
    }

    #[Test]
    public function test_demo_seeder_crea_asignaciones_docentes_activas(): void
    {
        $this->seed(DemoSeeder::class);

        // 13 materias por docente × 5 cursos = 65 asignaciones activas.
        $this->assertSame(65, AsignacionDocente::activas()->count());
        $this->assertSame(65, AsignacionDocente::count());
    }

    #[Test]
    public function test_demo_seeder_respeta_la_regla_de_visibilidad_por_asignacion_activa(): void
    {
        $this->seed(DemoSeeder::class);

        $carlos = Maestro::where('codigo', 'M001')->firstOrFail();
        $ana    = Maestro::where('codigo', 'M002')->firstOrFail();

        // Esto es exactamente el criterio que aplica el commit 4: el maestro
        // solo ve los detalles que tiene asignados con estado 'activa'.
        $visibles = function (int $maestroId): int {
            return DetalleCurso::whereIn('id', function ($query) use ($maestroId) {
                $query->select('detalle_curso_id')
                    ->from('asignaciones_docentes')
                    ->where('maestro_id', $maestroId)
                    ->where('estado', 'activa');
            })->count();
        };

        // Carlos: 7 materias × 5 cursos. Ana: 6 × 5. Total 65.
        $this->assertSame(35, $visibles($carlos->id));
        $this->assertSame(30, $visibles($ana->id));
    }

    #[Test]
    public function test_demo_seeder_es_idempotente(): void
    {
        $this->seed(DemoSeeder::class);

        $antes = [
            'users'               => User::count(),
            'maestros'            => Maestro::count(),
            'alumnos'             => Alumno::count(),
            'inscripciones'       => Inscripcion::count(),
            'asignaciones'        => AsignacionDocente::count(),
            'detalle_cursos'      => DetalleCurso::count(),
        ];

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $despues = [
            'users'               => User::count(),
            'maestros'            => Maestro::count(),
            'alumnos'             => Alumno::count(),
            'inscripciones'       => Inscripcion::count(),
            'asignaciones'        => AsignacionDocente::count(),
            'detalle_cursos'      => DetalleCurso::count(),
        ];

        $this->assertSame($antes, $despues, 'DemoSeeder no debe duplicar registros al reejecutarse.');
    }

    #[Test]
    public function test_demo_seeder_no_crea_cuentas_en_produccion(): void
    {
        // Application::environment() lee el binding 'env' del contenedor.
        app()['env'] = 'production';

        $this->withoutMockingConsoleOutput();

        try {
            $this->assertTrue(app()->environment('production'));

            $this->seed(DemoSeeder::class);

            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('maestros', 0);
        } finally {
            app()['env'] = 'testing';
        }
    }

    #[Test]
    public function test_demo_seeder_no_se_carga_con_db_seed_sin_clase(): void
    {
        // db:seed sin --class resuelve Database\Seeders\DatabaseSeeder, así
        // que DemoSeeder no puede colarse en una instalación normal. Se
        // comprueba por código en vez de por reflexión sobre el comando, que
        // es un detalle de implementación del framework.
        $this->assertNull(
            config('database.seeders'),
            'No debe registrarse una lista de seeders en config/database.php.'
        );

        $contenido = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        // DatabaseSeeder no delega en ningún otro seeder: así no puede crear
        // el administrador ni cargar datos de demostración.
        $this->assertStringNotContainsString('$this->call(', $contenido);
        $this->assertStringNotContainsString('new User(', $contenido);
        $this->assertStringNotContainsString('Hash::make', $contenido);
    }
}
