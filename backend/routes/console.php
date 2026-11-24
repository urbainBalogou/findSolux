<?php

use Illuminate\Support\Facades\Schedule;

/*
 * En production, une seule entrée cron : `* * * * * php artisan schedule:run`.
 * Les notifications partent par la file d'attente : `php artisan queue:work`.
 */
Schedule::command('requests:expire')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('subscriptions:process')->everyFiveMinutes()->withoutOverlapping(15)->onOneServer();
