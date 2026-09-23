<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AiUsageEvent;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Device;
use App\Models\ReminderNotification;
use App\Models\ShoppingList;
use App\Models\SyncMutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PrivacyService
{
    /**
     * Portable JSON export of the signed-in user's data (no secrets).
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->load([
            'preference',
            'devices',
            'contacts',
            'shoppingLists.items',
            'activities' => fn ($q) => $q->withTrashed()->with([
                'recurrence',
                'reminders',
                'paymentDetail',
                'task.subtasks',
                'occurrences' => fn ($o) => $o->withTrashed(),
                'childLinks',
                'parentLinks',
            ]),
        ]);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'privacy_notice' => 'Export under the Nigeria Data Protection Act 2023. Passwords and API tokens are never included.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'preferences' => $user->preference?->toArray(),
            'devices' => $user->devices()->withTrashed()->get()->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'app_version' => $device->app_version,
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
                'active' => $device->active,
                'deleted_at' => $device->deleted_at?->toIso8601String(),
            ])->all(),
            'contacts' => Contact::withTrashed()->where('user_id', $user->id)->get()->toArray(),
            'shopping_lists' => ShoppingList::withTrashed()->where('user_id', $user->id)->with(['items' => fn ($q) => $q->withTrashed()])->get()->toArray(),
            'activities' => $user->activities->map(function (Activity $activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'title' => $activity->title,
                    'description' => $activity->description,
                    'category' => $activity->category,
                    'priority' => $activity->priority,
                    'timezone' => $activity->timezone,
                    'location' => $activity->location,
                    'contact_id' => $activity->contact_id,
                    'notes' => $activity->notes,
                    'metadata' => $activity->metadata,
                    'deleted_at' => $activity->deleted_at?->toIso8601String(),
                    'recurrence' => $activity->recurrence,
                    'reminders' => $activity->reminders,
                    'payment_detail' => $activity->paymentDetail,
                    'task' => $activity->task,
                    'occurrences' => $activity->occurrences,
                    'links' => [
                        'child' => $activity->childLinks,
                        'parent' => $activity->parentLinks,
                    ],
                ];
            })->values()->all(),
            'notifications' => ReminderNotification::withTrashed()->where('user_id', $user->id)->get(['id', 'title', 'body', 'delivery_key', 'read_at', 'created_at', 'deleted_at'])->toArray(),
            'sync_mutations' => SyncMutation::withTrashed()->where('user_id', $user->id)->get(['id', 'client_mutation_id', 'entity', 'entity_id', 'operation', 'applied_at', 'created_at'])->toArray(),
            'ai_usage' => AiUsageEvent::query()->where('user_id', $user->id)->get(['id', 'kind', 'provider', 'model', 'prompt_tokens', 'completion_tokens', 'created_at'])->toArray(),
            'audit_logs' => AuditLog::withTrashed()->where('user_id', $user->id)->get(['id', 'action', 'metadata', 'created_at'])->toArray(),
        ];

        AuditLog::write($user->id, 'data_exported');

        return $payload;
    }

    public function acceptPrivacyNotice(User $user): void
    {
        $user->preference()->updateOrCreate(
            ['user_id' => $user->id],
            ['privacy_notice_accepted_at' => now()],
        );
        AuditLog::write($user->id, 'privacy_notice_accepted');
    }

    public function deleteAccount(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password is incorrect.',
            ]);
        }

        DB::transaction(function () use ($user) {
            AuditLog::write($user->id, 'account_deleted', [
                'email' => $user->email,
            ]);

            $user->tokens()->delete();

            // Cascade FKs remove owned rows. Force-delete the user for erasure.
            $user->forceDelete();
        });
    }
}
