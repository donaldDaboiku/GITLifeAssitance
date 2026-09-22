<?php

namespace App\Contracts;

use App\Models\User;

interface NotificationChannel
{
    public function send(User $user, string $title, string $body, array $context = []): void;
}
