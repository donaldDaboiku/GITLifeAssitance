<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Mail\ActivityReminderMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannel
{
    public function send(User $user, string $title, string $body, array $context = []): void
    {
        Mail::to($user->email)->send(new ActivityReminderMail($title, $body));
    }
}
