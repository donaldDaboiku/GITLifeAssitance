<?php

namespace Tests\Unit;

use App\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RecurrenceServiceTest extends TestCase
{
    public function test_monthly_31st_clamps_in_a_leap_year_and_a_common_year(): void
    {
        $service = new RecurrenceService;

        $this->assertSame(
            ['2024-01-31', '2024-02-29', '2024-03-31', '2024-04-30'],
            $this->localDates($service, '2024-01-31'),
        );

        $this->assertSame(
            ['2023-01-31', '2023-02-28', '2023-03-31', '2023-04-30'],
            $this->localDates($service, '2023-01-31'),
        );
    }

    public function test_business_days_skip_weekends_using_the_rrule_library(): void
    {
        $service = new RecurrenceService;
        $start = CarbonImmutable::parse('2026-09-25 09:00:00', 'Africa/Lagos');
        $until = CarbonImmutable::parse('2026-09-28 09:00:00', 'Africa/Lagos');

        $dates = array_map(
            fn (CarbonImmutable $utc) => $utc->timezone('Africa/Lagos')->toDateString(),
            $service->expand('FREQ=DAILY;BYDAY=MO,TU,WE,TH,FR', $start, $until),
        );

        $this->assertSame(['2026-09-25', '2026-09-28'], $dates);
    }

    /**
     * @return list<string>
     */
    private function localDates(RecurrenceService $service, string $startDate): array
    {
        $start = CarbonImmutable::parse($startDate.' 09:00:00', 'Africa/Lagos');
        $until = $start->addMonthsNoOverflow(3)->endOfDay();

        return array_map(
            fn (CarbonImmutable $utc) => $utc->timezone('Africa/Lagos')->toDateString(),
            $service->expand('FREQ=MONTHLY;BYMONTHDAY=31', $start, $until),
        );
    }
}
