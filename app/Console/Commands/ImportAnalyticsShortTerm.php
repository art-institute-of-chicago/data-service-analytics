<?php

namespace App\Console\Commands;

use App\Artwork;
use Carbon\Carbon;
use League\Csv\Writer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

use Google_Service_AnalyticsReporting as AnalyticsReportingService;

class ImportAnalyticsShortTerm extends ImportAnalytics
{

    protected $signature = 'import:analytics-short-term';

    protected $description = 'Imports last-three-months analytics from Google';

    protected $filename = 'artwork-pageviews-three-months.csv';

    public function handle()
    {

        ini_set("memory_limit", "-1");

        // Grab our config slash envars
        $this->viewId = env('GOOGLE_API_VIEW_ID');
        $this->authPath = storage_path(env('GOOGLE_API_AUTH_PATH'));

        // Prepare the CSV file
        $this->csv = Writer::createFromPath( $this->getCsvPath(), 'w' );

        // Mirror headers as exported from GA dashboard
        $this->csv->insertOne([
            'Page',
            'Pageviews'
        ]);

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

            $this->info('Working on batch ' .$pageToken);

            $batch = $analytics->createBatch();
            $batch = $this->addToBatch($batch, $pageToken, $analytics, Carbon::now()->subMonths(3)->toDateString());

            $results = $batch->execute();
            $tries = 1;

            while (!$this->isSuccessful($results) && $tries <= 4) {
                // Sleep for exponentially more time and try again
                $sleepFor = ($sleep * $sleepMultiplier) + rand(1000, 1000000);
                $this->info("Sleeping for " .number_format($sleepFor/1000000,3) ." seconds before trying again");
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
                throw new \Exception("Too many errors for this run.");
            }

            if ($this->isDone($results)) {
                break;
            }

            $this->tally($results);

            // Sleep for 1+ second to avoid pummeling the API
            usleep(1000000 + rand(1000, 1000000));
        }

        $this->saveReport();

        // Prepend an info header to match GA dashboard export
        $infoHeader = [
            '# ----------------------------------------',
            '# Collections (excluding exhibitions)',
            '# Top Artworks',
            '# ' .Carbon::now()->subMonths(3)->format('Ymd') .'-' . (new Carbon())->format('Ymd'),
            '# ----------------------------------------',
            '',
            '',
        ];

        $this->prepend(implode(PHP_EOL, $infoHeader), $this->getCsvPath());

    }

    protected function saveReport() {

        foreach( $this->pageviews as $objectId => $views ) {

            if ($objectId) {

                // Save to DB
                $artwork = Artwork::firstOrNew(['id' => $objectId]);
                $artwork->pageviews_short_term = $views;
                $artwork->save();

                $row = [
                    'Page' => '/artworks/' .$objectId,
                    'Pageviews' => number_format($views),
                ];

                $this->csv->insertOne($row);

            }

        }

    }

}
