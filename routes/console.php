<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jadwal tugas. Di Hostinger cukup SATU cron setiap menit:
|   php /home/uXXXX/domains/mutu.arthasafetyindonesia.com/smm/artisan schedule:run
*/

// Antrian (email, pindah berkas ke Drive) diproses tiap menit, berhenti sendiri bila kosong.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=5')->everyMinute()->withoutOverlapping(5);

Schedule::command('smm:files-push')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('smm:reminders')->dailyAt('07:00');
Schedule::command('smm:backup harian')->dailyAt('01:00')->withoutOverlapping(120);
Schedule::command('smm:backup bulanan')->monthlyOn(1, '02:00')->withoutOverlapping(120);
Schedule::command('smm:drive-health')->dailyAt('06:30');
Schedule::command('smm:cleanup-tmp')->hourly();
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::command('smm:verify-log')->weeklyOn(1, '06:00');
