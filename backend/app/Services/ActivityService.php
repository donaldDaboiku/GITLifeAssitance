<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    public function __construct(private RecurrenceService $recurrence) {}

    public function create(User $user, array $data): Activity
    {
        return DB::transaction(function () use ($user, $data) {
            $timezone = $data['timezone'] ?? $user->preference?->timezone ?? 'Africa/Lagos';
            $dueLocal = $this->dueLocal($user, $data['due_on'], $data['due_at_time'] ?? null, $timezone);

            $activity = new Activity([
                'user_id' => $user->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'timezone' => $timezone,
                'location' => $data['location'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            if (! empty($data['id'])) {
                $activity->id = $data['id'];
            }

            $activity->save();
            $this->writeTypeDetails($activity, $user, $data);
            $this->writeRule($activity, $user, $data['rrule'] ?? null);
            $this->writeReminders($activity, $user, $data['reminder_offsets_minutes'] ?? []);
            $this->recurrence->materialize($activity->load('recurrence'), $dueLocal);

            return $activity->load(Activity::RELATIONS);
        });
    }

    public function update(Activity $activity, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $data) {
            $user = $activity->user;
            $timezone = $data['timezone'] ?? $activity->timezone;

            $activity->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'priority' => $data['priority'] ?? $activity->priority,
                'timezone' => $timezone,
                'location' => $data['location'] ?? null,
                'contact_id' => $data['contact_id'] ?? $activity->contact_id,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? $activity->metadata,
            ])->save();

            $this->writeTypeDetails($activity, $user, $data);
            $this->writeRule($activity, $user, $data['rrule'] ?? null);
            $activity->reminders()->delete();
            $this->writeReminders($activity, $user, $data['reminder_offsets_minutes'] ?? []);

            $activity->occurrences()->where('status', 'pending')->delete();
            $dueLocal = $this->dueLocal($user, $data['due_on'], $data['due_at_time'] ?? null, $timezone);
            $this->recurrence->materialize($activity->load('recurrence'), $dueLocal);

            return $activity->load(Activity::RELATIONS);
        });
    }

    private function writeTypeDetails(Activity $activity, User $user, array $data): void
    {
        if ($activity->type === 'payment') {
            $activity->paymentDetail()->updateOrCreate(
                ['activity_id' => $activity->id],
                [
                    'user_id' => $user->id,
                    'amount_minor' => $data['payment']['amount_minor'],
                    'currency' => $data['payment']['currency'] ?? 'NGN',
                    'payment_category' => $data['payment']['payment_category'] ?? null,
                    'payment_method' => $data['payment']['payment_method'] ?? null,
                    'account_reference' => $data['payment']['account_reference'] ?? null,
                ],
            );
        }

        if (in_array($activity->type, ['task', 'follow_up'], true)) {
            $activity->task()->updateOrCreate(
                ['activity_id' => $activity->id],
                [
                    'user_id' => $user->id,
                    'follow_up_after_days' => $data['task']['follow_up_after_days'] ?? null,
                    'follow_up_rule' => $data['task']['follow_up_rule'] ?? null,
                ],
            );
        }
    }

    private function writeRule(Activity $activity, User $user, ?string $rrule): void
    {
        if ($rrule) {
            $activity->recurrence()->updateOrCreate(
                ['activity_id' => $activity->id],
                ['user_id' => $user->id, 'rrule' => $rrule],
            );

            return;
        }

        $activity->recurrence()?->delete();
    }

    /**
     * @param  list<int>  $offsets
     */
    private function writeReminders(Activity $activity, User $user, array $offsets): void
    {
        foreach (array_unique($offsets) as $offset) {
            $activity->reminders()->create([
                'user_id' => $user->id,
                'offset_minutes' => $offset,
            ]);
        }
    }

    private function dueLocal(User $user, string $dueOn, ?string $dueAtTime, string $timezone): CarbonImmutable
    {
        $time = $dueAtTime
            ? substr($dueAtTime, 0, 8)
            : substr((string) ($user->preference?->reminder_time ?? '09:00:00'), 0, 8);

        return CarbonImmutable::parse($dueOn.' '.$time, $timezone);
    }
}
