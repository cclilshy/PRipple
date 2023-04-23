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

use Exception;
use Cclilshy\Console;
use Cclilshy\Standard\Module;
use Cclilshy\Server\Standard\ServerAbstract;

// 进程🌲


/**
 *
 */
class Tree extends ServerAbstract implements Module
{
    private Node  $root;             // 根节点
    private Node  $orphanProcess;    // 孤儿根节点
    private array $processMap = [];

    private function __construct()
    {
        parent::__construct('Tree');
        $this->root          = new Node(0, 0, 'undefined');
        $this->orphanProcess = new Node(1, 0, 'undefined');
    }

    /**
     * @return static
     */
    public static function init(): self
    {
        return new self();
    }

    /**
     * 启用这树服务
     *
     * @return bool
     */
    public function launch(): bool
    {
        try {
            if ($this->initCreate()) {
                $handler = function ($action, $data, $ipc) {
                    $ipc->space->handle($ipc, $action, $data);
                };
                $ipcName = Event::create($handler, new self())->name;

                $this->info(['tree_name' => $ipcName]);
                Console::pgreen('[TreeServer] started!');
                return true;
            } else {
                Console::pred('[TreeServer] start failed : it\'s start');
                return false;
            }
        } catch (Exception $e) {
            Console::pred($e->getMessage());
        }
        return false;
    }

    /**
     * 树主函数
     *
     * @param $ipc
     * @param $action
     * @param $data
     * @return void
     */
    public function handle($ipc, $action, $data): void
    {
        Log::pdebug('[MESSAGE] ' . json_encode(func_get_args()));
        switch ($action) {
            case 'new':
                if ($node = $this->findNodeByPid($data['ppid'])) {
                    $node->pushChild($data['pid'], $data['ppid'], $data['IPCName'] ?? 'undefined');
                    $this->processMap[$data['pid']] = ['ppid' => $data['ppid']];
                } else {
                    $this->orphanProcess->pushChild($data['pid'], $data['ppid'], $data['IPCName'] ?? 'undefined');
                    $this->processMap[$data['pid']] = ['ppid' => 1];
                }
                break;
            case 'exit':
                // 新成员退出，通知守护进程,调整树结构
                $this->handleExit($data['pid']);
                break;
            case 'signal':
                if ($node = $this->findNodeByPid($data['pid'])) {
                    $node->signal($data['signNo']);
                }
                break;
            case 'kill':
                if ($node = $this->findNodeByPid($data['pid'])) {
                    $this->kill($node);
                }
                break;
            case 'killAll':
                if ($node = $this->findNodeByPid($data['ppid']))
                    $this->killAll($node);
                break;
            default:
                break;
        }
    }

    /**
     * 搜索指定ID的节点引用指针
     *
     * @param $pid
     * @return Node|null
     */
    private function findNodeByPid($pid): Node|null
    {
        if ($pid === 1) {
            return $this->orphanProcess;
        }
        // 新成员进入，找到指定节点，插入新成员
        $node            = $this->root;
        $parentProcessId = $pid;
        $path            = [$parentProcessId];
        while ($parentProcessId = $this->processMap[$parentProcessId]['ppid'] ?? null) {
            if ($parentProcessId === 1) {
                $node = $this->orphanProcess;
                break;
            }
            $path[] = $parentProcessId;
        }
        while ($parentProcessId = array_pop($path)) {
            if (!$node)
                break;
            $node = $node->get($parentProcessId);
        }
        return $node ?? null;
    }

    /**
     * 处理退出的成员，并重新维护树结构
     *
     * @param $pid
     * @return void
     */
    private function handleExit($pid): void
    {
        if ($node = $this->findNodeByPid($pid)) {
            // 修改子进程继承
            $childrenNodes = $node->exit();
            foreach ($childrenNodes as $childrenNode) {
                $this->processMap[$childrenNode->getProcessId()]['ppid'] = 1;
                $this->orphanProcess->add($childrenNode->extend(1));
            }

            // 从父节点中释放
            if ($parentNode = $this->findNodeByPid($node->ppid)) {
                $parentNode->remove($node->getProcessId());
            }
            // 释放哈希表
            unset($this->processMap[$node->getProcessId()]);
        } else {
        }
    }

    /**
     * 销毁一个进程，通知其守护者服务
     *
     * @param Node $node
     * @return void
     */
    private function kill(Node $node): void
    {
        $childrenNodes = $node->kill();
        foreach ($childrenNodes as $childrenNode) {
            $this->processMap[$childrenNode->getProcessId()]['ppid'] = 1;
            $this->orphanProcess->add($childrenNode->extend(1));
        }
    }

    /**
     * 销毁一棵树的进程
     *
     * @param Node $node
     * @return void
     */
    private function killAll(Node $node): void
    {
        foreach ($node->getChildren() as $childrenNode) {
            $this->killAll($childrenNode);
        }
        $node->kill();
        unset($this->processMap[$node->getProcessId()]);
    }

    /**
     * 关闭树服务
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->initLoad()) {
            try {
                if ($ipcName = $this->data['tree_name'] ?? null) {
                    if ($IPC = Event::link($ipcName, true)) {
                        $IPC->stop();
                        $this->release();
                        Console::pgreen('[TreeServer] stopped!');
                        return;
                    }
                }
            } catch (Exception $e) {
                Console::pred($e->getMessage());
            }
            $this->release();
        }
        Console::pred('[TreeServer] stop failed may not run');
    }
}
