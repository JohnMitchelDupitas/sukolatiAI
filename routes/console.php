<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// 1. Clean up old backups daily at 1:00 AM
Schedule::command('backup:clean')->daily()->at('01:00');

// 2. Run new backup daily at 1:30 AM
Schedule::command('backup:run')->daily()->at('01:30');
