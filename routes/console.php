<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscription:notifikasi-habis')->dailyAt('08:00');
Schedule::command('subscription:proses-grace-period')->dailyAt('00:01');
// Jalankan setiap hari jam 00:05 — buffer 5 menit dari tengah malam
Schedule::command('stock-opname:tutup-sesi-lama')->dailyAt('00:05');
