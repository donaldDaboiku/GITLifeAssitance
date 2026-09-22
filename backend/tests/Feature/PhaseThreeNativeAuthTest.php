<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PhaseThreeNativeAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_login_returns_bearer_token_and_device(): void
    {
        $user = User::factory()->create([
            'email' => 'native@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/token-login', [
            'email' => 'native@example.com',
            'password' => 'password123',
            'device_name' => 'Windows desk',
            'device_type' => 'windows',
            'app_version' => '0.3.0',
        ])->assertOk();

        $token = $response->json('token');
        $deviceId = $response->json('device.id');

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('devices', [
            'id' => $deviceId,
            'user_id' => $user->id,
            'name' => 'Windows desk',
            'type' => 'windows',
            'app_version' => '0.3.0',
            'active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'native@example.com');

        $this->assertDatabaseHas('audit_logs', ['action' => 'token_login']);
    }

    public function test_device_push_token_update_and_revoke(): void
    {
        $user = User::factory()->create();
        $create = $this->actingAs($user)->postJson('/api/devices', [
            'name' => 'Pixel',
            'type' => 'android',
            'app_version' => '0.3.0',
        ])->assertCreated();

        $deviceId = $create->json('device.id');
        $token = $create->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/devices/'.$deviceId, [
                'push_token' => 'fcm-token-abc',
                'last_sync_at' => true,
            ])
            ->assertOk()
            ->assertJsonPath('device.push_token', 'fcm-token-abc');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/devices/'.$deviceId)
            ->assertNoContent();

        $this->assertSoftDeleted('devices', ['id' => $deviceId]);
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertTrue(Device::withTrashed()->findOrFail($deviceId)->active === false);
    }
}
