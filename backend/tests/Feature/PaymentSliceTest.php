<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_internet_payment_creates_occurrences_and_paying_keeps_next_month_upcoming(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Internet',
            'due_on' => '2026-09-25',
            'timezone' => 'Africa/Lagos',
            'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=25',
            'reminder_offsets_minutes' => [4320, 1440],
            'payment' => [
                'amount_minor' => 2_000_000,
                'currency' => 'NGN',
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('payment_details', [
            'amount_minor' => 2_000_000,
            'currency' => 'NGN',
        ]);

        $occurrences = collect($created->json('data.occurrences'));
        $this->assertTrue($occurrences->contains('due_local_date', '2026-09-25'));
        $this->assertTrue($occurrences->contains('due_local_date', '2026-10-25'));
        $this->assertEqualsCanonicalizing([4320, 1440], $created->json('data.reminder_offsets_minutes'));

        $firstId = $occurrences->firstWhere('due_local_date', '2026-09-25')['id'];
        $paid = $this->actingAs($user)->postJson("/api/occurrences/{$firstId}/pay")->assertOk();

        $after = collect($paid->json('data.occurrences'));
        $this->assertSame('completed', $after->firstWhere('due_local_date', '2026-09-25')['status']);
        $this->assertCount(1, $after->where('due_local_date', '2026-09-25'));
        $next = $after->firstWhere('due_local_date', '2026-10-25');
        $this->assertSame('pending', $next['status']);
        $this->assertSame('upcoming', $next['computed_status']);
        $this->assertSame('FREQ=MONTHLY;BYMONTHDAY=25', $paid->json('data.rrule'));
    }

    public function test_snooze_does_not_change_the_recurrence_rule(): void
    {
        $user = User::factory()->create();
        $created = $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Call the bank',
            'due_on' => '2026-09-25',
            'rrule' => 'FREQ=WEEKLY;BYDAY=FR',
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $occurrenceId = $created->json('data.occurrences.0.id');

        $this->actingAs($user)->postJson("/api/occurrences/{$occurrenceId}/snooze", [
            'preset' => '1hour',
        ])->assertOk()->assertJsonPath('data.snoozed_until', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('activity_recurrences', [
            'activity_id' => $created->json('data.id'),
            'rrule' => 'FREQ=WEEKLY;BYDAY=FR',
        ]);
    }
}
