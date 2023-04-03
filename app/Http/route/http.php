<?php

use Cclilshy\PRipple\Route\Route;

Route::get('/', 'app\Http\controller\Index@index');
Route::get('/upload', 'app\Http\controller\Index@upload');
Route::post('/api', 'app\Http\controller\Index@index');
Route::post('/upload', 'app\Http\controller\Index@upload');

Route::static('/assets', \Cclilshy\PRipple\Built\Http\Http::ROOT_PATH . '/public/assets/');
Route::static('/robots.txt', \Cclilshy\PRipple\Built\Http\Http::ROOT_PATH . '/public/robots.txt');
Route::static('/favicon.ico', \Cclilshy\PRipple\Built\Http\Http::ROOT_PATH . '/public/favicon.ico');