<?php

namespace App\Services;

use App\Models\ActivityOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;

class DashboardService
{
    public function summary(User $user): array
    {
        $timezone = $user->preference?->timezone ?? 'Africa/Lagos';
        $today = CarbonImmutable::now($timezone)->toDateString();

        $pending = ActivityOccurrence::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('activity')
            ->with(['activity.paymentDetail', 'activity.user.preference'])
            ->orderBy('due_at')
            ->get();

        $date = fn (ActivityOccurrence $occurrence) => $occurrence->due_local_date->toDateString();

        return [
            'today' => $this->items($pending->filter(fn (ActivityOccurrence $occurrence) => $date($occurrence) <= $today)->values()),
            'due_today' => $this->items($pending->filter(fn (ActivityOccurrence $occurrence) => $date($occurrence) === $today)->values()),
            'upcoming' => $this->items($pending->filter(fn (ActivityOccurrence $occurrence) => $date($occurrence) > $today)->take(5)->values()),
            'payments' => $this->items($pending->filter(fn (ActivityOccurrence $occurrence) => $occurrence->activity->type === 'payment')->take(5)->values()),
            'tasks' => $this->items($pending->filter(fn (ActivityOccurrence $occurrence) => $occurrence->activity->type === 'task')->take(5)->values()),
        ];
    }

    private function items(mixed $occurrences): array
    {
        return collect($occurrences)->map(function (ActivityOccurrence $occurrence) {
            $activity = $occurrence->activity;
            $payment = $activity->paymentDetail;

            return [
                'occurrence_id' => $occurrence->id,
                'activity_id' => $activity->id,
                'type' => $activity->type,
                'title' => $activity->title,
                'due_at' => $occurrence->due_at?->toIso8601String(),
                'due_local_date' => $occurrence->due_local_date?->toDateString(),
                'status' => $occurrence->status,
                'computed_status' => $occurrence->computedStatus(),
                'amount_minor' => $payment?->amount_minor,
                'currency' => $payment?->currency,
                'amount_label' => $payment ? 'expected' : null,
            ];
        })->all();
    }
}
