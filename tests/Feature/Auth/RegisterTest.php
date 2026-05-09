<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registers_an_athlete_and_returns_a_token(): void
    {
        $athleteRoleId = Role::where('name', UserRole::ATHLETE->value)->value('id');

        $response = $this->postJson('/api/register', [
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'athlete',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'created_at']])
            ->assertJsonPath('user.email', 'juan@example.com')
            ->assertJsonPath('user.role', 'athlete');

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame($athleteRoleId, $user->role_id);
        $this->assertNotEquals('password123', $user->password, 'password must be hashed');
    }

    public function test_registers_an_instructor(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Coach Carla',
            'email' => 'carla@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'instructor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'instructor');

        $this->assertDatabaseHas('users', ['email' => 'carla@example.com']);
        $this->assertDatabaseMissing('athlete_profiles', ['user_id' => User::where('email', 'carla@example.com')->value('id')]);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Other',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'athlete',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_rejects_unknown_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_rejects_password_mismatch(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => 'athlete',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_rejects_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'athlete',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
