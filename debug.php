<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 15:54:17
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple;

include __DIR__ . '/vendor/autoload.php';
shell_exec("rm -rf " . __DIR__ . '/pipe/t.aisle');
Configure::init();

use Exception;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Communication\Agreement\CCL;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\ServerSocketManager;
use function var_dump;
use function microtime;
use const PHP_EOL;

Dispatcher::launch();


return;
$name = PIPE_PATH . FS . 't' . SocketAisle::EXT;

try {
    $a = ServerSocketManager::createServer(SocketUnix::class, $name);
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    return;
}


if (pcntl_fork() === 0) {
    while (true) {
        $readList = array_merge([$a->getEntranceSocket()], $a->getClientSockets() ?? []);
        socket_select($readList, $_, $_, null);
        foreach ($readList as $item) {
            switch ($item) {
                case $a->getEntranceSocket():
                    echo '接受客户' . PHP_EOL;
                    $a->accept($item);
                    break;

                default:
                    $aisle = $a->getClientBySocket($item);
                    try {
                        $res = CCL::cutWithString($aisle, $param);
                        var_dump($param);
                        // var_dump($res);
                    } catch (Exception $e) {

                        echo $e->getMessage();
                        var_dump($aisle->getReceiveFlowCount());
                        die;
                    }
            }
        }
    }
    exit;
}
$aisle = SocketUnix::connectAisle($name);
echo microtime(true) . PHP_EOL;
for ($i = 0; $i < 10; $i++) {
    CCL::sendWithString($aisle, "hello", "hello");
}
echo microtime(true) . PHP_EOL;
echo '结束了' . PHP_EOL;
// \var_dump($aisle);
sleep(3);