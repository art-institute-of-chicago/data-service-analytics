<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateArtworksTable extends Migration
{

    public function up()
    {
        Schema::create('artworks', function (Blueprint $table) {
            $table->increments('id');
            $table->text('title');
            $table->integer('pageviews')->unsigned()->nullable();

            // TODO: Move these to a separate model? Perhaps with morphable?
            $table->dateTime('indexed_at')->nullable();
            $table->dateTime('imported_at')->nullable();
            $table->dateTime('analyzed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('artworks');
    }

}
