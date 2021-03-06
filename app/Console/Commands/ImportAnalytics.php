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

class ImportAnalytics extends AbstractCommand
{

    protected $signature = 'import:analytics';

    protected $description = 'Imports analytics from Google';

    protected $viewId;

    protected $authPath;

    protected $filename = 'artwork-pageviews.csv';

    protected $pageviews = [];

    protected $csv;

    public function handle()
    {

        ini_set('memory_limit', '-1');
        set_time_limit(0);

        // Grab our config slash envars
        $this->viewId = env('GOOGLE_API_VIEW_ID');
        $this->authPath = storage_path(env('GOOGLE_API_AUTH_PATH'));

        // Prepare the CSV file
        $this->csv = Writer::createFromPath($this->getCsvPath(), 'w');

        // Mirror headers as exported from GA dashboard
        $this->csv->insertOne([
            'Page',
            'Pageviews',
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
        for ($pageToken = 0; $pageToken <= 400000; $pageToken += 5000) {

            $this->info(Carbon::now()->toDateTimeString() . ': Working on batch ' . $pageToken);

            $batch = $analytics->createBatch();
            $batch = $this->addToBatch($batch, $pageToken, $analytics);

            $results = $batch->execute();
            $tries = 1;

            while (!$this->isSuccessful($results) && $tries <= 10) {
                // Sleep for exponentially more time and try again
                $sleepFor = ($sleep * $sleepMultiplier) + rand(1000, 1000000);
                $this->info(Carbon::now()->toDateTimeString() . ': Sleeping for ' . number_format($sleepFor / 1000000, 3) . ' seconds before trying again');
                usleep($sleepFor);
                $sleepMultiplier *= 2;

                if ($sleepMultiplier >= 2048) {
                    $sleepMultiplier = 1;
                }

                // Get a new client to refresh the auth tokens
                $client = $this->getClient();
                $analytics = new AnalyticsReportingService($client);

                $batch = $analytics->createBatch();
                $batch = $this->addToBatch($batch, $pageToken, $analytics);

                $results = $batch->execute();
                $tries++;
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

        // Prepend an info header to match GA dashboard export
        $infoHeader = [
            '# ----------------------------------------',
            '# Collections (excluding exhibitions)',
            '# Top Artworks',
            '# 20100101-' . (new Carbon())->format('Ymd'),
            '# ----------------------------------------',
            '',
            '',
        ];

        $this->prepend(implode(PHP_EOL, $infoHeader), $this->getCsvPath());

    }

    protected function getClient() {
        $client = new Client();
        $client->setApplicationName('Analytics Data Service');
        $client->setAuthConfig($this->authPath);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $client->setUseBatch(true);

        return $client;
    }

    protected function getRequest($startDate = '2010-01-01') {

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
        $dimensionFilter->setExpressions('artwork[s]?/[0-9]+');

        $dimensionFilterClause = new DimensionFilterClause();
        $dimensionFilterClause->setFilters($dimensionFilter);

        // Create the OrderBy object
        $ordering = new OrderBy();
        $ordering->setFieldName('ga:pageviews');
        $ordering->setOrderType('VALUE');
        $ordering->setSortOrder('DESCENDING');

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

    /**
     * This modifies the instance $request object, but because we pass it a new
     * $nextPageToken each time, there's no effect in practice.
     */
    protected function getPaginatedRequest($nextPageToken = null, $startDate = '2010-01-01') {

        $request = $this->getRequest($startDate);

        $request->setPageToken($nextPageToken);

        return $request;

    }

    protected function addToBatch($batch, $pageToken, $analytics, $startDate = '2010-01-01') {

        // Batch five pages of queries together
        for ($i = 0; $i < 5; $i++) {
            $request = null;

            if ($pageToken === 0 && $i === 0) {
                $request = $this->getPaginatedRequest(null, $startDate);
            }
            else {
                $request = $this->getPaginatedRequest('' . (($i * 1000) + $pageToken), $startDate);
            }
            $report = $this->getReport($request, $analytics);
            $batch->add($report, 'starting-at-' . (($i * 1000) + $pageToken));
        }

        return $batch;
    }

    protected function getReport($request, $analytics) {

        // Wrap our request in a multi-request clause (required?)
        $body = new GetReportsRequest();
        $body->setReportRequests([$request]);

        // Issue the request and grab the response
        return $analytics->reports->batchGet($body);

    }

    protected function isSuccessful($results) {
        foreach ($results as $batchResult) {
            if (!property_exists($batchResult, 'reports')) {
                if ($batchResult->getCode() === 429
                    && $batchResult->getErrors()
                    && $batchResult->getErrors()[0]['reason'] === 'rateLimitExceeded') {
                    $this->info(Carbon::now()->toDateTimeString() . ': Rate limit exceeded. Sleep one hour before trying again');
                    usleep(1000000 * 60 * 60 + rand(1000, 1000000));
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

        return $rows === 0;

    }

    protected function tally($results) {

        foreach ($results as $batchResult) {
            $report = $batchResult->reports[0];

            $rows = $report->getData()->getRows();

            foreach($rows as $row) {
                $dimensions = $row->getDimensions();
                $metrics = $row->getMetrics();

                $path = $dimensions[0];
                $views = $metrics[0]->getValues()[0];

                preg_match('/artwork[s]?\/([0-9]+)/', $path, $matches);

                if ($matches) {
                    if (!Arr::has($this->pageviews, $matches[1])) {
                        $this->pageviews[$matches[1]] = 0;
                    }
                    $this->pageviews[$matches[1]] += $views;
                }
            }

        }

    }

    protected function saveReport() {

        foreach ($this->pageviews as $objectId => $views) {

            if ($objectId) {

                // Save to DB
                $artwork = Artwork::firstOrNew(['id' => $objectId]);
                $artwork->pageviews = $views;
                $artwork->save();

                $row = [
                    'Page' => '/artworks/' . $objectId,
                    'Pageviews' => number_format($views),
                ];

                $this->csv->insertOne($row);

            }

        }

    }

    protected function getCsvPath()
    {

        return Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . $this->filename;

    }

    // https://stackoverflow.com/a/1760579
    protected function prepend($string, $orig_filename) {
        $context = stream_context_create();
        $orig_file = fopen($orig_filename, 'rb', 1, $context);

        $temp_filename = tempnam(sys_get_temp_dir(), 'php_prepend_');
        file_put_contents($temp_filename, $string);
        file_put_contents($temp_filename, $orig_file, FILE_APPEND);

        fclose($orig_file);
        unlink($orig_filename);
        rename($temp_filename, $orig_filename);
    }

}
