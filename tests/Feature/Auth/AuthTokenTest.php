<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_valid_token_can_access_me(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');

        // Sanctum expiration (menit) dihitung dari created_at token.
        // Simulasikan token berumur lebih dari 8 jam (480 menit).
        $token->accessToken->forceFill([
            'created_at' => now()->subMinutes(481),
        ])->save();

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_fresh_token_is_still_valid_after_7_hours(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');

        $token->accessToken->forceFill([
            'created_at' => now()->subMinutes(419),
        ])->save();

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer invalid-token-abc')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
