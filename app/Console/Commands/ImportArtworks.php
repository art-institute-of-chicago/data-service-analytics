<?php

namespace App\Console\Commands;

use App\Behaviors\ImportsData;
use App\Artwork;
use Carbon\Carbon;

class ImportArtworks extends AbstractCommand
{

    use ImportsData;

    protected $signature = 'import:artworks';

    protected $description = 'Imports artworks from the data aggregator';

    public function handle()
    {

        // TODO: Refactor ImportsData to use http_build_query($query)?
        $query = [
            'resources' => 'artworks',
            'size' => 100,
            'query' => [
                // TODO: Add timestamp query here? Or last_modified_at
            ],
            'fields' => [
                'id',
                'title',
            ],
        ];

        $this->import(Artwork::class, 'artworks');

    }

    protected function save($datum, $model)
    {

        $artwork = $model::findOrNew($datum->id);

        $artwork->id = $datum->id;
        $artwork->title = $datum->title;
        // $artwork->indexed_at = new Carbon( $datum->timestamp );
        $artwork->imported_at = new Carbon();

        $artwork->save();

        return $artwork;

    }

}
