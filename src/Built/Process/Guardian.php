<?php
declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2023-02-26 15:14:18
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: cclilshy, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\Console;

/**
 *
 */
class Guardian
{
    // 兄弟进程ID
    public array $processIds = [];

    // 忽略父进程的存亡自我销毁
    public bool $guard = false;

    /**
     * 创建一个守护程序并返回IPC名称
     *
     * @return string | false
     * @throws \Exception
     * @throws \Exception
     */
    public static function create(): string|false
    {
        $handler = function ($action, $data, $ipc) {
            Console::pdebug('[Guardian(' . posix_getpid() . ')] ' . $action . ':' . json_encode($data));
            switch ($action) {
                case 'new':
                    $ipc->space->add($data['pid']);
                    break;
                case 'exit':
                    $ipc->space->remove($data['pid']);
                    break;
                case 'signal':
                    return posix_kill($data['pid'], $data['signNo']);
                case 'guard':
                    $ipc->space->guard = true;
                    break;
            }

            // 当父进程退出时且所有兄弟进程结束时，自我释放
            if (count($ipc->space->processIds) === 0 && (posix_getppid() === 1 || $ipc->space->guard)) {
                return 'quit';
            }
            return true;
        };
        return Event::create($handler, new self())->name ?? false;
    }

    /**
     * @param $pid
     * @return void
     */
    public function add($pid): void
    {
        $this->processIds[] = $pid;
    }

    /**
     * @param $pid
     * @return void
     */
    public function remove($pid): void
    {
        $key = array_search($pid, $this->processIds);
        if ($key !== false) {
            unset($this->processIds[$key]);
        }
    }
}
