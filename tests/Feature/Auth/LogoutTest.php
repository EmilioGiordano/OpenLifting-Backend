<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_logout_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a');
        $tokenB = $user->createToken('device-b');

        $response = $this->withHeader('Authorization', "Bearer {$tokenA->plainTextToken}")
            ->postJson('/api/logout');

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenA->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenB->accessToken->id]);
    }

    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized();
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer 999|nonexistent-token-string')
            ->getJson('/api/user');

        $response->assertUnauthorized();
    }
}
