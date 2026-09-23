<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PrivacyNdpaTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_user_data_without_password_or_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Private note',
            'due_on' => '2026-09-25',
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $token = $user->createToken('export-test')->plainTextToken;

        $response = $this->actingAs($user)->get('/api/privacy/export')->assertOk();
        $json = $response->streamedContent();
        $payload = json_decode($json, true);

        $this->assertSame('ada@example.com', $payload['user']['email']);
        $this->assertTrue(collect($payload['activities'])->contains(fn ($row) => $row['title'] === 'Private note'));
        $this->assertArrayNotHasKey('password', $payload['user']);
        $this->assertStringNotContainsString('password123', $json);
        $this->assertStringNotContainsString(explode('|', $token)[1] ?? '___', $json);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_exported', 'user_id' => $user->id]);
    }

    public function test_user_cannot_export_another_users_data_via_auth_scope(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Owner only',
            'due_on' => '2026-09-25',
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $payload = json_decode(
            $this->actingAs($intruder)->get('/api/privacy/export')->assertOk()->streamedContent(),
            true,
        );

        $this->assertFalse(collect($payload['activities'])->contains(fn ($row) => $row['title'] === 'Owner only'));
        $this->assertSame($intruder->email, $payload['user']['email']);
    }

    public function test_account_deletion_requires_password_and_erases_user(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $userId = $user->id;
        $user->createToken('phone');

        $this->actingAs($user)->deleteJson('/api/privacy/account', [
            'password' => 'wrong',
            'confirm' => true,
        ])->assertStatus(422);

        $this->actingAs($user)->deleteJson('/api/privacy/account', [
            'password' => 'password123',
            'confirm' => true,
        ])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $userId)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account_deleted']);
    }

    public function test_privacy_notice_can_be_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/privacy/notice')
            ->assertOk()
            ->assertJsonPath('data.version', '2026-09-23');

        $this->actingAs($user)->postJson('/api/privacy/accept')->assertNoContent();
        $this->assertNotNull($user->preference()->first()->privacy_notice_accepted_at);
    }
}
