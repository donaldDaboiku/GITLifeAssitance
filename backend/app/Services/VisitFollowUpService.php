<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;

class VisitFollowUpService
{
    public function __construct(private ActivityService $activities, private WorkflowService $workflows) {}

    /**
     * @return list<array{key: string, label: string}>
     */
    public function offers(): array
    {
        return [
            ['key' => 'follow_up_task', 'label' => 'Create a follow-up task'],
            ['key' => 'quotation_reminder', 'label' => 'Create a quotation reminder'],
            ['key' => 'report_task', 'label' => 'Create a report task'],
            ['key' => 'next_visit', 'label' => 'Schedule the next visit'],
        ];
    }

    /**
     * @param  list<string>  $choices
     * @return list<Activity>
     */
    public function apply(Activity $visit, User $user, array $choices, ?string $nextVisitOn = null): array
    {
        $created = [];
        $timezone = $visit->timezone ?: 'Africa/Lagos';
        $base = CarbonImmutable::now($timezone)->toDateString();

        foreach (array_unique($choices) as $choice) {
            $activity = match ($choice) {
                'follow_up_task' => $this->activities->create($user, [
                    'type' => 'follow_up',
                    'title' => 'Follow up: '.$visit->title,
                    'due_on' => $base,
                    'timezone' => $timezone,
                    'contact_id' => $visit->contact_id,
                    'location' => $visit->location,
                    'reminder_offsets_minutes' => [0],
                ]),
                'quotation_reminder' => $this->activities->create($user, [
                    'type' => 'task',
                    'title' => 'Send quotation: '.$visit->title,
                    'due_on' => CarbonImmutable::now($timezone)->addDays(2)->toDateString(),
                    'timezone' => $timezone,
                    'contact_id' => $visit->contact_id,
                    'reminder_offsets_minutes' => [1440, 0],
                ]),
                'report_task' => $this->activities->create($user, [
                    'type' => 'task',
                    'title' => 'Write visit report: '.$visit->title,
                    'due_on' => CarbonImmutable::now($timezone)->addDay()->toDateString(),
                    'timezone' => $timezone,
                    'contact_id' => $visit->contact_id,
                    'reminder_offsets_minutes' => [0],
                ]),
                'next_visit' => $this->activities->create($user, [
                    'type' => 'visit',
                    'title' => 'Next visit: '.$visit->title,
                    'due_on' => $nextVisitOn ?: CarbonImmutable::now($timezone)->addDays(14)->toDateString(),
                    'timezone' => $timezone,
                    'contact_id' => $visit->contact_id,
                    'location' => $visit->location,
                    'reminder_offsets_minutes' => [1440, 0],
                ]),
                default => null,
            };

            if ($activity) {
                $this->workflows->link($user, $visit, $activity, 'follow_up_of');
                $created[] = $activity;
            }
        }

        return $created;
    }

    public function maybeOffer(ActivityOccurrence $occurrence): ?array
    {
        if ($occurrence->activity->type !== 'visit' || $occurrence->status !== 'completed') {
            return null;
        }

        return $this->offers();
    }
}
