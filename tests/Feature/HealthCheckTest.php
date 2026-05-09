<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_health_check_returns_ok_when_database_is_reachable(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.app', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.database.connection', 'pgsql')
            ->assertJsonStructure(['status', 'checks', 'timestamp']);
    }
}
