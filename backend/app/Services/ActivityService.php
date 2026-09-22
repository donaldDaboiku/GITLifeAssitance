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
            $dueLocal = $this->dueLocal($user, $data['due_on'], $timezone);

            $activity = new Activity([
                'user_id' => $user->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'timezone' => $timezone,
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? null,
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
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->writeTypeDetails($activity, $user, $data);
            $this->writeRule($activity, $user, $data['rrule'] ?? null);
            $activity->reminders()->delete();
            $this->writeReminders($activity, $user, $data['reminder_offsets_minutes'] ?? []);

            $activity->occurrences()->where('status', 'pending')->delete();
            $dueLocal = $this->dueLocal($user, $data['due_on'], $timezone);
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

        if ($activity->type === 'task') {
            $activity->task()->updateOrCreate(
                ['activity_id' => $activity->id],
                ['user_id' => $user->id],
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

    private function dueLocal(User $user, string $dueOn, string $timezone): CarbonImmutable
    {
        $time = substr((string) ($user->preference?->reminder_time ?? '09:00:00'), 0, 8);

        return CarbonImmutable::parse($dueOn.' '.$time, $timezone);
    }
}
