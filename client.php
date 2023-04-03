<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 15:54:17
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple;

include __DIR__ . '/vendor/autoload.php';
Configure::init();
Config::init();
if (pcntl_fork() === 0) {
    $timer = new Built\Timer\Timer();
    $timer->launch();
    return;
}

$http = new \Cclilshy\PRipple\Built\Http\Service();
$http->launch();