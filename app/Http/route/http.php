<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

use Cclilshy\PRipple\Route\Route;
use Cclilshy\PRipple\Built\Http\Http;

Route::get('/', 'app\Http\controller\Index@index');
Route::get('/upload', 'app\Http\controller\Index@upload');
Route::post('/upload', 'app\Http\controller\Index@upload');
Route::static('/assets', Http::ROOT_PATH . '/public/assets/');
Route::static('/robots.txt', Http::ROOT_PATH . '/public/robots.txt');
Route::static('/favicon.ico', Http::ROOT_PATH . '/public/favicon.ico');