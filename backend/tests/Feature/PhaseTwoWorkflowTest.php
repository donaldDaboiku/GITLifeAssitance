<?php

namespace Tests\Feature;

use App\Models\ActivityLink;
use App\Models\NotificationDelivery;
use App\Models\ShoppingItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PhaseTwoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_birthday_reminders_fire_one_week_before_one_day_before_and_on_the_day(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'birthday',
            'title' => "John's birthday",
            'due_on' => '2026-10-15',
            'timezone' => 'Africa/Lagos',
            'rrule' => 'FREQ=YEARLY',
            'reminder_offsets_minutes' => [10080, 1440, 0],
        ])->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-10-08 09:05:00', 'Africa/Lagos'));
        $this->artisan('reminders:dispatch')->assertSuccessful();
        $this->assertSame(1, NotificationDelivery::query()->count());

        Carbon::setTestNow(Carbon::parse('2026-10-14 09:05:00', 'Africa/Lagos'));
        $this->artisan('reminders:dispatch')->assertSuccessful();
        $this->assertSame(2, NotificationDelivery::query()->count());

        Carbon::setTestNow(Carbon::parse('2026-10-15 09:05:00', 'Africa/Lagos'));
        $this->artisan('reminders:dispatch')->assertSuccessful();
        $this->assertSame(3, NotificationDelivery::query()->count());
    }

    public function test_birthday_with_gift_idea_creates_linked_shopping_item_and_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'birthday',
            'title' => "Ada's birthday",
            'due_on' => '2026-10-15',
            'timezone' => 'Africa/Lagos',
            'rrule' => 'FREQ=YEARLY',
            'reminder_offsets_minutes' => [10080],
            'gift' => [
                'idea' => 'Notebook',
                'budget_minor' => 500000,
            ],
        ])->assertCreated();

        $this->assertNotNull($response->json('gift_plan.shopping_item_id'));
        $this->assertNotNull($response->json('gift_plan.task_id'));

        $this->assertDatabaseHas('shopping_items', [
            'name' => 'Notebook',
            'estimated_price_minor' => 500000,
            'activity_id' => $response->json('data.id'),
        ]);

        $this->assertDatabaseHas('activities', [
            'id' => $response->json('gift_plan.task_id'),
            'type' => 'task',
            'title' => 'Buy gift: Notebook',
        ]);

        $this->assertTrue(
            ActivityLink::query()
                ->where('parent_activity_id', $response->json('data.id'))
                ->where('child_activity_id', $response->json('gift_plan.task_id'))
                ->where('relation', 'gift_for')
                ->exists()
        );
    }

    public function test_completing_a_visit_offers_and_creates_selected_follow_ups(): void
    {
        $user = User::factory()->create();
        $created = $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'visit',
            'title' => 'Client visit',
            'due_on' => '2026-09-22',
            'location' => 'Lagos',
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $occurrenceId = $created->json('data.occurrences.0.id');
        $activityId = $created->json('data.id');

        $complete = $this->actingAs($user)->postJson("/api/occurrences/{$occurrenceId}/complete")
            ->assertOk();

        $keys = collect($complete->json('follow_up_offers'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(
            ['follow_up_task', 'quotation_reminder', 'report_task', 'next_visit'],
            $keys,
        );

        $this->actingAs($user)->postJson("/api/activities/{$activityId}/visit-follow-ups", [
            'choices' => ['follow_up_task', 'quotation_reminder', 'report_task', 'next_visit'],
            'next_visit_on' => '2026-10-06',
        ])->assertCreated()->assertJsonCount(4, 'data');

        $this->assertDatabaseHas('activities', ['type' => 'follow_up', 'title' => 'Follow up: Client visit']);
        $this->assertDatabaseHas('activities', ['title' => 'Send quotation: Client visit']);
        $this->assertDatabaseHas('activities', ['title' => 'Write visit report: Client visit']);
        $this->assertDatabaseHas('activities', ['type' => 'visit', 'title' => 'Next visit: Client visit']);
    }

    public function test_shopping_totals_are_correct_to_the_kobo(): void
    {
        $user = User::factory()->create();
        $list = $this->actingAs($user)->postJson('/api/shopping-lists', [
            'name' => 'Market',
            'items' => [
                ['name' => 'Rice', 'estimated_price_minor' => 125050, 'actual_price_minor' => 120000],
                ['name' => 'Oil', 'estimated_price_minor' => 75025, 'purchased' => false],
                ['name' => 'Soap', 'estimated_price_minor' => 1000, 'actual_price_minor' => 1000, 'purchased' => true],
            ],
        ])->assertCreated();

        $this->assertSame(201075, $list->json('data.totals.estimated_minor'));
        $this->assertSame(121000, $list->json('data.totals.actual_minor'));
        $this->assertSame(200075, $list->json('data.totals.remaining_minor'));
    }

    public function test_dashboard_shows_expected_payment_totals_for_week_and_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Africa/Lagos'));
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Internet',
            'due_on' => '2026-09-25',
            'timezone' => 'Africa/Lagos',
            'payment' => ['amount_minor' => 2_000_000, 'currency' => 'NGN', 'payment_category' => 'utilities', 'payment_method' => 'transfer'],
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'payment',
            'title' => 'Rent',
            'due_on' => '2026-09-30',
            'timezone' => 'Africa/Lagos',
            'payment' => ['amount_minor' => 5_000_000, 'currency' => 'NGN'],
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $dashboard = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();
        $this->assertSame('expected', $dashboard->json('expected_payments.this_week.label'));
        $this->assertSame('expected', $dashboard->json('expected_payments.this_month.label'));
        $this->assertSame(2_000_000, $dashboard->json('expected_payments.this_week.amount_minor'));
        $this->assertSame(7_000_000, $dashboard->json('expected_payments.this_month.amount_minor'));
    }

    public function test_global_search_finds_across_activities_contacts_shopping_and_notes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Buy toner',
            'due_on' => '2026-09-23',
            'notes' => 'For the office printer',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/contacts', [
            'name' => 'Toner Supplier',
            'notes' => 'Lagos warehouse',
        ])->assertCreated();

        $list = $this->actingAs($user)->postJson('/api/shopping-lists', [
            'name' => 'Office',
            'items' => [['name' => 'Black toner cartridge', 'estimated_price_minor' => 150000]],
        ])->assertCreated();

        $this->assertDatabaseHas('shopping_items', ['shopping_list_id' => $list->json('data.id')]);

        $search = $this->actingAs($user)->getJson('/api/search?q=toner')->assertOk();
        $titles = collect($search->json('data'))->pluck('title')->all();

        $this->assertTrue(collect($titles)->contains(fn ($title) => str_contains(strtolower((string) $title), 'toner')));
        $this->assertGreaterThanOrEqual(3, count($search->json('data')));
        $this->assertNotNull(ShoppingItem::query()->where('name', 'like', '%toner%')->first());
    }
}
