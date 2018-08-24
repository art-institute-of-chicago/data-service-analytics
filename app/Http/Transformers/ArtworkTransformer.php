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

            // TODO: Dates?
        ];

        // Enables ?fields= functionality
        return parent::transform($data);

    }

}
