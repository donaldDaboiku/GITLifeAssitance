<?php

namespace Tests\Feature;

use App\Models\ActivityOccurrence;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseFourSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_is_idempotent_and_pull_returns_changes_with_tombstones(): void
    {
        $user = User::factory()->create();
        $mutationId = (string) Str::uuid();
        $contactId = (string) Str::uuid();

        $first = $this->actingAs($user)->postJson('/api/sync/push', [
            'mutations' => [[
                'client_mutation_id' => $mutationId,
                'entity' => 'contact',
                'op' => 'upsert',
                'id' => $contactId,
                'data' => [
                    'name' => 'Ada',
                    'phone' => '08012345678',
                ],
            ]],
        ])->assertOk();

        $this->assertSame('applied', $first->json('results.0.status'));
        $this->assertDatabaseHas('contacts', ['id' => $contactId, 'name' => 'Ada']);
        $this->assertDatabaseCount('sync_mutations', 1);

        $this->actingAs($user)->postJson('/api/sync/push', [
            'mutations' => [[
                'client_mutation_id' => $mutationId,
                'entity' => 'contact',
                'op' => 'upsert',
                'id' => $contactId,
                'data' => ['name' => 'Should Not Change'],
            ]],
        ])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $this->assertDatabaseHas('contacts', ['id' => $contactId, 'name' => 'Ada']);
        $this->assertDatabaseCount('sync_mutations', 1);

        $deleteId = (string) Str::uuid();
        $this->actingAs($user)->postJson('/api/sync/push', [
            'mutations' => [[
                'client_mutation_id' => $deleteId,
                'entity' => 'contact',
                'op' => 'delete',
                'id' => $contactId,
            ]],
        ])->assertOk();

        $this->assertSoftDeleted('contacts', ['id' => $contactId]);

        $pull = $this->actingAs($user)->postJson('/api/sync/pull', [
            'cursor' => null,
            'limit' => 50,
        ])->assertOk();

        $tombstone = collect($pull->json('changes'))->firstWhere('id', $contactId);
        $this->assertNotNull($tombstone);
        $this->assertNotNull($tombstone['deleted_at']);
        $this->assertNull($tombstone['data']);
    }

    public function test_completed_occurrence_is_not_reopened_without_flag(): void
    {
        $user = User::factory()->create();
        $created = $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Call bank',
            'due_on' => '2026-09-25',
            'reminder_offsets_minutes' => [0],
        ])->assertCreated();

        $occurrenceId = $created->json('data.occurrences.0.id');
        $this->actingAs($user)->postJson("/api/occurrences/{$occurrenceId}/complete")->assertOk();
        $this->assertSame('completed', ActivityOccurrence::query()->findOrFail($occurrenceId)->status);

        $this->actingAs($user)->postJson('/api/sync/push', [
            'mutations' => [[
                'client_mutation_id' => (string) Str::uuid(),
                'entity' => 'occurrence',
                'op' => 'upsert',
                'id' => $occurrenceId,
                'data' => ['status' => 'pending'],
            ]],
        ])->assertOk();

        $this->assertSame('completed', ActivityOccurrence::query()->findOrFail($occurrenceId)->status);

        $this->actingAs($user)->postJson('/api/sync/push', [
            'mutations' => [[
                'client_mutation_id' => (string) Str::uuid(),
                'entity' => 'occurrence',
                'op' => 'upsert',
                'id' => $occurrenceId,
                'reopen' => true,
                'data' => ['status' => 'pending', 'completed_at' => null],
            ]],
        ])->assertOk();

        $this->assertSame('pending', ActivityOccurrence::query()->findOrFail($occurrenceId)->status);
    }

    public function test_user_cannot_pull_another_users_changes(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        Contact::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'name' => 'Secret',
        ]);

        $this->actingAs($intruder)->postJson('/api/sync/pull', [])
            ->assertOk()
            ->assertJsonCount(0, 'changes');
    }
}
