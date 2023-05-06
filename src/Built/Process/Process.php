<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\PRipple\Log;
use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Communication;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;

class Process extends Service
{
    const SOCK_ADDRESS = PRIPPLE_SOCK_PATH . FS . 'Process' . SocketAisle::EXT;
    private static Guard|null  $guardProcess = null;
    private static SocketAisle $socketAisle;

    private array $guards = array();
    // When all processes are created, they will detect whether there is a daemon,
    // if there is, they will not be created, otherwise created,
    // so each process may only have one child process as a daemon,
    // so I decided to use the parent process ID as the key name of the daemon to facilitate indexing
    // between brother processes

    private array $processHashMap = array();
    // Because I want to record the parent process of the process and traverse the guard, I need such a process hash table,
    // The process hash table only records the corresponding parent ID, otherwise all child process PIDs can be found in the guard

    public function __construct(string|null $name = null)
    {
        parent::__construct("Process");
    }

    public static function fork(callable $function): int
    {
        if (Process::$guardProcess === null) {
            Process::init();
            Process::$guardProcess = Process::createGuardProcess();
        }
        switch ($pid = pcntl_fork()) {
            case 0:
                // In order to send events between multiple processes without conflict,
                // a locking mechanism is used
                // Therefore, it is necessary to clone a pipe with a different resource address
                Process::$guardProcess = null;
                $event                 = new Event('Process', 'processSignal', [
                    'type' => 'new',
                    'pid'  => posix_getpid(),
                    'ppid' => posix_getppid()
                ]);
                Process::$socketAisle->write($event->serialize());
                call_user_func($function);
                $event = new Event('Process', 'processSignal', [
                    'type' => 'exit',
                    'pid'  => posix_getpid(),
                ]);
                Process::$socketAisle->write($event->serialize());
                exit;
            case -1:
            default:
                return $pid;
        }
    }

    public static function init(): void
    {
        try {
            Process::$socketAisle = SocketAisle::create(SocketUnix::connect(Process::SOCK_ADDRESS));
        } catch (\Exception $e) {
            Log::print($e->getMessage() . PHP_EOL);
        }
    }

    public static function createGuardProcess(): Guard
    {
        Process::$guardProcess = new Guard('guard_' . posix_getpid());
        $pid                   = pcntl_fork();
        if ($pid === 0) {
            Process::$guardProcess->launch();
            exit;
        } else {
            $event = new Event('Process', 'guardOnline', [
                'pid'  => $pid,
                'ppid' => posix_getpid()
            ]);
            Process::$socketAisle->write($event->serialize());
        }
        return Process::$guardProcess;
    }

    public static function signal(int $signNo, int $processId): void
    {
        $event = new Event('Process', 'processSignal', [
            'type'   => 'signal',
            'signNo' => $signNo,
            'pid'    => $processId
        ]);
        Process::$socketAisle->write($event->serialize());
    }

    public function handshake(Client $client): bool|null
    {
        return $client->handshake();
    }

    public function onConnect(Client $client): void
    {

    }

    public function onClose(Client $client): void
    {

    }

    public function heartbeat(): void
    {

    }

    public function onMessage(string $context, Client $client): void
    {
        if ($event = Event::unSerialize($client->cache($context))) {
            $this->onEvent($event, $client);
            $client->cleanCache();
        } else {
            echo 'sss';
        }
    }

    public function onEvent(Event $event): void
    {
        if ($event->getName() === 'guardOnline') {
            //TODO: establish guard and process index
            $data                        = $event->getData();
            $this->guards[$data['ppid']] = [
                'pid'        => $data['pid'],
                'processIds' => []
            ];
        } elseif ($event->getName() === 'processSignal') {
            $data   = $event->getData();
            $pid    = $data['pid'] ?? null;
            $ppid   = $data['ppid'] ?? null;
            $signNo = $data['signNo'] ?? null;
            $type   = $data['type'];

            if ($type === 'new' && isset($this->guards[$ppid])) {
                $this->guards[$ppid][]      = $pid;
                $this->processHashMap[$pid] = $ppid;
            } elseif ($type === 'exit') {
                // TODO: rebuild the table index
                if ($ppid = $this->processHashMap[$pid] ?? null) {
                    if ($key = array_search($pid, $this->guards[$ppid]['processIds'] ?? [])) {
                        unset($this->guards[$ppid]['processIds'][$key]);
                    }
                }
            } elseif ($type === 'signal') {
                // TODO: scan guard process when send signal
                if ($guardProcessId = $this->processHashMap[$pid] ?? null) {
                    $this->publishEvent('guard_' . $guardProcessId . '_signal', [
                        'signNo' => $signNo,
                        'pid'    => $pid
                    ]);
                }
            }
        }
    }

    public function initialize(): void
    {
        if (file_exists(Process::SOCK_ADDRESS)) {
            unlink(Process::SOCK_ADDRESS);
        }
        $this->createServer(Communication::UNIX, Process::SOCK_ADDRESS);
    }

    public function onPackage(Build $package): void
    {

    }

    public function destroy(): void
    {
        if (isset(self::$socketAisle)) {
            self::$socketAisle->release();
        }
    }
}