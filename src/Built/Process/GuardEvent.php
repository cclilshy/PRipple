<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 19:46:17
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Built\Process\Fifo;
use Cclilshy\PRipple\Communication\Agreement\CCL;
use Cclilshy\PRipple\Built\Process\AgreementInterface;
use Cclilshy\PRipple\Built\Process\Standard\EventInterface;

/**
 *
 */
class GuardEvent
{
    private string             $name;
    private array              $siblingsProcessIds = [];
    private AgreementInterface $aisle;

    public function __construct()
    {
        $this->name  = 'guard_' . posix_getpid() . BS . substr(md5(rand(1111, 9999)), 0, 6);
        $this->aisle = Fifo::create(Fifo::create($this->name));
    }

    /**
     * @return \Cclilshy\PRipple\Built\Process\GuardEvent
     */
    public static function create(): GuardEvent
    {
        return new self();
    }

    /**
     * @param string    $name
     * @param bool|null $destroy
     * @return \Cclilshy\PRipple\Service\Process\Event|false
     */
    public static function link(string $name, ?bool $destroy = false): EventInterface|false
    {
        return false;
    }

    /**
     * @return int
     */
    public function killAll(): int
    {
        $count = 0;
        foreach ($this->siblingsProcessIds as $processId) {
            $this->kill($processId) && $count++;
        }
        return $count;
    }

    /**
     * @param int $processId
     * @return bool
     */
    public function kill(int $processId): bool
    {
        if ($this->sendSiganl($processId, SIGKILL)) {
            $this->removeSibling($processId);
            return true;
        }
        return false;
    }

    /**
     * @param int $processId
     * @param int $signal
     * @return bool
     */
    public function sendSiganl(int $processId, int $signal): bool
    {
        if (in_array($processId, $this->siblingsProcessIds)) {
            return pcntl_signal($processId, $signal);
        }
        return false;
    }

    /**
     * @param int $processId
     * @return void
     */
    public function removeSibling(int $processId): void
    {
        $key = array_search($processId, $this->siblingsProcessIds);
        if ($key !== false) {
            unset($this->siblingsProcessIds[$key]);
        }
    }

    /**
     * @param int $processId
     * @return void
     */
    public function addSibling(int $processId): void
    {
        $this->siblingsProcessIds[] = $processId;
    }

    /**
     * @throws \Exception
     */
    private function listen(): void
    {
        while ($message = CCL::cut($this->aisle)) {
            if ($event = unserialize($message)) {
                $action    = $event->getActionName();
                $arguments = $event->getArguments();
                $result    = call_user_func_array([$this, $action], $arguments);
                CCL::send($this->aisle, serialize($result));
            } else {
                Console::pred("Error: Build un serialize failed");
            }
        }
        throw new Exception("Error Processing Request");
    }
}