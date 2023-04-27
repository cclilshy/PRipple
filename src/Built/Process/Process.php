<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;

class Process extends Service
{
    private static Process    $singleCase;
    private static Guard|null $guardProcess = null;

    public function __construct(string|null $name = null)
    {
        parent::__construct($name);
    }

    public function handshake(Client $client): bool|null
    {

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

    public function onEvent(Event $event): void
    {
        if ($event->getName() === 'guardOnline') {
            //TODO: establish guard and process index
            $data = $event->getData();
        }
    }

    public function onMessage(string $context, Client $client): void
    {

    }

    public function initialize(): void
    {
        Process::$singleCase = $this;
        $this->subscribe($this->name, 'processSignal', Dispatcher::FORMAT_EVENT);
        $this->subscribe('default', 'guardOnline', Dispatcher::FORMAT_EVENT);
    }

    public function onPackage(Build $package): void
    {

    }

    public static function fork(callable $function): int
    {
        if (Process::$guardProcess === null) {
            Process::$guardProcess = Process::createGuardProcess();
        }
        switch ($pid = pcntl_fork()) {
            case 0:
                // In order to send events between multiple processes without conflict,
                // a locking mechanism is used
                // Therefore, it is necessary to clone a pipe with a different resource address
                Process::$guardProcess     = null;
                Process::$singleCase->pipe = Process::$singleCase->pipe->clone();
                Process::$singleCase->publishEvent('processSignal', [
                    'type' => 'new',
                    'pid'  => posix_getpid(),
                    'ppid' => posix_getppid()
                ]);
                call_user_func($function);
                Process::$singleCase->publishEvent('processSignal', [
                    'type' => 'exit',
                    'pid'  => posix_getpid(),
                ]);
                exit;
            case -1:
            default:
                return $pid;
        }
    }

    public static function createGuardProcess(): Guard
    {
        Process::$guardProcess = new Guard('guard_' . posix_getpid());
        Process::$guardProcess->launch();
    }
}