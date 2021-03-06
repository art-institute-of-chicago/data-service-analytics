<?php

namespace App\Http\Transformers;

use Aic\Hub\Foundation\AbstractTransformer;

class ArtworkTransformer extends AbstractTransformer
{

    public function transform($artwork)
    {

        $data = [
            'id' => $artwork->id,
            'title' => $artwork->title,
            'pageviews' => $artwork->pageviews,
            'pageviews_recent' => $artwork->pageviews_recent,
            'indexed_at' => $artwork->indexed_at,
            'imported_at' => $artwork->imported_at,

            // TODO: Dates?
        ];

        // Enables ?fields= functionality
        return parent::transform($data);

    }

}
