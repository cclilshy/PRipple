<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-02-19 16:23:07
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: cclilshy, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

// 用于管理进程树
// 任何入口都是从根部顺下
// 任何进程都可以向任何进程发送消息
// 任何进程都可以向任何进程发送信号

use Exception;


class Node
{
    // 哈希表索引
    private int    $pid;      // 进程id
    private int    $ppid;     // 父进程
    private array  $children; // 子进程
    private string $IPCName;

    /**
     * 创建一个节点，储存其基本信息
     *
     * @param int    $pid
     * @param int    $ppid
     * @param string $IPCName
     */
    public function __construct(int $pid, int $ppid, string $IPCName)
    {
        $this->pid      = $pid;
        $this->ppid     = $ppid;
        $this->children = [];
        $this->IPCName  = $IPCName;
        $this->sendIPCMessage('new', ['pid' => $pid]);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name): mixed
    {
        return $this->$name;
    }

    /**
     * @param Node $node
     * @return void
     */
    public function add(Node $node): void
    {
        $this->children[$node->pid] = $node;
    }

    /**
     * 移除一个子成员
     *
     * @param $pid
     * @return bool
     */
    public function remove($pid): bool
    {
        if (isset($this->children[$pid])) {
            unset($this->children[$pid]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * new一个子成员
     *
     * @param int    $pid
     * @param int    $ppid
     * @param string $IPCName
     * @return void
     */
    public function pushChild(int $pid, int $ppid, string $IPCName): void
    {
        if (!isset($this->children[$pid])) {
            $this->children[$pid] = new self($pid, $ppid, $IPCName);
        }
    }

    /**
     * 发送指定信号
     *
     * @param int $signNo
     * @return bool
     */
    public function signal(int $signNo): bool
    {
        return $this->sendIPCMessage('signal', ['pid' => $this->pid, 'signNo' => $signNo]);
    }

    /**
     * 指定一个继承人
     *
     * @param int $pid
     * @return Node
     */
    public function extend(int $pid): self
    {
        $this->ppid = $pid;
        return $this;
    }

    /**
     * 明确杀死子进程
     *
     * @return array
     */
    public function kill(): array
    {
        $this->sendIPCMessage('signal', ['pid' => $this->pid, 'signNo' => SIGKILL]);
        return $this->exit();  // 该操作可能触发守护进程自动回收，因此最后执行
    }

    /**
     *将子进程交与调用者处理，并通知守护者自身结束
     *
     * @return array
     */
    public function exit(): array
    {
        $this->sendIPCMessage('exit', ['pid' => $this->pid]);
        return $this->children;
    }

    /**
     * 获取一个子节点
     *
     * @param $pid
     * @return void
     */
    public function get($pid)
    {
        return $this->children[$pid] ?? null;
    }

    /**
     * @return array
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return int
     */
    public function getProcessId(): int
    {
        return $this->pid;
    }

    /**
     * 与IPC建立一次性连接，并发送遗传自定义命令
     *
     * @return mixed
     */
    private function sendIPCMessage(): mixed
    {
        try {
            if ($ipc = Event::link($this->IPCName)) {
                $res = call_user_func_array([$ipc, 'call'], func_get_args());
                $ipc->close();
                return $res;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }
}