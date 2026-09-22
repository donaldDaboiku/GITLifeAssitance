<?php

namespace App\Services;

use App\Contracts\NotificationChannel;
use App\Models\ActivityOccurrence;
use App\Models\NotificationDelivery;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\InAppChannel;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

class ReminderService
{
    /** @var list<NotificationChannel> */
    private array $channels;

    public function __construct()
    {
        $this->channels = [new InAppChannel, new EmailChannel];
    }

    /**
     * Send every reminder whose fire time has passed and that has not been sent.
     * A missed minute is not replayed: the delivery key is unique, so catch-up sends once.
     */
    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $now = $now ?? CarbonImmutable::now();
        $sent = 0;

        $occurrences = ActivityOccurrence::query()
            ->where('status', 'pending')
            ->where('due_at', '<=', $now->addYear())
            ->with(['activity.reminders', 'activity.paymentDetail', 'activity.user'])
            ->get();

        foreach ($occurrences as $occurrence) {
            if ($occurrence->snoozed_until && $occurrence->snoozed_until->greaterThan($now)) {
                continue;
            }

            if ($occurrence->snoozed_until && $occurrence->snoozed_until->lessThanOrEqualTo($now)) {
                $key = $occurrence->id.':snooze:'.$occurrence->snoozed_until->utc()->format('YmdHis');
                if ($this->claimAndSend($occurrence, $key, 0, $now)) {
                    $sent++;
                }
            }

            foreach ($occurrence->activity->reminders as $reminder) {
                $fireAt = $occurrence->due_at->copy()->subMinutes($reminder->offset_minutes);

                if ($fireAt->greaterThan($now)) {
                    continue;
                }

                if ($occurrence->snoozed_until && $fireAt->lessThanOrEqualTo($occurrence->snoozed_until)) {
                    continue;
                }

                $key = $occurrence->id.':'.$reminder->offset_minutes;
                if ($this->claimAndSend($occurrence, $key, $reminder->offset_minutes, $now)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function claimAndSend(ActivityOccurrence $occurrence, string $key, int $offsetMinutes, CarbonImmutable $now): bool
    {
        try {
            NotificationDelivery::query()->create([
                'user_id' => $occurrence->user_id,
                'occurrence_id' => $occurrence->id,
                'offset_minutes' => $offsetMinutes,
                'delivery_key' => $key,
                'sent_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        $activity = $occurrence->activity;
        $localDate = $occurrence->due_local_date->format('d/m/Y');
        $title = 'Reminder: '.$activity->title;
        $body = $activity->title.' is due on '.$localDate.'.';

        if ($activity->paymentDetail) {
            $body .= ' Expected amount '.Money::format(
                $activity->paymentDetail->amount_minor,
                $activity->paymentDetail->currency,
            ).'.';
        }

        foreach ($this->channels as $channel) {
            $channel->send($activity->user, $title, $body, [
                'occurrence_id' => $occurrence->id,
                'delivery_key' => $key,
            ]);
        }

        return true;
    }
}
