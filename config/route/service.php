<?php
use Cclilshy\PRipple\Route\Route;

Route::service("Timer", '\Cclilshy\PRipple\Built\Timer\Timer');
Route::service("HttpService", '\Cclilshy\PRipple\Built\Http\Service');
Route::service("WebSocket", '\app\WebSocket\Text');