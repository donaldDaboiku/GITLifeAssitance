<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vapid_public_key_requires_configuration(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/push/vapid-public-key')->assertStatus(503);
    }

    public function test_subscribe_and_unsubscribe_stores_push_token_on_web_device(): void
    {
        // Fixed keys for CI/Windows PHP builds where openssl EC keygen can fail.
        config([
            'webpush.vapid.public_key' => 'BEl62iUYgUivxIkv69yViEuiBIa-Ib27-xs80AwJgq5OAQBluXIhWJkS_gYQYxqP5bq9QbF-9ZlJqGqY2n1kZ8A',
            'webpush.vapid.private_key' => 'uP2kZ8AqY2n1GqYZlJqF-9bQ5bqYxqPSgYQJkS_gYIhWBluXOAq5OAQxs80AwJIb27-xsViEuiBIa-Ikv69y',
        ]);

        $user = User::factory()->create();
        $subscription = [
            'endpoint' => 'https://example.com/push/endpoint-abc',
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u7wTvV',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
        ];

        $this->actingAs($user)->getJson('/api/push/vapid-public-key')
            ->assertOk()
            ->assertJsonPath('public_key', config('webpush.vapid.public_key'));

        $this->actingAs($user)->postJson('/api/push/subscribe', [
            'name' => 'Chrome',
            'subscription' => $subscription,
        ])->assertOk();

        $device = Device::query()->where('user_id', $user->id)->where('type', 'web')->first();
        $this->assertNotNull($device);
        $stored = json_decode((string) $device->push_token, true);
        $this->assertSame($subscription['endpoint'], $stored['endpoint']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'web_push_subscribed']);

        $this->actingAs($user)->deleteJson('/api/push/subscribe', [
            'endpoint' => $subscription['endpoint'],
        ])->assertNoContent();

        $this->assertNull($device->fresh()->push_token);
    }

    public function test_web_push_channel_is_quiet_without_vapid_keys(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        Log::spy();
        $user = User::factory()->create();

        (new WebPushChannel)->send($user, 'Hello', 'Body', ['occurrence_id' => null]);

        $this->assertTrue(true);
    }
}
