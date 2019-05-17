<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Use this to import third-party Artisan commands.
     *
     * @var array
     */
    protected $commands = [
        \Aic\Hub\Foundation\Commands\DatabaseReset::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('import:artworks')
            ->weeklyOn(7, '1:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/import-artworks-last-run.log'));

        $schedule->command('import:analytics')
            ->weeklyOn(1, '1:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/import-analytics-last-run.log'));

        $schedule->command('import:analytics-short-term')
            ->weeklyOn(2, '1:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/import-analytics-short-term-last-run.log'));
    }

    /**
     * Register the Closure based commands for the application.
     * By default, it loads all commands in `Commands` non-recursively.
     *
     * @return void
     */
    protected function commands()
    {

        $this->load(__DIR__.'/Commands');

    }
}
