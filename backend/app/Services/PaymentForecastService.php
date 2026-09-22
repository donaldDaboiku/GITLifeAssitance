<?php

namespace App\Services;

use App\Models\ActivityOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;

class PaymentForecastService
{
    public function expectedTotals(User $user): array
    {
        $timezone = $user->preference?->timezone ?? 'Africa/Lagos';
        $now = CarbonImmutable::now($timezone);
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $weekEnd = $now->endOfWeek(CarbonImmutable::SUNDAY)->endOfDay();
        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfMonth()->endOfDay();

        $payments = ActivityOccurrence::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('activity', fn ($query) => $query->where('type', 'payment'))
            ->with('activity.paymentDetail')
            ->get();

        $sum = function (CarbonImmutable $start, CarbonImmutable $end) use ($payments, $timezone): int {
            return (int) $payments
                ->filter(function (ActivityOccurrence $occurrence) use ($start, $end, $timezone) {
                    $local = CarbonImmutable::parse($occurrence->due_local_date->toDateString(), $timezone)->startOfDay();

                    return $local->betweenIncluded($start->startOfDay(), $end->startOfDay());
                })
                ->sum(fn (ActivityOccurrence $occurrence) => $occurrence->activity->paymentDetail?->amount_minor ?? 0);
        };

        return [
            'this_week' => [
                'amount_minor' => $sum($weekStart, $weekEnd),
                'currency' => 'NGN',
                'label' => 'expected',
            ],
            'this_month' => [
                'amount_minor' => $sum($monthStart, $monthEnd),
                'currency' => 'NGN',
                'label' => 'expected',
            ],
        ];
    }
}
