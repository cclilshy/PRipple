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

/**
 *
 */
class Guardian
{
    // sibling process ID
    public array $processIds = [];

    // Ignore the life and death of the parent process self-destruct
    public bool $guard = false;

    /**
     * Create a daemon and return the IPC name
     *
     * @return string | false
     * @throws \Exception
     * @throws \Exception
     */
    public static function create(): string|false
    {
        $handler = function ($action, $data, $ipc) {
            Log::pdebug('[Guardian(' . posix_getpid() . ')] ' . $action . ':' . json_encode($data));
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

            // Self-release when the parent process exits and all sibling processes end
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
