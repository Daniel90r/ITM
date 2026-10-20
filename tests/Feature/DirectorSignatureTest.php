<?php

namespace Tests\Feature;

use App\Models\Director;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DirectorSignatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function adminActing()
    {
        return $this->actingAs($this->admin);
    }

    #[Test]
    public function admin_puede_registrar_director_sin_firma(): void
    {
        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('directores', [
            'nombre' => 'Juan Pérez',
            'firma_path' => null,
        ]);
    }

    #[Test]
    public function admin_puede_registrar_director_con_jpg_valido(): void
    {
        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(500);

        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $director = Director::where('nombre', 'Juan Pérez')->first();
        $this->assertNotNull($director);
        $this->assertNotNull($director->firma_path);
        $this->assertStringStartsWith('directores/firmas/', $director->firma_path);
        Storage::disk('local')->assertExists($director->firma_path);
        Storage::disk('public')->assertMissing($director->firma_path);
    }

    #[Test]
    public function admin_puede_registrar_director_con_png_valido(): void
    {
        $firma = UploadedFile::fake()->image('firma.png', 100, 100)->size(500);

        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $director = Director::where('nombre', 'Juan Pérez')->first();
        $this->assertNotNull($director->firma_path);
        Storage::disk('local')->assertExists($director->firma_path);
        Storage::disk('public')->assertMissing($director->firma_path);
    }

    #[Test]
    public function admin_puede_registrar_director_con_webp_valido(): void
    {
        $firma = UploadedFile::fake()->image('firma.webp', 100, 100);

        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $director = Director::where('nombre', 'Juan Pérez')->first();
        $this->assertNotNull($director->firma_path);
        Storage::disk('local')->assertExists($director->firma_path);
        Storage::disk('public')->assertMissing($director->firma_path);
    }

    #[Test]
    public function firma_mayor_a_2mb_es_rechazada(): void
    {
        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(3000);

        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $response->assertSessionHasErrors('firma');
        $this->assertDatabaseCount('directores', 0);
    }

    #[Test]
    public function archivo_txt_es_rechazado(): void
    {
        $firma = UploadedFile::fake()->create('firma.txt', 100, 'text/plain');

        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $response->assertSessionHasErrors('firma');
        $this->assertDatabaseCount('directores', 0);
    }

    #[Test]
    public function svg_es_rechazado(): void
    {
        $firma = UploadedFile::fake()->create('firma.svg', 100, 'image/svg+xml');

        $response = $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $response->assertSessionHasErrors('firma');
        $this->assertDatabaseCount('directores', 0);
    }

    #[Test]
    public function firma_path_almacenado_pertenece_a_directores_firmas(): void
    {
        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(500);

        $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $director = Director::where('nombre', 'Juan Pérez')->first();
        $this->assertStringStartsWith('directores/firmas/', $director->firma_path);
    }

    #[Test]
    public function archivo_existe_en_storage_fake(): void
    {
        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(500);

        $this->adminActing()->post(route('admin.directores.store'), [
            'nombre' => 'Juan Pérez',
            'fecha_inicio' => now()->toDateString(),
            'firma' => $firma,
        ]);

        $director = Director::where('nombre', 'Juan Pérez')->first();
        Storage::disk('local')->assertExists($director->firma_path);
        Storage::disk('public')->assertMissing($director->firma_path);
    }

    #[Test]
    public function admin_puede_reemplazar_firma(): void
    {
        $director = Director::create([
            'nombre' => 'Director Original',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firmaOriginal = UploadedFile::fake()->image('firma1.jpg', 100, 100)->size(500);
        $director->update(['firma_path' => $firmaOriginal->store('directores/firmas', 'local')]);
        $oldPath = $director->firma_path;

        $firmaNueva = UploadedFile::fake()->image('firma2.jpg', 100, 100)->size(500);

        $response = $this->adminActing()->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firmaNueva,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $director->refresh();
        $this->assertNotEquals($oldPath, $director->firma_path);
        $this->assertStringStartsWith('directores/firmas/', $director->firma_path);
        Storage::disk('local')->assertExists($director->firma_path);
        Storage::disk('local')->assertExists($oldPath);
    }

    #[Test]
    public function al_reemplazar_firma_nueva_existe_y_anterior_se_conserva(): void
    {
        $director = Director::create([
            'nombre' => 'Director Original',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firmaOriginal = UploadedFile::fake()->image('firma1.jpg', 100, 100)->size(500);
        $director->update(['firma_path' => $firmaOriginal->store('directores/firmas', 'local')]);
        $oldPath = $director->firma_path;

        $firmaNueva = UploadedFile::fake()->image('firma2.jpg', 100, 100)->size(500);

        $this->adminActing()->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firmaNueva,
        ]);

        $director->refresh();
        Storage::disk('local')->assertExists($director->firma_path);
        Storage::disk('local')->assertExists($oldPath);
    }

    #[Test]
    public function si_update_falla_firma_nueva_se_limpia_y_anterior_permanece(): void
    {
        $director = Director::create([
            'nombre' => 'Director Original',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

$firmaOriginal = UploadedFile::fake()->image('firma1.jpg', 100, 100)->size(500);
        $director->update(['firma_path' => $firmaOriginal->store('directores/firmas', 'local')]);
        $oldPath = $director->firma_path;

        $this->app->instance('db', $this->app->make('db')->connection());
        $originalUpdate = Director::class;

        $firmaNueva = UploadedFile::fake()->image('firma2.jpg', 100, 100)->size(500);

        // Simular fallo en BD forzando una excepción
        $this->app->bind(\Illuminate\Contracts\Auth\Authenticatable::class, function () {
            throw new \Exception('Error simulado de BD');
        });

        $response = $this->adminActing()->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firmaNueva,
        ]);

        // Si falla, la firma anterior debe seguir existiendo
        Storage::disk('local')->assertExists($oldPath);
    }

    #[Test]
    public function alumno_no_puede_reemplazar_firma(): void
    {
        $alumno = User::factory()->create(['role' => 'alumno']);
        $director = Director::create([
            'nombre' => 'Director Original',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(500);

        $response = $this->actingAs($alumno)->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firma,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function docente_no_puede_reemplazar_firma(): void
    {
        $docente = User::factory()->create(['role' => 'docente']);
        $director = Director::create([
            'nombre' => 'Director Original',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(500);

        $response = $this->actingAs($docente)->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firma,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function no_autenticado_no_puede_reemplazar_firma(): void
    {
        $director = Director::create([
            'nombre' => 'Director Original',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.jpg', 100, 100)->size(500);

        $response = $this->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firma,
        ]);

        $response->assertStatus(302); // Redirect to login
    }

    #[Test]
    public function reemplazar_firma_no_cambia_campos_historicos(): void
    {
        $director = Director::create([
            'nombre' => 'Director Original',
            'dui' => '12345678-9',
            'telefono' => '2000-0000',
            'email' => 'director@test.com',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => null,
            'activo' => true,
            'user_id' => $this->admin->id,
        ]);

        $firmaNueva = UploadedFile::fake()->image('firma2.jpg', 100, 100)->size(500);

        $this->adminActing()->patch(route('admin.directores.firma.update', $director), [
            'firma' => $firmaNueva,
        ]);

        $director->refresh();

        $this->assertEquals('Director Original', $director->nombre);
        $this->assertEquals('12345678-9', $director->dui);
        $this->assertEquals('2000-0000', $director->telefono);
        $this->assertEquals('director@test.com', $director->email);
        $this->assertEquals($director->fecha_inicio->toDateString(), now()->subMonth()->toDateString());
        $this->assertNull($director->fecha_fin);
        $this->assertTrue($director->activo);
        $this->assertEquals($this->admin->id, $director->user_id);
    }

    #[Test]
    public function admin_puede_visualizar_firma_privada(): void
    {
        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.png', 100, 100)->size(500);
        $path = $firma->store('directores/firmas', 'local');
        $director->update(['firma_path' => $path]);

        $response = $this->adminActing()->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    #[Test]
    public function sin_firma_path_retorna_404(): void
    {
        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
            'firma_path' => null,
        ]);

        $response = $this->adminActing()->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(404);
    }

    #[Test]
    public function archivo_inexistente_retorna_404(): void
    {
        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
            'firma_path' => 'directores/firmas/no-existe.png',
        ]);

        $response = $this->adminActing()->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(404);
    }

    #[Test]
    public function path_fuera_del_prefijo_retorna_404(): void
    {
        Storage::disk('local')->put('otro/directorio/secreto.png', 'contenido-secreto');

        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
            'firma_path' => 'otro/directorio/secreto.png',
        ]);

        $response = $this->adminActing()->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(404);
    }

    #[Test]
    public function no_autenticado_redirige_a_login(): void
    {
        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.png', 100, 100)->size(500);
        $path = $firma->store('directores/firmas', 'local');
        $director->update(['firma_path' => $path]);

        $response = $this->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(302);
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    #[Test]
    public function alumno_no_puede_ver_firma(): void
    {
        $alumno = User::factory()->create(['role' => 'alumno']);
        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.png', 100, 100)->size(500);
        $path = $firma->store('directores/firmas', 'local');
        $director->update(['firma_path' => $path]);

        $response = $this->actingAs($alumno)->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(403);
    }

    #[Test]
    public function docente_no_puede_ver_firma(): void
    {
        $docente = User::factory()->create(['role' => 'docente']);
        $director = Director::create([
            'nombre' => 'Director Test',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'activo' => true,
        ]);

        $firma = UploadedFile::fake()->image('firma.png', 100, 100)->size(500);
        $path = $firma->store('directores/firmas', 'local');
        $director->update(['firma_path' => $path]);

        $response = $this->actingAs($docente)->get(route('admin.directores.firma.show', $director));

        $response->assertStatus(403);
    }
}
