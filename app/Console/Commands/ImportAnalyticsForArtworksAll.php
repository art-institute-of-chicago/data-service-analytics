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

class ImportAnalyticsForArtworksAll extends AbstractCommand
{

    protected $signature = 'import:analytics-for-artworks-all';

    protected $description = 'Imports analytics from Google all artworks';

    public function handle()
    {
        Artwork::chunk(5, function ($artworks) {
            $this->call('import:analytics-for-artwork', [ 'artworks' => implode($artworks->pluck('id')->all(), ',') ]);
        });
    }
}
