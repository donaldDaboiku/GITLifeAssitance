<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('summaries:morning')->everyMinute()->withoutOverlapping();
