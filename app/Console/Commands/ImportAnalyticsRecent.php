<?php

namespace App\Console\Commands;

use App\Artwork;
use Carbon\Carbon;

use Google_Service_AnalyticsReporting as AnalyticsReportingService;

class ImportAnalyticsRecent extends ImportAnalytics
{

    protected $signature = 'import:analytics-recent';

    protected $description = 'Imports last-three-months analytics from Google';

    public function handle()
    {

        ini_set('memory_limit', '-1');

        // Grab our config slash envars
        $this->viewId = env('GOOGLE_API_VIEW_ID');
        $this->authPath = storage_path(env('GOOGLE_API_AUTH_PATH'));

        // Create a client instance
        $client = $this->getClient();

        // Create a service instance
        $analytics = new AnalyticsReportingService($client);

        $sleep = 2000000;
        $sleepMultiplier = 1;

        // Batch queries in groups of 5, to stay below the Google API limit
        // of 10 queries per second per IP address
        // This loop could probably go on for hundreds of thousands of records.
        // We'll take the first 200,000 results as a pretty good gauge of
        // the metrics.
        for ($pageToken = 0; $pageToken <= 200000; $pageToken += 5000) {

            $this->info('Working on batch ' . $pageToken);

            $batch = $analytics->createBatch();
            $batch = $this->addToBatch($batch, $pageToken, $analytics, Carbon::now()->subMonths(3)->toDateString());

            $results = $batch->execute();
            $tries = 1;

            while (!$this->isSuccessful($results) && $tries <= 4) {
                // Sleep for exponentially more time and try again
                $sleepFor = ($sleep * $sleepMultiplier) + rand(1000, 1000000);
                $this->info('Sleeping for ' . number_format($sleepFor / 1000000, 3) . ' seconds before trying again');
                usleep($sleepFor);
                $sleepMultiplier *= 2;

                if ($sleepMultiplier >= 512) {
                    $sleepMultiplier = 1;
                }

                // Get a new client to refresh the auth tokens
                $client = $this->getClient();
                $analytics = new AnalyticsReportingService($client);

                $batch = $analytics->createBatch();
                $batch = $this->addToBatch($batch, $pageToken, $analytics, Carbon::now()->subMonths(3)->toDateString());

                $results = $batch->execute();
            }

            if (!$this->isSuccessful($results)) {
                throw new \Exception('Too many errors for this run.');
            }

            if ($this->isDone($results)) {
                break;
            }

            $this->tally($results);

            // Sleep for 1+ second to avoid pummeling the API
            usleep(1000000 + rand(1000, 1000000));
        }

        $this->saveReport();
    }

    protected function saveReport() {

        foreach ($this->pageviews as $objectId => $views) {

            if ($objectId) {

                // Save to DB
                $artwork = Artwork::firstOrNew(['id' => $objectId]);
                $artwork->pageviews_recent = $views;
                $artwork->save();
            }

        }

    }

}
