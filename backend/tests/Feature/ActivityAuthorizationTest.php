<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_read_update_or_delete_another_users_activity(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $created = $this->actingAs($owner)->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Private task',
            'due_on' => '2026-09-30',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->actingAs($intruder)->getJson('/api/activities/'.$id)->assertForbidden();
        $this->actingAs($intruder)->putJson('/api/activities/'.$id, [
            'title' => 'Stolen',
            'due_on' => '2026-10-01',
        ])->assertForbidden();
        $this->actingAs($intruder)->deleteJson('/api/activities/'.$id)->assertForbidden();

        $this->actingAs($intruder)->getJson('/api/activities')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Private task']);

        $this->assertDatabaseHas('activities', ['id' => $id, 'title' => 'Private task']);
    }
}
