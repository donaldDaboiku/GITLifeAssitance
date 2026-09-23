<?php

namespace Tests\Feature;

use App\Models\ReminderNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PhaseSixSummaryForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_forecast_includes_next_month_matching_pending_payments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'Africa/Lagos'));
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'This month bill',
            'due_on' => '2026-09-15',
            'timezone' => 'Africa/Lagos',
            'payment' => ['amount_minor' => 1_000_000, 'currency' => 'NGN'],
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Next month bill',
            'due_on' => '2026-10-05',
            'timezone' => 'Africa/Lagos',
            'payment' => ['amount_minor' => 2_500_000, 'currency' => 'NGN'],
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $dash = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();
        $this->assertSame(1_000_000, $dash->json('expected_payments.this_month.amount_minor'));
        $this->assertSame(2_500_000, $dash->json('expected_payments.next_month.amount_minor'));
        $this->assertSame('expected', $dash->json('expected_payments.next_month.label'));
    }

    public function test_morning_summary_sends_at_chosen_time_and_stops_when_disabled(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->preference->update([
            'timezone' => 'Africa/Lagos',
            'morning_summary_enabled' => true,
            'morning_summary_time' => '07:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-23 07:00:30', 'Africa/Lagos')->utc());
        $this->artisan('summaries:morning')->assertSuccessful();
        $this->assertSame(1, ReminderNotification::query()->where('user_id', $user->id)->count());

        $this->artisan('summaries:morning')->assertSuccessful();
        $this->assertSame(1, ReminderNotification::query()->where('user_id', $user->id)->count());

        $user->preference->update(['morning_summary_enabled' => false]);
        Carbon::setTestNow(Carbon::parse('2026-09-24 07:00:30', 'Africa/Lagos')->utc());
        $this->artisan('summaries:morning')->assertSuccessful();
        $this->assertSame(1, ReminderNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_suggestions_require_confirmation_and_are_not_auto_created(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00:00', 'Africa/Lagos'));
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Call bank',
            'due_on' => '2026-09-20',
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $before = $user->activities()->count();
        $list = $this->actingAs($user)->getJson('/api/suggestions')->assertOk();
        $this->assertNotEmpty($list->json('data'));
        $this->assertTrue($list->json('data.0.requires_confirmation'));
        $this->assertSame($before, $user->activities()->count());

        $suggestion = $list->json('data.0');
        $this->actingAs($user)->postJson('/api/suggestions/confirm', [
            'suggestion_id' => $suggestion['id'],
            'payload' => $suggestion['payload'],
        ])->assertCreated();

        $this->assertSame($before + 1, $user->activities()->count());
    }

    public function test_preferences_can_toggle_morning_summary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/preferences', [
            'morning_summary_enabled' => true,
            'morning_summary_time' => '06:30',
            'due_soon_days' => 5,
        ])->assertOk()
            ->assertJsonPath('data.morning_summary_enabled', true)
            ->assertJsonPath('data.morning_summary_time', '06:30')
            ->assertJsonPath('data.due_soon_days', 5);
    }
}
