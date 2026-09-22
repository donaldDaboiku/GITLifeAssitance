<?php

namespace App\Policies;

use App\Models\ActivityOccurrence;
use App\Models\User;

class ActivityOccurrencePolicy
{
    public function update(User $user, ActivityOccurrence $occurrence): bool
    {
        return $occurrence->user_id === $user->id;
    }
}
