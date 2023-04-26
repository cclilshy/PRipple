<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-02-19 20:58:16
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: cclilshy, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

use Exception;
use Cclilshy\Console;
use Cclilshy\Pripple\FileSystemPipe;
use Cclilshy\Pripple\FileSystemFifo;
use Cclilshy\PRipple\Built\Process\Pipe;
use Cclilshy\PRipple\Built\Process\Fifo;
use Cclilshy\PRipple\Built\Process\Standard\EventInterface as EventFactory;
use function Cclilshy\PRipple\Built\Process\phppack;
use const Cclilshy\PRipple\Built\Process\Event;

/**
 * function stop() Initiated by the caller to stop the watcher service
 * function release() Should be initiated by a watcher to release pipeline files
 * function close() Initiated by the caller, the call is actively closed without affecting the operation of the monitor
 */
class Event implements EventFactory
{
    public mixed  $space;    // allows user defined objects at initialization time
    private mixed $observer; // Monitor functions

    private int    $observerProcessId; //Monitor the process ID
    private string $name;              // IPC name

    private Fifo $sender;     // This process
    private Fifo $notice;     // Target process
    private Fifo $common;     // Public pipes
    private Pipe $lock;

    /**
     * @param ?string $name
     */
    private function __construct(?string $name)
    {
        if (isset($name)) {
            $this->name = $name;
        } else {
            $this->name = posix_getpid() . '_' . substr(md5((string)microtime(true)), 0, 6);
        }
    }

    /**
     * @param callable    $observer 监视者方法
     * @param mixed       $space    自定义暂存空间
     * @param string|null $name     自定义名称
     * @return Event|false  返回IPC信息
     * @throws Exception
     */
    public static function create(callable $observer, mixed $space = null, string $name = null): Event|false
    {

        $ipc = new self($name);
        return $ipc->initIPC($observer, $space);
    }

    /**
     * @param callable $observer
     * @param mixed    $space
     * @return \Cclilshy\PRipple\Process\Event|false
     * @throws Exception
     */
    private function initIPC(callable $observer, mixed $space = null): Event|false
    {
        if (Fifo::exists($this->name . '_master') || Fifo::exists($this->name . '_observer') || Fifo::exists($this->name . '_common') || Pipe::exists($this->name))
            return false;

        $this->observer = $observer;
        $this->space    = $space;
        $this->sender   = Fifo::create($this->name . '_master');
        $this->notice   = Fifo::create($this->name . '_observer');
        $this->common   = Fifo::create($this->name . '_common');
        $this->lock     = Pipe::create($this->name);

        return $this->observed() ? $this : false;
    }

    /**
     * 开始监视进程
     *
     * @return bool
     * @throws Exception
     */
    private function observed(): bool
    {
        switch ($pid = pcntl_fork()) {
            case 0:
                // $_ = set_error_handler(function ($errno, $erst, $erf, $erl) {
                //     echo 'Err(' . $errno . ')File ' . $erf . ' (' . $erl . ') :' . $erst . PHP_EOL;
                //     $this->common->write(serialize(false));
                //     $this->notice->write(strlen(serialize(false)) . PHP_EOL);
                //     $this->listener();
                // }, E_ALL);
                $this->listener();
                exit;
            case -1:
                throw new Exception('无法启动消费者服务，请检查系统荷载');
            default:
                $this->observerProcessId = $pid;
                return true;
        }
    }

    /**
     * 开始监听
     *
     * @return void
     * @throws Exception
     */
    private function listener(): void
    {
        $this->sender = Fifo::link($this->name . '_observer');
        $this->notice = Fifo::link($this->name . '_master');
        $this->common = Fifo::link($this->name . '_common');
        while ($length = $this->sender->fgets()) {
            if (!$fullContext = $this->fullContext(intval($length))) {
                $result = false;
            } else {
                $arguments   = unserialize($fullContext);
                $arguments[] = $this;
                if (isset($arguments[0]) && $arguments[0] === 'quit') {
                    $result = 'quit';
                } elseif (isset($arguments[0]) && $arguments[0] === 'test') {
                    $result = 'test';
                } else {
                    $result = call_user_func_array($this->observer, $arguments);
                }
                $context = serialize($result);
                // 将校验长度加入报文头
                $context    = Event . phppack('L', strlen($context)) . $context;
                $contextLen = strlen($context);
                // 发送报文
                $this->common->write($context);
                // 发送报文长度
                $this->notice->write($contextLen . PHP_EOL);
                if ((isset($arguments[0]) && $arguments[0] === 'quit') || $result === 'quit') {
                    $this->release();
                    exit;
                }
            }
        }
    }

    /**
     * 根据IPC名称连接到监视者
     *
     * @param string    $name
     * @param bool|null $destroy
     * @return Event|false
     * @throws Exception
     */
    public static function link(string $name, ?bool $destroy = false): Event|false
    {

        $ipc = new self($name);
        return $ipc->initConnection($destroy);
    }

    /**
     * @param bool|null $destroy
     * @return \Cclilshy\PRipple\Process\Event|false
     * @throws Exception
     */
    private function initConnection(?bool $destroy = false): Event|false
    {
        if (!Fifo::exists($this->name . '_master') || !Fifo::exists($this->name . '_observer') || !Fifo::exists($this->name . '_common') || !Pipe::exists($this->name)) {
            throw new Exception('无法连接一个不存在的IPC > ' . $this->name);
            //            return false;
        }

        $this->sender = Fifo::link($this->name . '_master');
        $this->notice = Fifo::link($this->name . '_observer');
        $this->common = Fifo::link($this->name . '_common');
        $this->lock   = Pipe::link($this->name);

        switch ($pid = pcntl_fork()) {
            case 0:
                $this->call('test');
                exit;
            case -1:
                throw new Exception('无法启动消费者服务，请检查系统荷载');
            default:

                declare(ticks=1);

                $active = false;
                pcntl_signal(SIGCHLD, function () use (&$active) {
                    $active = true;
                });
                sleep(2);
                pcntl_signal_dispatch();
                if ($active === true) {
                    return $this;
                } else {
                    posix_kill($pid, SIGKILL);
                    $destroy ? $this->release() : $this->close();
                    return false;
                }
        }
    }

    /**
     * 通过此方法可以调用监视者
     * 该进程会堵塞直到监视者返回结果,并返回结果
     * 该进程如果等不到结果会被强制杀死
     *
     * @return mixed
     * @throws Exception
     */
    public function call(): mixed
    {
        $lock = $this->lock->clone();
        $_    = $lock->lock();
        if (!$_) {
            throw new Exception("管道被破坏");
        }
        $context    = serialize(func_get_args());
        $contextLen = strlen($context);
        $context    = pack('L', strlen($context)) . $context;
        $this->common->write($context);
        $this->notice->write($contextLen . PHP_EOL);
        $length = $this->sender->fgets();
        if ($length === '') {
            $lock->unlock();
            return false;
        }
        $length = intval($length);
        if (!$fullContext = $this->fullContext($length)) {
            $result = false;
        } else {
            $result = unserialize($fullContext);
        }
        $lock->unlock();
        return $result;
    }


    // 事实上管道的安全,应该由监视者自己维护,而不应该由调用者维护
    // 消费者是服务态,调用者只需要考虑调用,不应考虑其他问题
    // 但是,由于管道的特殊性,调用者需要考虑管道的安全性

    /**
     * 获取完整上下文
     *
     * @param int $length  寻找数据长度
     * @param int $residue 渣数据长度
     * @return string | false
     * @throws Exception
     */
    private function fullContext(int $length, int $residue = 0): string|false
    {
        $this->common->setBlocking(false);
        if ($residue > 0) {
            $this->common->read($residue);
        }

        $_residue = $this->common->read(4);
        if ($_residue === '') {
            $this->common->setBlocking(true);
            return false;
        }
        if (!$_residue = unpack('L', $_residue)[1]) {
            throw new Exception('IPC报文发生了不可预知的错误', 1);
        } elseif ($_residue !== $length) {
            return $this->fullContext($length, $_residue);
        } else {
            $this->common->setBlocking(true);
            return $this->common->read($_residue);
        }
    }

    /**
     * 关闭连接并删除管道
     *
     * @return void
     */
    private function release(): void
    {
        $this->close();
        $this->sender->release();
        $this->notice->release();
        $this->common->release();
        $this->lock->release();
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->sender->close();
        $this->notice->close();
        $this->common->close();
        $this->lock->close();
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * 通知监视者销毁并自关闭管道
     *
     * @return void
     */
    public function stop(): void
    {
        try {
            // 新增超时销毁
            switch ($pid = pcntl_fork()) {
                case 0:
                    if ($this->call('quit') === 'quit') {
                        $this->close();
                    }
                    exit;
                case -1:
                    throw new Exception('无法启用fork服务,请检查系统荷载 ', 1);
                default:

                    declare(ticks=1);
                    pcntl_signal(SIGCHLD, function () {
                    });
                    sleep(1);
                    posix_kill($pid, SIGKILL);
                    $this->release();
            }
        } catch (Exception $e) {
            Console::pred($e->getMessage());
            return;
        }
    }
}
