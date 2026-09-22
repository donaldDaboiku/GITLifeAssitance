<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function view(User $user, Activity $activity): bool
    {
        return $activity->user_id === $user->id;
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->view($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->view($user, $activity);
    }
}
