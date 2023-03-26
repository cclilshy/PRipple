<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 15:54:17
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple;
shell_exec("rm -rf " . __DIR__ . '/runtime/pipe/*.*');

include __DIR__ . '/vendor/autoload.php';
Configure::init();

use Cclilshy\PRipple\Dispatch\Dispatcher;

Dispatcher::launch();
return;
