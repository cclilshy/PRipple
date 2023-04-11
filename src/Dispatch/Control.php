<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-29 10:38:31
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Built\Timer\Timer;
use Cclilshy\PRipple\Service\ServiceInfo;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use function ob_flush;
use function ob_start;

class Control
{
    private SocketAisle $dispatcherSocket;
    private int         $currentLine             = 0;
    private int         $subscriberStatusLine    = 0;
    private int         $subscriberStatusEndLine = 0;
    private int         $serviceStatusLine       = 0;
    private int         $serviceStatusEndLine    = 0;
    private int         $lastLine                = 0;
    private int         $messageLine             = 0;
    private int         $lastUpdateTableTime     = 0;

    public function __construct()
    {
    }

    public static function register(): string
    {
        return '主程序';
    }

    public function __destruct()
    {
        if (isset($this->dispatcherSocket)) {
            $this->dispatcherSocket->release();
        }
    }


    public function main($argv, $console): void
    {
        if (count($argv) < 2) {
            printf("Please Enter The Correct Parameter.\n\033[32m dth\033[0m help\n");
            return;
        }
        $option = $argv[1];
        switch ($option) {
            case 'service':
                $this->connectDispatcher();
                if (isset($argv[2])) {
                    $this->getServiceInfo($argv[2]);
                } else {
                    $this->getServices();
                }
                $this->listen();
                break;
            case 'subscribe':
                $this->connectDispatcher();
                $this->getSubscribes();
                $this->listen();
                break;
            case 'listen':
                $this->connectDispatcher();
                $this->getServices();
                $this->getSubscribes();
                $this->listen(false);
                break;
            case 'stop':
                if ($serviceInfo = ServiceInfo::load('dispatcher')) {
                    if ($this->connectDispatcher()) {
                        $event = new Event("control", 'termination', null);
                        $build = new Build('control', null, $event);
                        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
                    } else {
                        $serviceInfo->release();
                    }
                }
                break;
            case 'start':
                if ($serviceInfo = ServiceInfo::create('dispatcher')) {
                    $pid = pcntl_fork();
                    if ($pid === 0) {
                        Dispatcher::launch();
                        exit;
                    } elseif ($pid === -1) {
                        Console::pdebug("[Dispatcher] start failed!");
                    } else {
                        sleep(1);
                        $this->connectDispatcher();

                        $timerProcessId = pcntl_fork();
                        if ($timerProcessId === 0) {
                            $timer = new Timer();
                            $timer->launch();
                            exit;
                        }

                        $httpProcessId = pcntl_fork();
                        if ($httpProcessId === 0) {
                            $http = new \Cclilshy\PRipple\Built\Http\Service();
                            $http->launch();
                            exit;
                        }

                        $serviceInfo->info([
                            'httpProcessId'  => $httpProcessId,
                            'timerProcessId' => $timerProcessId
                        ]);
                    }
                } else {
                    Console::pdebug("[Dispatcher] server is running!");
                }

                break;
            case 'help':
            default:
                # code...
                break;
        }
    }

    private function connectDispatcher(): bool
    {
        try {
            $socket                 = SocketUnix::connect(Dispatcher::$controlServiceUnixAddress);
            $this->dispatcherSocket = SocketAisle::create($socket);
            $this->dispatcherSocket->setBlock();
        } catch (Exception $e) {
            Console::debug("[Control]", $e->getMessage());
            return false;
        }
        return true;
    }

    public function getServiceInfo(string $name): void
    {
        $event = new Event('control', 'getServiceInfo', $name);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
    }

    public function getServices(): void
    {
        $event = new Event('control', 'getServices', null);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
    }

    public function listen(bool|null $oneOff = true): void
    {
        ob_start();
        $int = null;
        while (true) {
            try {
                if ($content = Dispatcher::AGREE::cutWithInt($this->dispatcherSocket, $int)) {
                    switch ($int) {
                        case Dispatcher::FORMAT_EVENT:
                            $event = Event::unSerialize($content);
                            switch ($event->getName()) {
                                case 'subscribes':
                                    $table = $this->formatEventSubscribersTable($event->getData());
                                    $this->updateSubscribersTable($table);
                                    break;
                                case 'services':
                                    $table = $this->formatServicesTable($event->getData());
                                    $this->updateServicesTable($table);
                                    break;
                                case 'serviceInfo':
                                    $this->showServiceInfo($event->getData());
                                    break;
                                default:

                                    break;
                            }
                            break;
                        case Dispatcher::FORMAT_MESSAGE:
                            $this->printMessage($content);
                            break;
                        default:
                            # code...
                            break;
                    }
                    if ($oneOff === true) {
                        break;
                    }
                }
            } catch (Exception $e) {
                echo "Dispatcher close . " . $e->getMessage() . "\n";
                exit;
            }
        }
    }

    private function formatEventSubscribersTable(array $eventSubscribersArray): array
    {
        $data = [];

        if (empty($eventSubscribersArray)) {
            $data['header'] = [
                ['Publisher', 14],
                ['Event', 14],
                ['Subscribers', 14],
            ];
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

    private function updateSubscribersTable(array $table): void
    {
        if (empty($table['header']) || empty($table['body'])) {
            return;
        }
        $output          = $this->getOutputContentByTableArray($table, 'Subscribers');
        $outputLines     = explode("\n", $output);
        $outputLineCount = count($outputLines);

        if ($this->subscriberStatusEndLine > 0) {
            $this->seekCursor($this->subscriberStatusLine);
            for ($i = 0; $i < $outputLineCount; $i++) {
                echo "\r" . str_repeat(' ', 80) . "\r";
                echo $outputLines[$i] . "\n";
            }
            $this->currentLine = $this->subscriberStatusLine + $outputLineCount;
        } else {
            $this->subscriberStatusLine    = $this->lastLine;
            $this->subscriberStatusEndLine = $this->subscriberStatusLine + $outputLineCount;
            echo $output;
            $this->currentLine = $this->subscriberStatusEndLine;
        }

        $this->lastLine    = $this->currentLine;
        $this->messageLine = $this->lastLine;
        ob_flush();
        $this->seekCursor($this->currentLine);
    }

    private function getOutputContentByTableArray(array $tableArray, string $tableName): string
    {
        if (empty($tableArray['header'])) {
            return "No data to display.";
        }
        $columnMaxLen = array_map(function ($column) {
            return $column[1];
        }, $tableArray['header']);

        $output         = '';
        $tableNameColor = "\033[1;32m"; // Bold cyan
        $output         .= sprintf("%s%s:\n%s", $tableNameColor, $tableName, "\033[0m");
        $borderStr      = "+-" . str_repeat('-', array_sum($columnMaxLen) + count($columnMaxLen) * 2) . "-+\n";
        $output         .= sprintf("%s", $borderStr);
        $headerStr      = '';
        foreach ($tableArray['header'] as $i => $column) {
            $headerStr .= "| \033[1;36m%{$columnMaxLen[$i]}s\033[0m ";
        }
        $headerStr .= "|\n";
        $output    .= sprintf($headerStr, ...array_column($tableArray['header'], 0));
        $output    .= sprintf("%s", $borderStr);
        foreach ($tableArray['body'] as $row) {
            $rowStr = '';
            foreach ($row as $i => $column) {
                $rowStr .= "| %{$columnMaxLen[$i]}s ";
            }
            $rowStr .= "|\n";
            $output .= sprintf($rowStr, ...$row);
        }
        $output .= sprintf("%s", $borderStr);
        return $output;
    }

    private function seekCursor(int $line): void
    {
        $count = $line - $this->currentLine;
        if ($count > 0) {
            // 光标下移
            echo "\033[{$count}B";
        } elseif ($count < 0) {
            // 光标上移
            echo "\033[{$count}A";
        }
        $this->currentLine = $line;
    }

    private function formatServicesTable(array $services): array
    {
        $data = [];

        if (empty($services)) {
            $data['header'] = [
                ['Name', 10],
                ['Status', 10],
            ];
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

    private function updateServicesTable(array $table): void
    {
        if (empty($table['header']) || empty($table['body'])) {
            return;
        }
        $output          = $this->getOutputContentByTableArray($table, 'Services');
        $outputLines     = explode("\n", $output);
        $outputLineCount = count($outputLines);

        if ($this->serviceStatusEndLine > 0) {
            $this->seekCursor($this->serviceStatusLine);
            for ($i = 0; $i < $outputLineCount; $i++) {
                echo "\r" . str_repeat(' ', 80) . "\r";
                echo $outputLines[$i] . "\n";
            }
            $this->currentLine = $this->serviceStatusLine + $outputLineCount;
        } else {
            $this->serviceStatusLine    = $this->lastLine;
            $this->serviceStatusEndLine = $this->serviceStatusLine + $outputLineCount;
            echo $output;
            $this->currentLine = $this->serviceStatusEndLine;
        }

        $this->lastLine    = $this->currentLine;
        $this->messageLine = $this->lastLine;
        ob_flush();
        $this->seekCursor($this->currentLine);
    }

    private function showServiceInfo(mixed $service): void
    {
        if ($service == null) {
            echo "Service not found.\n";
            return;
        }
        echo "\033[1mServiceInfo:\033[0m\n";
        echo "  name: " . $service->name . "\n";
        echo "  state: " . $service->state . "\n";
        echo "  cacheFilePath: " . $service->cacheFilePath . "\n";
        echo "  cacheCount: " . $service->cacheCount . "\n";
        echo "\033[1mSocketInfo:\033[0m\n";
        echo "  address: " . $service->socket->getAddress() . "\n";
        echo "  keyName: " . $service->socket->getKeyName() . "\n";
        echo "  createTime: " . $service->socket->getCreateTime() . "\n";
        echo "  port: " . $service->socket->getPort() . "\n";
        echo "  sendBufferSize: " . $service->socket->getSendBufferSize() . "\n";
        echo "  receiveBufferSize: " . $service->socket->getReceiveBufferSize() . "\n";
        echo "  sendLowWaterSize: " . $service->socket->getSendLowWaterSize() . "\n";
        echo "  receiveLowWaterSize: " . $service->socket->getReceiveLowWaterSize() . "\n";
        echo "  sendFlowCount: " . $service->socket->getSendFlowCount() . "\n";
        echo "  receiveFlowCount: " . $service->socket->getReceiveFlowCount() . "\n";
        echo "  cacheLength: " . $service->socket->getCacheLength() . "\n";
        echo "  name: " . $service->socket->getName() . "\n";
        echo "  identity: " . $service->socket->getIdentity() . "\n";
        echo "  activeTime: " . $service->socket->getActiveTime() . "\n";
    }

    public function printMessage(string $message): void
    {
        if ($this->messageLine > 0) {
            $this->seekCursor($this->messageLine);
        } else {
            $this->seekCursor($this->lastLine);
            $this->messageLine = $this->currentLine;
        }

        // 清空当前终端行
        echo "\033[2K\033[1;32mStatus:\033[0m {$message}";
        $this->currentLine = $this->messageLine;
        ob_flush();
    }

    public function getSubscribes(): void
    {
        $event = new Event('control', 'getSubscribes', null);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
    }

    private function flushPrint(): void
    {
    }
}
