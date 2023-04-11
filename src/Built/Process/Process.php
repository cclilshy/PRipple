<?php
declare(strict_types=1);
/*
 * @Author: error: error: git config user.name & please set dead value or install git && error: git config user.email & please set dead value or install git & please set dead value or install git
 * @Date: 2023-02-07 22:54:36
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by ${git_name} email: ${git_email}, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

use Exception;
use Cclilshy\Console;
use Cclilshy\Server\Server;
use Cclilshy\Pripple\FileSystem\Fifo;

// 进程管理器


class Process
{
    private static Event       $processTreeIPC;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          // 进程树IPC
    private static string|null $guardIPCName;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             // 守护者IPC名称
    private static bool        $invited     = false;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          // 是否初始化
    private static array       $exceptEvent = array();                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    // 异常事件

    /**
     * 创建一个子进程
     *
     * @param callable $handler
     * @param ?string  $name
     * @return int
     * @throws Exception
     */
    public static function fork(callable $handler, ?string $name = null): int
    {
        if (!self::$invited) {
            return -1;
        }

        if (!isset(self::$guardIPCName)) {
            if (!$guardIPCName = Guardian::create()) {
                return -1;
            }
            self::$guardIPCName = $guardIPCName;
        }

        $fifo = Fifo::create(md5(microtime(true) . mt_rand(111, 999)));
        switch ($pid = pcntl_fork()) {
            case -1:
                return -1;
            case 0:
                set_exception_handler([__CLASS__, 'exceptEvent']);
                try {
                    Event::link(self::$guardIPCName);
                } catch (Exception $e) {
                    Console::pred($e->getMessage());
                    self::$guardIPCName = Guardian::create();
                    Console::pgreen('尝试重新创建守护者成功');
                }
                self::$processTreeIPC->call('new', [
                    'pid'     => posix_getpid(),
                    'ppid'    => posix_getppid(),
                    'IPCName' => self::$guardIPCName
                ]);
                $fifo->write('1');
                // 在子节点中重置守护信息
                self::$invited      = false;
                self::$guardIPCName = null;
                self::$exceptEvent  = [];
                self::init();
                // 处理主业务
                call_user_func($handler);
                // 通知进程树销毁
                self::$processTreeIPC->call('exit', ['pid' => posix_getpid()]);
                exit(0);

            default:
                $fifo->read(1);
                $fifo->release();
                return $pid;
        }
    }

    /**
     * 初始化
     *
     * @return bool
     */
    public static function init(): bool
    {
        try {
            if (self::$invited) {
                return true;
            } elseif (!$server = Server::load('Tree')) {
                throw new Exception('进程树不在运行');
            } elseif (!$processTreeIPC = Event::link($server->info()['tree_name'])) {
                throw new Exception('无法连接进程树IPC,可能进程树已意外停止');
            } else {
                $processTreeIPC->call('new', [
                    'pid'     => posix_getpid(),
                    'ppid'    => posix_getppid(),
                    'IPCName' => null
                ]);
                self::$processTreeIPC = $processTreeIPC;
                return self::$invited = true;
            }
        } catch (Exception $e) {
            Console::pred($e->getMessage());
            return false;
        }
    }

    /**
     * @param int $pid
     * @param int $signNo
     * @return bool
     * @throws Exception
     */
    public static function signal(int $pid, int $signNo): bool
    {
        if (!isset(self::$invited) || !self::$invited)
            return false;
        if (self::$processTreeIPC->call('signal', ['pid' => $pid, 'signo' => $signNo]) === 0) {
            return true;
        }
        return false;
    }

    /**
     * 销毁任意进程
     *
     * @param int $pid
     * @return bool
     * @throws Exception
     */
    public static function kill(int $pid): bool
    {
        if (!self::$invited)
            return false;
        if (self::$processTreeIPC->call('kill', ['pid' => $pid]) === 0) {
            return true;
        }
        return false;
    }

    /**
     * 销毁一整棵树的进程，提供根节点
     *
     * @param int $ppid
     * @return bool
     * @throws Exception
     */
    public static function killAll(int $ppid): bool
    {
        if (!isset(self::$invited) || !self::$invited)
            return false;
        if (self::$processTreeIPC->call('killAll', ['ppid' => $ppid]) === 0) {
            return true;
        }
        return false;
    }

    /**
     * 开始守护，当前进程将不再创建子进程
     *
     * @return void
     * @throws Exception
     */
    public static function guard(): void
    {
        if (!self::$invited || !isset(self::$guardIPCName)) {
            return;
        } elseif ($guardIPC = Event::link(self::$guardIPCName)) {
            $guardIPC->call('guard', []);
        }
        self::$processTreeIPC->call('exit', ['pid' => posix_getpid()]);
    }

    /**
     * @param mixed $exception
     * @return void
     */
    public static function exceptEvent(mixed $exception): void
    {
        Console::pred($exception->getMessage() . ' in ' . $exception->getFile() . '(' . $exception->getLine() . ')');
        $trace = $exception->getTrace();
        foreach ($trace as $item) {
            if (isset($item['file']) && isset($item['line'])) {
                Console::pred("|trace in {$item['file']}({$item['line']})");
            }
        }
        try {
            self::$processTreeIPC->call('exit', ['pid' => posix_getpid()]);
        } catch (Exception $e) {
            Console::pred($e->getMessage());
        }
        foreach (self::$exceptEvent as $callable) {
            call_user_func($callable, $exception);
        }
        exit;
    }

    /**
     * @param callable $func
     * @return void
     */
    public static function registererEvent(callable $func): void
    {
        self::$exceptEvent[] = $func;
    }
}