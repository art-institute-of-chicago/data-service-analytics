<?php

Route::get('/', function () {
    return redirect('/api/v1');
});

Route::group(['prefix' => 'v1'], function() {

    Route::get('artworks', 'ArtworkController@index');
    Route::get('artworks/{id}', 'ArtworkController@show');

});
