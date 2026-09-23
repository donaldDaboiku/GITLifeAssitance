<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityOccurrence;
use App\Models\ShoppingItem;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Suggestions only. Never creates activities unless the user confirms via confirm().
 */
class FollowUpSuggestionService
{
    public function __construct(private ActivityService $activities) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function suggest(User $user): array
    {
        $timezone = $user->preference?->timezone ?? 'Africa/Lagos';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $suggestions = [];

        $staleTasks = ActivityOccurrence::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('due_local_date', '<', $today->toDateString())
            ->whereHas('activity', fn ($q) => $q->whereIn('type', ['task', 'follow_up']))
            ->with('activity')
            ->orderBy('due_local_date')
            ->limit(10)
            ->get();

        foreach ($staleTasks as $occurrence) {
            $suggestions[] = [
                'id' => 'nudge:'.$occurrence->id,
                'kind' => 'nudge_overdue_task',
                'title' => 'Follow up on '.$occurrence->activity->title,
                'reason' => 'This task was due '.$occurrence->due_local_date->toDateString().' and is still open.',
                'requires_confirmation' => true,
                'payload' => [
                    'type' => 'follow_up',
                    'title' => 'Follow up: '.$occurrence->activity->title,
                    'due_on' => $today->toDateString(),
                    'timezone' => $timezone,
                    'reminder_offsets_minutes' => [0],
                    'notes' => 'Suggested follow-up. Linked to overdue task '.$occurrence->activity->id,
                    'source_activity_id' => $occurrence->activity_id,
                ],
            ];
        }

        $completedVisits = ActivityOccurrence::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $today->subDays(7))
            ->whereHas('activity', fn ($q) => $q->where('type', 'visit'))
            ->with('activity')
            ->limit(10)
            ->get();

        foreach ($completedVisits as $occurrence) {
            $hasFollowUp = Activity::query()
                ->where('user_id', $user->id)
                ->where('type', 'follow_up')
                ->where('notes', 'like', '%'.$occurrence->activity_id.'%')
                ->exists();

            if ($hasFollowUp) {
                continue;
            }

            $suggestions[] = [
                'id' => 'visit:'.$occurrence->id,
                'kind' => 'visit_follow_up',
                'title' => 'Create follow-up after '.$occurrence->activity->title,
                'reason' => 'Visit completed recently. Confirm to create a follow-up task.',
                'requires_confirmation' => true,
                'payload' => [
                    'type' => 'follow_up',
                    'title' => 'Follow up after '.$occurrence->activity->title,
                    'due_on' => $today->addDays(2)->toDateString(),
                    'timezone' => $timezone,
                    'contact_id' => $occurrence->activity->contact_id,
                    'reminder_offsets_minutes' => [0],
                    'notes' => 'Suggested after visit '.$occurrence->activity_id,
                    'source_activity_id' => $occurrence->activity_id,
                ],
            ];
        }

        $unboughtGifts = ShoppingItem::query()
            ->where('user_id', $user->id)
            ->where('purchased', false)
            ->whereNotNull('activity_id')
            ->with('list')
            ->limit(10)
            ->get();

        foreach ($unboughtGifts as $item) {
            $suggestions[] = [
                'id' => 'gift:'.$item->id,
                'kind' => 'buy_gift_reminder',
                'title' => 'Buy gift: '.$item->name,
                'reason' => 'Gift idea is still unpurchased.',
                'requires_confirmation' => true,
                'payload' => [
                    'type' => 'task',
                    'title' => 'Buy gift: '.$item->name,
                    'due_on' => $today->toDateString(),
                    'timezone' => $timezone,
                    'priority' => 'high',
                    'reminder_offsets_minutes' => [1440, 0],
                    'notes' => 'Suggested from shopping item '.$item->id,
                    'source_activity_id' => $item->activity_id,
                ],
            ];
        }

        return array_slice($suggestions, 0, 20);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function confirm(User $user, array $payload): Activity
    {
        abort_unless(($payload['type'] ?? null), 422, 'Suggestion payload is incomplete.');

        $data = [
            'type' => $payload['type'],
            'title' => $payload['title'],
            'due_on' => $payload['due_on'],
            'timezone' => $payload['timezone'] ?? $user->preference?->timezone ?? 'Africa/Lagos',
            'contact_id' => $payload['contact_id'] ?? null,
            'priority' => $payload['priority'] ?? 'normal',
            'reminder_offsets_minutes' => $payload['reminder_offsets_minutes'] ?? [0],
            'notes' => $payload['notes'] ?? null,
        ];

        if (in_array($data['type'], ['task', 'follow_up'], true)) {
            $data['task'] = [];
        }

        return $this->activities->create($user, $data);
    }
}
