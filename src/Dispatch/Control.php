<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Log;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Route\Route;
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

    /**
     * @return string
     */
    public static function register(): string
    {
        return 'main program';
    }

    /**
     *
     */
    public function __destruct()
    {
        if (isset($this->dispatcherSocket)) {
            $this->dispatcherSocket->close();
        }
    }

    /**
     * @param $argv
     * @param $console
     * @return void
     */
    public function main($argv, $console): void
    {
        if (count($argv) < 2) {
            $console->printn("Please Enter The Correct Parameter.");
            $console->brief("service", 'view service');
            $console->brief("subscribe", '');
            $console->brief("listen", 'debug monitor');
            $console->brief("start", 'start the scheduler');
            $console->brief("stop", 'end scheduler');
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
                    // TODO: try send message close
                    if ($this->connectDispatcher()) {
                        $event = new Event("control", 'termination', null);
                        $build = new Build('control', null, $event);
                        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
                    } else {
                        // TODO: release service info file
                        $serviceInfo->release();
                    }
                }

                // TODO: last try release every service info file
                foreach (Route::getServices() as $serviceName => $service) {
                    if ($instantiation = $service->instantiation()) {
                        if ($instantiation->initLoad()) {
                            $instantiation->release();
                        }
                    }
                }
                PRipple::stop();
                Log::print("pripple is stop.");
                break;
            case 'start':
                if ($serviceInfo = ServiceInfo::create('dispatcher')) {
                    $serviceInfo->setLock();
                    $pid = pcntl_fork();
                    if ($pid === 0) {
                        Dispatcher::$serviceInfo = $serviceInfo;
                        Dispatcher::launch();
                        exit;
                    } elseif ($pid === -1) {
                        Log::pdebug("[Dispatcher] start failed!");
                    } else {
                        $serviceInfo->pipe->clone()->lock();
                        $this->connectDispatcher();
                        foreach (Route::getServices() as $serviceName => $service) {
                            PRipple::registerService($service->instantiation());
                        }
                        PRipple::go();
                    }
                } else {
                    Log::pdebug("[Dispatcher] server is running!");
                }

                break;
            case 'help':
            default:
                # code...
                break;
        }
    }

    /**
     * @return bool
     */
    private function connectDispatcher(): bool
    {
        try {
            $socket                 = SocketUnix::connect(Dispatcher::$controlServiceUnixAddress);
            $this->dispatcherSocket = SocketAisle::create($socket);
            $this->dispatcherSocket->setBlock();
        } catch (Exception $e) {
            Log::pdebug("[Control]", $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param string $name
     * @return void
     */
    public function getServiceInfo(string $name): void
    {
        $event = new Event('control', 'getServiceInfo', $name);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
    }

    /**
     * @return void
     */
    public function getServices(): void
    {
        $event = new Event('control', 'getServices', null);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
    }

    /**
     * @param bool|null $oneOff
     * @return void
     */
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
                Log::print("Dispatcher close . " . $e->getMessage() . "\n");
                exit;
            }
        }
    }

    /**
     * @param array $eventSubscribersArray
     * @return array
     */
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
                $subscriberList = implode(", ", array_keys($subscribers));
                @$subscriberTypes = implode(", ", array_values($subscribers));
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
                    @$subscriberList .= "{$subscriber}({$type}), ";
                }
                $subscriberList = rtrim($subscriberList, ", ");
                $body[]         = [$publisher, $event, $subscriberList];
            }
        }

        $data['body'] = $body;
        return $data;
    }

    /**
     * @param array $table
     * @return void
     */
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

    /**
     * @param array  $tableArray
     * @param string $tableName
     * @return string
     */
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

    /**
     * @param int $line
     * @return void
     */
    private function seekCursor(int $line): void
    {
        $count = $line - $this->currentLine;
        if ($count > 0) {
            echo "\033[{$count}B";
        } elseif ($count < 0) {
            echo "\033[{$count}A";
        }
        $this->currentLine = $line;
    }

    /**
     * @param array $services
     * @return array
     */
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

    /**
     * @param array $table
     * @return void
     */
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

    /**
     * @param mixed $service
     * @return void
     */
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

    /**
     * @param string $message
     * @return void
     */
    public function printMessage(string $message): void
    {
        if ($this->messageLine > 0) {
            $this->seekCursor($this->messageLine);
        } else {
            $this->seekCursor($this->lastLine);
            $this->messageLine = $this->currentLine;
        }

        // Clears the current terminal row
        echo "\033[2K\033[1;32mStatus:\033[0m {$message}";
        $this->currentLine = $this->messageLine;
        ob_flush();
    }

    /**
     * @return void
     */
    public function getSubscribes(): void
    {
        $event = new Event('control', 'getSubscribes', null);
        $build = new Build('dispatcher', null, $event);
        Dispatcher::AGREE::send($this->dispatcherSocket, $build->serialize());
    }
}
