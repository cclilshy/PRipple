<?php

namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Service\SubscribeManager;
use Cclilshy\PRipple\Communication\Agreement\CCL;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Communication\Socket\Manager;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Service\Service as ServiceHandler;
use Cclilshy\PRipple\Dispatch\EventTemplate\CommonTemplate;

// 事件调度
class Dispatcher
{
    const AGREE             = CCL::class;
    const LOCAL_STREAM_TYPE = SocketUnix::class;
    const UNIX_HANDLE       = PIPE_PATH . FS . __CLASS__ . SocketAisle::EXT;
    const SOCKET_HANDLER    = 2;
    const PD_SUBSCRIBE      = 'PD_SUBSCRIBE';
    const PD_SUBSCRIBE_UN   = 'PD_SUBSCRIBE_UN';
    const FORMAT_BUILD      = 1;
    const FORMAT_EVENT      = 2;
    const FORMAT_MESSAGE    = 3;
    private static array            $socketHashMap = array();
    private static SubscribeManager $subscribeManager;
    private static Manager          $handlerSocketManager;
    private static array            $servers;
    // 服务列表

    /**
     * 启动
     *
     * @return void
     */
    public static function launch(): void
    {
        try {
            Dispatcher::initPublicEventList();
            Dispatcher::launchServer();
        } catch (Exception $e) {
            Console::debug($e->getMessage());
            return;
        }
        self::listen();
    }

    /**
     * 注册公共事件
     *
     * @return void
     */
    private static function initPublicEventList(): void
    {
        self::$subscribeManager = new subscribeManager();
        //TODO::公共事件
    }

    /**
     * 启动服务
     *
     * @return void
     * @throws \Exception
     */
    private static function launchServer(): void
    {
        self::$servers                    = [];
        Dispatcher::$handlerSocketManager = Manager::createServer(Dispatcher::LOCAL_STREAM_TYPE, Dispatcher::UNIX_HANDLE);
    }

    /**
     * 开始监听服务
     *
     * @return void
     */
    private static function listen(): void
    {
        while (true) {
            if ($readList = self::$handlerSocketManager->waitReads()) {
                foreach ($readList as $readSocket) {
                    switch ($readSocket) {
                        case Dispatcher::$handlerSocketManager->getEntranceSocket(): //TODO:入口发来消息
                            self::handleServiceOnline($readSocket);
                            break;
                        default:
                            $name = Manager::getNameBySocket($readSocket);
                            switch (Dispatcher::$socketHashMap[$name] ?? null) {
                                case Dispatcher::SOCKET_HANDLER: //TODO:来自处理的消息
                                    self::handleHandlerMessage($readSocket);
                                    break;
                                default: //TODO:集群消息可能得有
                                    break;
                            }
                            break;
                    }
                }
            } else {
                Console::debug("[Dispatcher] 等待消息异常");
            }
        }
    }

    /**
     * 事件消息服务器
     *
     * @param mixed $socket
     * @return void
     */
    private static function handleHandlerMessage(mixed $socket): void
    {
        $name   = Manager::getNameBySocket($socket);
        $client = self::$handlerSocketManager->getClientBySocket($socket);
        try {
            $package   = Build::getBuildByAgreement(Dispatcher::AGREE, $client);
            $publisher = $package->getPublisher();
            $message   = $package->getMessage();
            $event     = $package->getEvent();

            Console::debug("[Dispatcher]处理器消息 > " . $publisher);
            if ($event) {
                // 处理调度器内置事件
                if (self::handleBuiltEvent($event, $client)) {
                    return;
                }
                // 订阅者列表
                $subscribers = self::$subscribeManager->getSubscribesByPublishAndEvent($publisher, $event->getName());
                // 通知订阅事件
                foreach ($subscribers as $subscriber => $needType) {
                    self::notice($subscriber, $package, $needType);
                }
            }

            // 全局事件的订阅者列表
            $subscribers = self::$subscribeManager->getSubscribesByPublishAndEvent($publisher, 'DEFAULT');
            foreach ($subscribers as $subscriber => $needType) {
                self::notice($subscriber, $package, $needType);
            }
        } catch (Exception $e) {
            self::handleServiceOnBlack($client, $e->getMessage());
            return;
        }
    }

    /**
     * 获取一个服务实体
     *
     * @param string $name
     * @return \Cclilshy\PRipple\Dispatch\Service|null
     */
    private static function getServiceByName(string $name): Service|null
    {
        return self::$servers[$name] ?? null;
    }

    /**
     * 移除一个服务实体
     *
     * @param string $name
     * @return void
     */
    private static function removeServiceByName(string $name): void
    {
        if (isset(self::$servers[$name])) {
            unset(self::$servers[$name]);
        }
    }

    /**
     * 向订阅者发送通知
     *
     * @param string                           $subscriber
     * @param \Cclilshy\PRipple\Dispatch\Build $package
     * @param int                              $type
     * @return void
     */
    private static function notice(string $subscriber, Build $package, int $type): void
    {
        if (!$service = self::getServiceByName($subscriber)) {
            return;
        }
        switch ($type) {
            case Dispatcher::FORMAT_BUILD:
                $service->sendWithInt($package, Dispatcher::FORMAT_BUILD);
                break;
            case Dispatcher::FORMAT_EVENT:
                if ($event = $package->getEvent()) {
                    $service->sendWithInt($event->serialize(), Dispatcher::FORMAT_EVENT);
                }
                break;
            case Dispatcher::FORMAT_MESSAGE:
                if ($message = $package->getMessage()) {
                    $service->sendWithInt($message, Dispatcher::FORMAT_MESSAGE);
                }
                break;
        }
    }

    /**
     * 处理内置事件
     *
     * @param \Cclilshy\PRipple\Dispatch\EventTemplate\CommonTemplate $event 事件本身
     * @param \Cclilshy\PRipple\Communication\Socket\Client           $client
     * @return bool
     */
    private static function handleBuiltEvent(CommonTemplate $event, Client $client): bool
    {
        switch ($event->getName()) {
            case Dispatcher::PD_SUBSCRIBE: //TODO::订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    self::$subscribeManager->addSubscribes($subscribeInfo['publish'], $subscribeInfo['event'], $event->getPublisher(), $subscribeInfo['type']);
                }
                break;
            case Dispatcher::PD_SUBSCRIBE_UN: //TODO::卸载订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    self::$subscribeManager->unSubscribes($event->getPublisher(), $subscribeInfo['publish'], $subscribeInfo['event']);
                }
                break;
            case ServiceHandler::PS_START:  //TODO: 服务注册事件
                self::handleServiceRegister($event, $client);
                break;
            case ServiceHandler::PS_CLOSE: //TODO:: 正常关闭服务事件
                self::handleServiceOnclose($event, $client);
                break;
            default: //TODO::不是内置事件
                return false;
        }
        return true;
    }


    private static function handleServiceOnline(mixed $readSocket): void
    {
        $name                             = self::$handlerSocketManager->accept($readSocket);
        Dispatcher::$socketHashMap[$name] = Dispatcher::SOCKET_HANDLER;
    }

    private static function handleServiceRegister(CommonTemplate $event, Client $client): void
    {
        if (!$service = self::getServiceByName($event->getPublisher())) {
            $service = new Service($event->getPublisher(), $client);
            Console::debug("[Dispatcher] " . $event->getPublisher() . ' 上线');
            self::$servers[$event->getPublisher()] = $service;
            $service->setState(Service::STATE_START);
        } else {
            Console::debug("[Dispatcher] " . $event->getPublisher() . ' 重连');
            $service->handleServiceOnReconnect($client);
        }
        self::$handlerSocketManager->setIdentityBySocket($client->getSocket(), $event->getPublisher());
    }

    private static function handleServiceOnclose(CommonTemplate $event, Client $client): void
    {
        // 删除所有订阅事件
        self::$subscribeManager->unSubscriber($event->getPublisher());
        // 移除服务套接字
        if ($socketAisle = self::$handlerSocketManager->getClientSocketByName($event->getPublisher())) {
            self::$handlerSocketManager->removeClient($socketAisle);
        }
        // 移除服务对象
        self::removeServiceByName($event->getPublisher());
    }

    private static function handleServiceOnBlack(Client $client, string $message): void
    {
        Console::debug('处理器断开连接:', $message);
        if ($service = self::getServiceByName($client->getIdentity())) {
            if ($service->getState() !== Service::STATE_CLOSE) {
                $service->setState(Service::STATE_EXPECT);
                Console::debug('非正常退出:' . $client->getIdentity());
            }
        }
        self::$handlerSocketManager->removeClient($client->getSocket());
    }
}
