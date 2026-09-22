<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Send due in-app and email reminders. Each reminder key is sent once.';

    public function handle(ReminderService $reminders): int
    {
        $count = $reminders->dispatchDue();
        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
