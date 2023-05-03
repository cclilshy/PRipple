<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

use Cclilshy\PRipple\Route\Route;

Route::service("Process", '\Cclilshy\PRipple\Built\Process\Process');
Route::service("Timer", '\Cclilshy\PRipple\Built\Timer\Timer');
Route::service("HttpService", '\Cclilshy\PRipple\Built\Http\Service');
Route::service("WebSocket", '\app\WebSocket\Text');
