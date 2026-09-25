<?php

namespace Tests\Feature;

use App\Models\AiUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_internet_bill_returns_schema_and_confirm_creates_payment(): void
    {
        $user = User::factory()->create();

        $parse = $this->actingAs($user)->postJson('/api/assistant/parse', [
            'text' => 'Pay internet ₦20,000 monthly on the 25th',
        ])->assertOk();

        $proposal = $parse->json('proposal');
        $this->assertSame('create_activity', $proposal['intent']);
        $this->assertSame('payment', $proposal['type']);
        $this->assertSame(2_000_000, $proposal['amount_minor']);
        $this->assertSame('FREQ=MONTHLY;BYMONTHDAY=25', $proposal['rrule']);
        $this->assertSame([], $proposal['missing_fields']);
        $this->assertTrue($proposal['requires_confirmation']);
        $this->assertGreaterThanOrEqual(0.85, $proposal['confidence']);

        $this->actingAs($user)->postJson('/api/assistant/confirm', [
            'proposal' => $proposal,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'payment')
            ->assertJsonPath('data.payment.amount_minor', 2_000_000);

        $this->assertDatabaseHas('ai_usage_events', [
            'user_id' => $user->id,
            'kind' => 'confirm',
        ]);
    }

    public function test_parse_does_not_invent_missing_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/assistant/parse', [
            'text' => 'Pay the electricity bill monthly on the 10th',
        ])->assertOk()
            ->assertJsonPath('proposal.type', 'payment')
            ->assertJsonPath('proposal.amount_minor', null)
            ->assertJsonPath('proposal.missing_fields.0', 'amount_minor');

        $proposal = $this->actingAs($user)->postJson('/api/assistant/parse', [
            'text' => 'Pay the electricity bill monthly on the 10th',
        ])->json('proposal');

        $this->actingAs($user)->postJson('/api/assistant/confirm', [
            'proposal' => $proposal,
        ])->assertStatus(422);
    }

    public function test_confirm_accepts_user_filled_amount_and_due_day(): void
    {
        $user = User::factory()->create();

        $proposal = $this->actingAs($user)->postJson('/api/assistant/parse', [
            'text' => 'Pay electricity 9000 monthly',
        ])->assertOk()->json('proposal');

        $this->assertContains('due_day', $proposal['missing_fields']);

        $proposal['amount_minor'] = 900_000;
        $proposal['due_on'] = '2026-09-26';

        $this->actingAs($user)->postJson('/api/assistant/confirm', [
            'proposal' => $proposal,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'payment')
            ->assertJsonPath('data.payment.amount_minor', 900_000);
    }

    public function test_ask_uses_user_scoped_tools_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Mine DSTV',
            'due_on' => '2026-09-25',
            'payment' => ['amount_minor' => 100000, 'currency' => 'NGN'],
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $this->actingAs($other)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Secret Other Bill',
            'due_on' => '2026-09-25',
            'payment' => ['amount_minor' => 999999, 'currency' => 'NGN'],
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $ask = $this->actingAs($user)->postJson('/api/assistant/ask', [
            'question' => 'What payments do I have?',
        ])->assertOk();

        $this->assertContains('list_payments', $ask->json('tools_used'));
        $titles = collect($ask->json('data.payments'))->pluck('title');
        $this->assertTrue($titles->contains('Mine DSTV'));
        $this->assertFalse($titles->contains('Secret Other Bill'));
        $this->assertStringNotContainsString('Secret Other Bill', $ask->json('answer'));
    }

    public function test_monthly_usage_cap_is_enforced(): void
    {
        config(['ai.monthly_request_cap' => 2, 'ai.rate_per_minute' => 100]);
        $user = User::factory()->create();

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'kind' => 'parse',
            'provider' => 'heuristic',
            'model' => null,
        ]);
        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'kind' => 'parse',
            'provider' => 'heuristic',
            'model' => null,
        ]);

        $this->actingAs($user)->postJson('/api/assistant/parse', [
            'text' => 'Call Ada tomorrow',
        ])->assertStatus(429);
    }

    public function test_transcribe_requires_configured_provider(): void
    {
        $user = User::factory()->create();
        config(['ai.speech.provider' => null]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('voice.webm', 20, 'audio/webm');

        $this->actingAs($user)->post('/api/assistant/transcribe', [
            'audio' => $file,
        ])->assertStatus(503);
    }
}
