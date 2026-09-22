<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityOccurrence;
use Carbon\CarbonImmutable;

class OccurrenceService
{
    public function __construct(
        private RecurrenceService $recurrence,
        private VisitFollowUpService $visitFollowUps,
    ) {}

    public function markPaid(ActivityOccurrence $occurrence): Activity
    {
        if ($occurrence->activity->type !== 'payment') {
            abort(422, 'Only a payment can be marked paid.');
        }

        return $this->finish($occurrence, 'completed');
    }

    /**
     * @return array{activity: Activity, follow_up_offers: list<array{key: string, label: string}>|null}
     */
    public function complete(ActivityOccurrence $occurrence): array
    {
        $activity = $this->finish($occurrence, 'completed');
        $occurrence->refresh();

        return [
            'activity' => $activity,
            'follow_up_offers' => $this->visitFollowUps->maybeOffer($occurrence),
        ];
    }

    public function skip(ActivityOccurrence $occurrence): Activity
    {
        return $this->finish($occurrence, 'skipped');
    }

    public function snooze(ActivityOccurrence $occurrence, ?string $preset, ?string $until): ActivityOccurrence
    {
        $timezone = $occurrence->activity->timezone ?: 'Africa/Lagos';
        $now = CarbonImmutable::now($timezone);
        $reminderTime = substr((string) ($occurrence->activity->user?->preference?->reminder_time ?? '09:00:00'), 0, 8);

        $local = match ($preset) {
            '15min' => $now->addMinutes(15),
            '1hour' => $now->addHour(),
            'tomorrow' => $now->addDay()->setTimeFromTimeString($reminderTime),
            'next_week' => $now->addDays(7)->setTimeFromTimeString($reminderTime),
            default => CarbonImmutable::parse((string) $until)->timezone($timezone),
        };

        $occurrence->snoozed_until = $local->utc();
        $occurrence->save();

        return $occurrence->fresh(['activity.recurrence']);
    }

    private function finish(ActivityOccurrence $occurrence, string $status): Activity
    {
        if (! in_array($occurrence->status, ['pending', 'in_progress'], true)) {
            abort(422, 'This occurrence is already '.$occurrence->status.'.');
        }

        $occurrence->status = $status;
        if ($status === 'completed') {
            $occurrence->completed_at = now();
        }
        $occurrence->save();

        $activity = $occurrence->activity->load('recurrence');
        $local = CarbonImmutable::parse($occurrence->due_at)->timezone($activity->timezone);
        $this->recurrence->ensureNext($activity, $local);

        return $activity->load(Activity::RELATIONS);
    }
}
