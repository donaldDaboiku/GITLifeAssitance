<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Models\ReminderNotification;
use App\Models\User;

class InAppChannel implements NotificationChannel
{
    public function send(User $user, string $title, string $body, array $context = []): void
    {
        ReminderNotification::query()->create([
            'user_id' => $user->id,
            'occurrence_id' => $context['occurrence_id'] ?? null,
            'delivery_key' => $context['delivery_key'],
            'title' => $title,
            'body' => $body,
        ]);
    }
}
