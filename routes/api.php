<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->namespace('API')->group(function () {
    Route::get('videos', 'VideoController@index')->name('videos.index');
    Route::post('videos', 'VideoController@store')->name('videos.store');
    Route::get('videos/{id}', 'VideoController@show')->name('videos.show');
    Route::post('videos/{id}', 'VideoController@update')->name('videos.update');
    Route::delete('videos/{id}', 'VideoController@destroy')->name('videos.destroy');
});
