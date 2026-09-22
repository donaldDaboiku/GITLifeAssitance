<?php

namespace App\Services;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use RRule\RRule;

class RecurrenceService
{
    public const HORIZON_MONTHS = 12;

    /**
     * Expand an RFC 5545 RRULE between two local instants, inclusive.
     *
     * Monthly BYMONTHDAY 29/30/31 clamps to the last day of a shorter month
     * (Jan 31 → Feb 28/29 → Mar 31). php-rrule follows RFC 5545 and skips those
     * months, so that one case is adjusted with Carbon's month length.
     * Every other rule is expanded by php-rrule.
     *
     * @return list<CarbonImmutable> UTC instants
     */
    public function expand(string $rrule, CarbonImmutable $startLocal, CarbonImmutable $untilLocal): array
    {
        $rule = new RRule($rrule, $startLocal->toDateTimeImmutable());
        $parts = $rule->getRule();
        $monthDay = $this->singleMonthDay($parts);

        if ($this->frequency($parts) === 'MONTHLY' && $monthDay !== null && $monthDay >= 29) {
            return $this->expandClampedMonthDay($parts, $monthDay, $startLocal, $untilLocal);
        }

        $dates = $rule->getOccurrencesBetween(
            $startLocal->toDateTimeImmutable(),
            $untilLocal->toDateTimeImmutable(),
        );

        return array_map(
            fn (DateTimeInterface $date) => CarbonImmutable::instance($date)->utc()->microseconds(0),
            $dates,
        );
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return list<CarbonImmutable>
     */
    private function expandClampedMonthDay(array $parts, int $monthDay, CarbonImmutable $startLocal, CarbonImmutable $untilLocal): array
    {
        $interval = max(1, (int) ($parts['INTERVAL'] ?? 1));
        $onlyMonths = $this->monthFilter($parts['BYMONTH'] ?? null);
        $cursor = $startLocal->startOfMonth();
        $results = [];

        for ($guard = 0; $guard < 500 && $cursor->lessThanOrEqualTo($untilLocal); $guard++) {
            $month = (int) $cursor->month;

            if ($onlyMonths === null || in_array($month, $onlyMonths, true)) {
                $occurrence = $cursor
                    ->setDay(min($monthDay, $cursor->daysInMonth))
                    ->setTime($startLocal->hour, $startLocal->minute, $startLocal->second);

                if ($occurrence->greaterThanOrEqualTo($startLocal) && $occurrence->lessThanOrEqualTo($untilLocal)) {
                    $results[] = $occurrence->utc()->microseconds(0);
                }
            }

            $cursor = $cursor->addMonthsNoOverflow($interval);
        }

        return $results;
    }

    public function materialize(Activity $activity, CarbonImmutable $dueLocal): void
    {
        $until = $dueLocal->addMonthsNoOverflow(self::HORIZON_MONTHS)->endOfDay();
        $instants = $activity->recurrence
            ? $this->expand($activity->recurrence->rrule, $dueLocal, $until)
            : [$dueLocal->utc()->microseconds(0)];

        if ($instants === []) {
            $instants = [$dueLocal->utc()->microseconds(0)];
        }

        foreach ($instants as $utc) {
            $this->storeOccurrence($activity, $utc);
        }
    }

    public function ensureNext(Activity $activity, CarbonImmutable $afterLocal): void
    {
        if (! $activity->recurrence) {
            return;
        }

        $until = $afterLocal->addMonthsNoOverflow(self::HORIZON_MONTHS)->endOfDay();
        $instants = $this->expand($activity->recurrence->rrule, $afterLocal, $until);

        foreach ($instants as $instant) {
            if ($instant->greaterThan($afterLocal->utc())) {
                $this->storeOccurrence($activity, $instant);

                return;
            }
        }
    }

    private function storeOccurrence(Activity $activity, CarbonImmutable $utc): void
    {
        $utc = $utc->utc()->microseconds(0);
        $exists = $activity->occurrences()->where('due_at', $utc)->exists();

        if ($exists) {
            return;
        }

        $activity->occurrences()->create([
            'user_id' => $activity->user_id,
            'due_at' => $utc,
            'due_local_date' => $utc->timezone($activity->timezone)->toDateString(),
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function frequency(array $parts): string
    {
        return strtoupper((string) ($parts['FREQ'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function singleMonthDay(array $parts): ?int
    {
        $day = $parts['BYMONTHDAY'] ?? null;

        if (is_array($day)) {
            if (count($day) !== 1) {
                return null;
            }

            $day = $day[0];
        }

        if ($day === null || $day === '') {
            return null;
        }

        return (int) $day;
    }

    /**
     * @return list<int>|null
     */
    private function monthFilter(mixed $months): ?array
    {
        if ($months === null || $months === '') {
            return null;
        }

        $values = is_array($months) ? $months : explode(',', (string) $months);

        return array_map('intval', $values);
    }
}
