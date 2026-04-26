<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // ✨ 1. We added this import!

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ✨ 2. And we add your automated schedule down here!
Schedule::command('interns:mark-absent')->weekdays()->dailyAt('23:59');