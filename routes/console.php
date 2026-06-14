<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly DB backup, committed with the site repo (only dumps when something changed).
\Illuminate\Support\Facades\Schedule::command('hondabase:dump')->dailyAt('00:00');
