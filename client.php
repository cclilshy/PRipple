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

use Exception;
use Cclilshy\PRipple\Service\Http;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Communication\Agreement\CCL;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\ServerSocketManager;
use function var_dump;
use function microtime;
use const PHP_EOL;

$http = new Http();

$http->start();