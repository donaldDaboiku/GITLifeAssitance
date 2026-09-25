<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_user_returns_json_401(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_register_login_and_device_token(): void
    {
        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/register', [
                'name' => 'Ada',
                'email' => 'ada@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', [
                'email' => 'ada@example.com',
                'password' => 'password123',
            ])
            ->assertOk();

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $token = $this->actingAs($user)->postJson('/api/devices', [
            'name' => 'Pixel',
            'type' => 'android',
            'app_version' => '0.1.0',
        ])->assertCreated()->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->assertDatabaseHas('audit_logs', ['action' => 'register']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login']);
    }
}
