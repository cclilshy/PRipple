<?php

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;

class Control
{
    private SocketAisle $dispatcherSocket;

    public function __construct()
    {
        try {
            $socket                 = SocketUnix::connect(Dispatcher::$controlServiceUnixAddress);
            $this->dispatcherSocket = SocketAisle::create($socket);
            $this->dispatcherSocket->setBlock();
        } catch (\Exception $e) {
            Console::debug("[Control]", $e->getMessage());
            die;
        }
    }

    public function __destruct()
    {
        $this->dispatcherSocket->release();
    }

    public function listen(): void
    {
        \ob_start();
        while (true) {
            $readList = [$this->dispatcherSocket->getSocket()];
            if (socket_select($readList, $_, $_, null)) {
                if ($this->dispatcherSocket->read($data)) {
                    echo "\033[2K\033[0G";
                    echo $data;
                    \ob_flush();
                }
            }
        }
    }


    public function showSubscribes(): void
    {
        $event = new Event('control', 'getSubscribes', null);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());

        $int         = null;
        $recvContext = Dispatcher::AGREE::cutWithInt($this->dispatcherSocket, $int);
        $recvBuild   = Build::unSerialize($recvContext);
        if (!$event = $recvBuild->getEvent()) {
            return;
        } elseif (!$subscribes = $event->getData()) {
            return;
        } else {
            $table = $this->formatEventSubscribersTable($subscribes);
            Console::displayTableFromArray($table, 'Subscribes');
        }
    }

    public function showServices(): void
    {
        $event = new Event('control', 'getServices', null);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());

        $int         = null;
        $recvContext = Dispatcher::AGREE::cutWithInt($this->dispatcherSocket, $int);
        $recvBuild   = Build::unSerialize($recvContext);

        if ($event = $recvBuild->getEvent()) {
            $services = $event->getData();
            $table    = $this->formatServicesTable($services);
            Console::displayTableFromArray($table, 'Services');
        } else {
            echo "Failed to retrieve services from dispatcher.\n";
        }
    }

    private function formatEventSubscribersTable(array $eventSubscribersArray): array
    {
        $data = [];

        if (empty($eventSubscribersArray)) {
            $data['header'] = [];
            $data['body']   = [];
            return $data;
        }

        $publisherMaxLen  = max(array_map('strlen', array_keys($eventSubscribersArray)));
        $eventMaxLen      = max(array_map('strlen', array_keys($eventSubscribersArray[array_key_first($eventSubscribersArray)])));
        $subscriberMaxLen = 0;
        foreach ($eventSubscribersArray as $publisher => $events) {
            foreach ($events as $event => $subscribers) {
                $subscriberList   = implode(", ", array_keys($subscribers));
                $subscriberTypes  = implode(", ", array_values($subscribers));
                $subscriberMaxLen = max($subscriberMaxLen, strlen($subscriberList) + strlen($subscriberTypes) + 3); // add 3 for parentheses and comma
            }
        }

        $data['header'] = [
            ['Publisher', $publisherMaxLen],
            ['Event', $eventMaxLen],
            ['Subscribers', $subscriberMaxLen],
        ];

        $body = [];
        foreach ($eventSubscribersArray as $publisher => $events) {
            foreach ($events as $event => $subscribers) {
                $subscriberList = '';
                foreach ($subscribers as $subscriber => $type) {
                    $subscriberList .= "{$subscriber}({$type}), ";
                }
                $subscriberList = rtrim($subscriberList, ", ");
                $body[]         = [$publisher, $event, $subscriberList];
            }
        }

        $data['body'] = $body;
        return $data;
    }

    private function formatServicesTable(array $services): array
    {
        $data = [];

        if (empty($services)) {
            $data['header'] = [];
            $data['body']   = [];
            return $data;
        }

        $nameMaxLen   = max(array_map('strlen', array_map('strval', array_column($services, 'name'))));
        $statusMaxLen = 10;

        $data['header'] = [
            ['Name', $nameMaxLen],
            ['Status', $statusMaxLen],
        ];

        $body = [];
        foreach ($services as $service) {
            $body[] = [$service->name, $service->state];
        }

        $data['body'] = $body;
        return $data;
    }
}
