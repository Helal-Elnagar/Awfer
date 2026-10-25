<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::group(['middleware' => ['web', 'HtmlMinifier']], function () {

  Route::get('/', 'IntroController@index')->name('intro');

  Route::get('/privacy-policy', 'IntroController@privacyPolicy')->name('IntroPrivacyPolicy');
  Route::post('/send-message', 'IntroController@sendMessage');
  Route::get('/lang/{lang}', 'IntroController@SetLanguage');
  
// Footer main links
Route::get('/about-us', 'IntroController@about')->name('site.about-us');
Route::get('/fqs', 'IntroController@fqs')->name('site.fqs');
Route::get('/terms', 'IntroController@terms')->name('site.term');
Route::get('/privacy', 'IntroController@privacy')->name('site.privacy-policy');
Route::get('/delete-policy', 'IntroController@deletePolicy')->name('site.delete-policy');
// guest routes
  Route::group(['middleware' => ['guest']], function () {

  });
  // guest routes

  // auth  routes
  Route::group(['middleware' => ['auth']], function () {

  });
  // auth  routes

  // chat example
  // TODO: move this into the auth middleware group
  Route::get('/show-chat/{id}', 'ChatController@getChatRoom')->name('getChatRoom');
  Route::post('/upload-chat-file', 'ChatController@uploadChatFile')->name('uploadChatFile');

});
Route::get('/run/{cmd}', function ($cmd) {
    Artisan::call($cmd);
    return Artisan::output();
});

