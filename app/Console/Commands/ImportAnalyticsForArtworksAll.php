<?php

namespace App\Console\Commands;

use App\Artwork;

class ImportAnalyticsForArtworksAll extends AbstractCommand
{

    protected $signature = 'import:analytics-for-artworks-all
                            {start-from : Artwork ID start sequentially start from}';

    protected $description = 'Imports analytics from Google all artworks';

    public function handle()
    {
        $startFrom = $this->argument('start-from') ?? 0;
        Artwork::where('id', '>=', $startFrom)->chunk(5, function ($artworks) {
            $this->call('import:analytics-for-artwork', ['artworks' => implode($artworks->pluck('id')->all(), ',')]);
        });
    }
}
