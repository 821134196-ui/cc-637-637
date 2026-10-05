<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_token_and_me_returns_user(): void
    {
        $user = User::factory()->create(['role' => 'researcher', 'password' => 'secret-password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);
        $response->assertOk()->assertJsonStructure(['token', 'user']);

        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_protected_routes_require_auth(): void
    {
        $this->getJson('/api/applications')->assertUnauthorized();
        $this->getJson('/api/grants')->assertUnauthorized();
    }
}
