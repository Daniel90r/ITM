<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La raíz muestra la oferta académica pública (ya no redirige al login).
     */
    public function test_root_shows_public_offer(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
