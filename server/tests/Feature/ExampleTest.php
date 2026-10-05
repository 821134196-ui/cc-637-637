<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_serves_the_spa(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_spa_fallback_serves_index_for_client_routes(): void
    {
        $this->get('/datasets/1')->assertOk();
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->getJson('/api/nonexistent')->assertNotFound();
    }
}
