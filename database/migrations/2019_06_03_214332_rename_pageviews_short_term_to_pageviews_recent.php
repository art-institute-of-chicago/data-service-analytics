<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenamePageviewsShortTermToPageviewsRecent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropColumn('pageviews_short_term');
        });
        Schema::table('artworks', function (Blueprint $table) {
            $table->integer('pageviews_recent')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropColumn('pageviews_recent');
        });
        Schema::table('artworks', function (Blueprint $table) {
            $table->integer('pageviews_short_term')->unsigned()->nullable();
        });
    }
}
