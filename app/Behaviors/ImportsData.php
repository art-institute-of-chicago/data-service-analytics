<?php

namespace App\Behaviors;

trait ImportsData
{

    protected function fetch( $file, $decode = false ) {

        if( !$contents = @file_get_contents( $file ) )
        {
            throw new \Exception('Fetch failed: ' . $file );
        }

        return $decode ? json_decode( $contents ) : $contents;

    }

    protected function query( $endpoint, $page = 1, $limit = 100 )
    {
        $url = env('API_URL') . '/' . $endpoint . '?page=' . $page . '&limit=' . $limit . '&fields=id,title';

        $this->info( 'Querying: ' . $url );

        return $this->fetch( $url, true );

    }

    protected function import( $model, $endpoint, $current = 1 )
    {

        // Query for the first page + get page count
        $json = $this->query( $endpoint, $current );

        // Assumes the dataservice has standardized pagination
        $pages = $json->pagination->total_pages;
        // $total = $json->pagination->total;

        // $bar = $this->output->createProgressBar($pages);

        while( $current <= $pages )
        {
            foreach( $json->data as $datum )
            {
                $item = $this->save( $datum, $model );


                $this->info('Imported #' . $item->id . ' - ' . $item->title);

            }

            $current++;

            // $bar->advance();

            $json = $this->query( $endpoint, $current );

        }

        // $bar->finish();

    }

}
