<?php

namespace App\Console\Commands;

use App\Services\MorningSummaryService;
use Illuminate\Console\Command;

class DispatchMorningSummaries extends Command
{
    protected $signature = 'summaries:morning';

    protected $description = 'Send optional morning summaries at each user\'s chosen local time.';

    public function handle(MorningSummaryService $summaries): int
    {
        $count = $summaries->dispatchDue();
        $this->info("Sent {$count} morning summary(ies).");

        return self::SUCCESS;
    }
}
