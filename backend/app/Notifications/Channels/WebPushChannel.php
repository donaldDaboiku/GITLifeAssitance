<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushChannel implements NotificationChannel
{
    public function send(User $user, string $title, string $body, array $context = []): void
    {
        $public = config('webpush.vapid.public_key');
        $private = config('webpush.vapid.private_key');
        if (! filled($public) || ! filled($private)) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) $public,
                'privateKey' => (string) $private,
            ],
        ];

        $webPush = new WebPush($auth);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'occurrence_id' => $context['occurrence_id'] ?? null,
            'url' => $context['url'] ?? '/',
        ], JSON_THROW_ON_ERROR);

        $devices = Device::query()
            ->where('user_id', $user->id)
            ->where('type', 'web')
            ->where('active', true)
            ->whereNotNull('push_token')
            ->get();

        foreach ($devices as $device) {
            $subscription = json_decode((string) $device->push_token, true);
            if (! is_array($subscription) || empty($subscription['endpoint'])) {
                continue;
            }

            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create($subscription),
                    $payload,
                );

                if ($report && method_exists($report, 'isSuccess') && ! $report->isSuccess()) {
                    $code = method_exists($report, 'getResponse') ? $report->getResponse()?->getStatusCode() : null;
                    if (in_array($code, [404, 410], true)) {
                        $device->forceFill(['push_token' => null])->save();
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('Web push failed', [
                    'device_id' => $device->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                Log::debug('Web push flush report', ['reason' => $report->getReason()]);
            }
        }
    }
}
