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
        // Import artworks once a week
        $schedule->command('import:artworks')
            ->weekly()->saturdays()->at('0:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/import-artworks-last-run.log'));

        // Run the last-three-months metrics every weekday, also only in production
        $schedule->command('import:analytics-recent')
            ->environments(['production'])
            ->weekdays()->at('0:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/import-analytics-recent-last-run.log'));

        // Import all historic analytics in production once a week
        // We only run this in production to save calls to the Google API
        //
        // This takes days to run and breaks part of the way through.
        // For now, run this periodically when we can monitor the import,
        // nudge it along, and make more improvements for efficiency.
        //
        // $schedule->command('import:analytics-for-artworks-all')
        //     ->environments(['production'])
        //     ->weekly()->saturdays()->at('1:00')
        //     ->withoutOverlapping()
        //     ->sendOutputTo(storage_path('logs/import-analytics-last-run.log'));
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
