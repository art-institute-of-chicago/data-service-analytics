<?php

namespace App\Console\Commands;

use App\Artwork;
use Carbon\Carbon;
use League\Csv\Writer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

use Google_Client as Client;
use Google_Http_Batch as Batch;
use Google_Service_AnalyticsReporting as AnalyticsReportingService;
use Google_Service_AnalyticsReporting_DateRange as DateRange;
use Google_Service_AnalyticsReporting_Dimension as Dimension;
use Google_Service_AnalyticsReporting_Metric as Metric;
use Google_Service_AnalyticsReporting_DimensionFilter as DimensionFilter;
use Google_Service_AnalyticsReporting_DimensionFilterClause as DimensionFilterClause;
use Google_Service_AnalyticsReporting_OrderBy as OrderBy;
use Google_Service_AnalyticsReporting_ReportRequest as ReportRequest;
use Google_Service_AnalyticsReporting_GetReportsRequest as GetReportsRequest;

class ImportAnalyticsForArtwork extends AbstractCommand
{

    protected $signature = 'import:analytics-for-artwork
                            {artworks : Comma-separated list of artwork IDs}';

    protected $description = 'Imports analytics from Google for a group of artworks';

    protected $viewId;
    protected $authPath;

    public function handle()
    {

        $artworks = $this->argument('artworks');
        if ($artworks) {
            $artworks = explode(',', $artworks);
        }

        ini_set("memory_limit", "-1");
        set_time_limit(0);

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
        foreach (array_chunk($artworks, 5) as $artworksChunk) {

            $this->info(Carbon::now()->toDateTimeString() .': Working on batch ' .implode(',', $artworksChunk));

            $batch = $analytics->createBatch();
            $batch = $this->addToBatch($batch, $artworksChunk, $analytics);

            $results = $batch->execute();
            $tries = 1;

            while (!$this->isSuccessful($results) && $tries <= 10) {
                // Sleep for exponentially more time and try again
                $sleepFor = ($sleep * $sleepMultiplier) + rand(1000, 1000000);
                $this->info(Carbon::now()->toDateTimeString() .": Sleeping for " .number_format($sleepFor/1000000,3) ." seconds before trying again");
                usleep($sleepFor);
                $sleepMultiplier *= 2;
                if ($sleepMultiplier >= 2048) {
                    $sleepMultiplier = 1;
                }

                // Get a new client to refresh the auth tokens
                $client = $this->getClient();
                $analytics = new AnalyticsReportingService($client);

                $batch = $analytics->createBatch();
                $batch = $this->addToBatch($batch, $artworksChunk, $analytics);

                $results = $batch->execute();
                $tries++;
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

    }

    protected function getClient() {
        $client = new Client();
        $client->setApplicationName('Analytics Data Service');
        $client->setAuthConfig($this->authPath);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $client->setUseBatch(TRUE);

        return $client;
    }

    protected function getRequest($id = 0, $startDate = '2010-01-01') {

        // Create the DateRange object
        $dateRange = new DateRange();
        $dateRange->setStartDate($startDate);
        $dateRange->setEndDate('today');

        // Create the Dimensions object
        $dimension = new Dimension();
        $dimension->setName('ga:pagePath');

        // Create the Metrics object
        $metric = new Metric();
        $metric->setExpression('ga:pageviews');

        // Create the Dimension Filter
        $dimensionFilter = new DimensionFilter();
        $dimensionFilter->setDimensionName('ga:pagePath');
        $dimensionFilter->setOperator('REGEXP');
        $dimensionFilter->setExpressions('artwork[s]?/' .$id .'([/?].*)?');

        $dimensionFilterClause = new DimensionFilterClause();
        $dimensionFilterClause->setFilters($dimensionFilter);

        // Create the OrderBy object
        $ordering = new OrderBy();
        $ordering->setFieldName("ga:pageviews");
        $ordering->setOrderType("VALUE");
        $ordering->setSortOrder("DESCENDING");

        // Create the ReportRequest object
        $request = new ReportRequest();
        $request->setViewId($this->viewId);
        $request->setDateRanges($dateRange);
        $request->setDimensions($dimension);
        $request->setMetrics($metric);
        $request->setDimensionFilterClauses($dimensionFilterClause);
        $request->setOrderBys($ordering);

        $request->setSamplingLevel('SMALL');

        return $request;

    }

    protected function addToBatch($batch, $artworkIds, $analytics, $startDate = '2010-01-01') {

        foreach ($artworkIds as $id) {
            $request = $this->getRequest($id, $startDate);
            $report = $this->getReport($request, $analytics);
            $batch->add($report, "artwork-". $id);
        }

        return $batch;
    }

    protected function getReport($request, $analytics) {

        // Wrap our request in a multi-request clause (required?)
        $body = new GetReportsRequest();
        $body->setReportRequests( array($request) );

        // Issue the request and grab the response
        return $analytics->reports->batchGet($body);

    }

    protected function isSuccessful($results) {
        foreach ($results as $batchResult) {
            if (!property_exists($batchResult, 'reports')) {
                if ($batchResult->getCode() == 429
                    && $batchResult->getErrors()
                    && $batchResult->getErrors()[0]['reason'] == 'rateLimitExceeded') {
                    $this->info(Carbon::now()->toDateTimeString() .': Rate limit exceeded. Sleep two hours before trying again');
                    usleep(1000000*60*60*2 + rand(1000, 1000000));
                }
                return false;
            }
        }

        return true;
    }

    protected function isDone($results) {

        $rows = 0;
        foreach ($results as $batchResult) {
            $report = $batchResult->reports[0];

            $rows += count($report->getData()->getRows());
        }

        return $rows == 0;

    }

    protected function tally($results) {

        foreach ($results as $key => $batchResult) {
            $report = $batchResult->reports[0];

            $rows = $report->getData()->getRows();
            $totalViews = 0;
            foreach( $rows as $row ) {
                $metrics = $row->getMetrics();

                $totalViews += $metrics[0]->getValues()[0];
            }

            // Save to DB
            $id = substr($key, strrpos($key, '-')+1);
            $artwork = Artwork::firstOrNew(['id' => $id]);
            $artwork->pageviews = $totalViews;
            $artwork->save();
        }

    }
}
