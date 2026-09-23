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
        $nextMonthStart = $now->addMonthNoOverflow()->startOfMonth()->startOfDay();
        $nextMonthEnd = $now->addMonthNoOverflow()->endOfMonth()->endOfDay();

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

        $bucket = fn (int $amount): array => [
            'amount_minor' => $amount,
            'currency' => 'NGN',
            'label' => 'expected',
        ];

        return [
            'this_week' => $bucket($sum($weekStart, $weekEnd)),
            'this_month' => $bucket($sum($monthStart, $monthEnd)),
            'next_month' => $bucket($sum($nextMonthStart, $nextMonthEnd)),
        ];
    }
}
