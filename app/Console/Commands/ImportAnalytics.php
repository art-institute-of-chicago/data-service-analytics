<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use League\Csv\Writer;
use Illuminate\Support\Facades\Storage;

use Google_Client as Client;
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

    private $viewId;

    private $authPath;

    private $request;

    private $filename = 'artwork-pageviews.csv';

    private $csv;

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
        $client = new Client();
        $client->setApplicationName('Analytics Data Service');
        $client->setAuthConfig($this->authPath);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);

        // Create a service instance
        $analytics = new AnalyticsReportingService($client);

        $nextPageToken = null;

        do {

            $this->warn("Requesting items using page token: " . $nextPageToken);

            // Get our request definition
            $request = $this->getPaginatedRequest($nextPageToken);

            // Use this while developing:
            // $request->setPageSize(20);

            $report = $this->getReport($request, $analytics);

            $this->saveReport($report);

            $nextPageToken = $report->getNextPageToken();

            // Uncomment this for debug:
            // $nextPageToken = null;

            // Sleep for 1.2 seconds to avoid pummeling the API
            usleep(1200000);

        } while (isset($nextPageToken));

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

    /**
     * This modifies the instance $request object, but because we pass it a new
     * $nextPageToken each time, there's no effect in practice.
     */
    private function getPaginatedRequest($nextPageToken = null) {

        $request = $this->getRequest();

        $request->setPageToken($nextPageToken);

        return $request;

    }

    private function getRequest() {

        if ($this->request) {
            return $this->request;
        }

        // Create the DateRange object
        $dateRange = new DateRange();
        $dateRange->setStartDate('2010-01-01');
        $dateRange->setEndDate('today');

        //Create the Dimensions object
        $dimension = new Dimension();
        $dimension->setName('ga:pagePath');

        // Create the Metrics object
        $metric = new Metric();
        $metric->setExpression('ga:pageviews');

        // Create the Dimension Filter
        $dimensionFilter = new DimensionFilter();
        $dimensionFilter->setDimensionName('ga:pagePath');
        $dimensionFilter->setOperator('REGEXP');
        $dimensionFilter->setExpressions('^/aic/collections/artwork/[0-9]+$');

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

        // Save the request instance for minor memory gains
        $this->request = $request;

        return $request;

    }

    private function getReport($request, $analytics) {

        // Wrap our request in a multi-request clause (required?)
        $body = new GetReportsRequest();
        $body->setReportRequests( array($request) );

        // Issue the request and grab the response
        $reports = $analytics->reports->batchGet($body);

        // We are only interested in one report
        $report = $reports[0];

        return $report;

    }

    private function saveReport($report) {

        $header = $report->getColumnHeader();

        $dimensionHeaders = $header->getDimensions();
        $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();

        $rows = $report->getData()->getRows();

        foreach( $rows as $row ) {

            $dimensions = $row->getDimensions();
            $metrics = $row->getMetrics();

            // TODO: Actually write this to CSV file
            $pagePath = $dimensions[0];
            $pageViews = $metrics[0]->getValues()[0];

            $this->info( $pagePath . ',' . $pageViews );

            $row = [
                'Page' => $pagePath,
                'Pageviews' => $pageViews,
            ];

            $this->csv->insertOne($row);

        }

    }

    private function getCsvPath()
    {

        return Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . $this->filename;

    }

    // https://stackoverflow.com/a/1760579
    private function prepend($string, $orig_filename) {
        $context = stream_context_create();
        $orig_file = fopen($orig_filename, 'r', 1, $context);

        $temp_filename = tempnam(sys_get_temp_dir(), 'php_prepend_');
        file_put_contents($temp_filename, $string);
        file_put_contents($temp_filename, $orig_file, FILE_APPEND);

        fclose($orig_file);
        unlink($orig_filename);
        rename($temp_filename, $orig_filename);
    }

}
