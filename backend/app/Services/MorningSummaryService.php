<?php

namespace App\Services;

use App\Models\ReminderNotification;
use App\Models\User;
use App\Models\UserPreference;
use App\Notifications\Channels\EmailChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MorningSummaryService
{
    public function __construct(private DashboardService $dashboard) {}

    public function dispatchDue(?CarbonImmutable $nowUtc = null): int
    {
        $nowUtc = $nowUtc ?? CarbonImmutable::now('UTC');
        $sent = 0;

        $prefs = UserPreference::query()
            ->where('morning_summary_enabled', true)
            ->with('user')
            ->get();

        foreach ($prefs as $preference) {
            $user = $preference->user;
            if (! $user) {
                continue;
            }

            $timezone = $preference->timezone ?: 'Africa/Lagos';
            $local = $nowUtc->timezone($timezone);
            $wanted = substr((string) $preference->morning_summary_time, 0, 5);
            $current = $local->format('H:i');

            if ($wanted !== $current) {
                continue;
            }

            if ($this->deliver($user, $local->toDateString())) {
                $sent++;
            }
        }

        return $sent;
    }

    public function deliver(User $user, string $localDate): bool
    {
        $key = 'morning:'.$user->id.':'.$localDate;

        return DB::transaction(function () use ($user, $key, $localDate) {
            try {
                ReminderNotification::query()->create([
                    'user_id' => $user->id,
                    'occurrence_id' => null,
                    'delivery_key' => $key,
                    'title' => 'Morning summary',
                    'body' => $this->buildBody($user, $localDate),
                ]);
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            $notification = ReminderNotification::query()->where('delivery_key', $key)->first();
            if ($notification) {
                (new EmailChannel)->send($user, $notification->title, $notification->body, [
                    'delivery_key' => $key,
                ]);
            }

            return true;
        });
    }

    private function buildBody(User $user, string $localDate): string
    {
        $summary = $this->dashboard->summary($user);
        $lines = [
            'Morning summary for '.$localDate.' (planned / expected only):',
            'Due today: '.count($summary['due_today']),
            'Upcoming: '.count($summary['upcoming']),
            'Expected payments this week: ₦'.number_format(($summary['expected_payments']['this_week']['amount_minor'] ?? 0) / 100, 2),
            'Expected payments this month: ₦'.number_format(($summary['expected_payments']['this_month']['amount_minor'] ?? 0) / 100, 2),
            'Expected payments next month: ₦'.number_format(($summary['expected_payments']['next_month']['amount_minor'] ?? 0) / 100, 2),
        ];

        foreach (array_slice($summary['due_today'], 0, 5) as $item) {
            $lines[] = '- '.$item['title'].' ('.$item['type'].')';
        }

        return implode("\n", $lines);
    }
}
