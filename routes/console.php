<?php

use App\Jobs\CompleteDepartedTrips;
use App\Jobs\ExpireTripRequests;
use App\Jobs\SendTripReminders;
use Illuminate\Support\Facades\Schedule;

// Run with `php artisan schedule:work` in development (cron in production).
Schedule::job(new SendTripReminders)->everyFifteenMinutes()->withoutOverlapping();
Schedule::job(new CompleteDepartedTrips)->hourly()->withoutOverlapping();
Schedule::job(new ExpireTripRequests)->dailyAt('00:10');
