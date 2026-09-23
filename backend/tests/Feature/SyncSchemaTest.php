<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncable_tables_have_uuid_version_and_tombstone_columns(): void
    {
        $tables = [
            'users',
            'devices',
            'activities',
            'activity_recurrences',
            'activity_occurrences',
            'activity_reminders',
            'payment_details',
            'tasks',
            'notifications',
            'notification_deliveries',
            'user_preferences',
            'audit_logs',
            'contacts',
            'shopping_lists',
            'shopping_items',
            'sync_mutations',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumns($table, ['id', 'version', 'updated_at', 'deleted_at']), $table);
        }

        $user = User::factory()->create();
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertSame(1, $user->version);
    }
}
