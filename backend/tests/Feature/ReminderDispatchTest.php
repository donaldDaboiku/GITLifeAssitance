<?php

namespace Tests\Feature;

use App\Mail\ActivityReminderMail;
use App\Models\NotificationDelivery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_due_reminder_fires_once_and_a_missed_one_is_caught_up_once(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Africa/Lagos'));

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Internet',
            'due_on' => '2026-09-25',
            'timezone' => 'Africa/Lagos',
            'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=25',
            'reminder_offsets_minutes' => [4320, 1440],
            'payment' => ['amount_minor' => 2_000_000, 'currency' => 'NGN'],
        ])->assertCreated();

        $this->artisan('reminders:dispatch')->assertSuccessful();
        $this->assertSame(1, NotificationDelivery::query()->count());
        Mail::assertSent(ActivityReminderMail::class, 1);

        $this->artisan('reminders:dispatch')->assertSuccessful();
        $this->assertSame(1, NotificationDelivery::query()->count());
        Mail::assertSent(ActivityReminderMail::class, 1);

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Africa/Lagos'));
        $this->artisan('reminders:dispatch')->assertSuccessful();
        $this->assertSame(2, NotificationDelivery::query()->count());
    }
}
