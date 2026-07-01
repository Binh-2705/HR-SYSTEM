<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('app:health-check')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('queue:prune-failed --hours=168')
            ->dailyAt('02:00');

        $schedule->command('queue:retry all')
            ->twiceDaily(3, 15)
            ->withoutOverlapping();

        $schedule->command('leave:seed-annual-balances')
            ->yearlyOn(1, 1, '01:15')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
