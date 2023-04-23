<?php

use Cclilshy\PRipple\Route\Route;

Route::console("dth", '\Cclilshy\PRipple\Dispatch\Control');
Route::service("Timer", '\Cclilshy\PRipple\Built\Timer\Timer');
Route::service("HttpService", '\Cclilshy\PRipple\Built\Http\Service');