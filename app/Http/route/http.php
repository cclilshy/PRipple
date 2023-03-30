<?php

use Cclilshy\PRipple\Route\Route;

Route::get('/', 'app\Http\controller\Index@index');
Route::static('/assets', \Cclilshy\PRipple\Built\Http\Http::ROOT_PATH . '/public/assets/');
Route::static('/robots.txt', \Cclilshy\PRipple\Built\Http\Http::ROOT_PATH . '/public/robots.txt');
Route::static('/favicon.ico', \Cclilshy\PRipple\Built\Http\Http::ROOT_PATH . '/public/favicon.ico');