<?php

namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Service\SubscribeManager;
use Cclilshy\PRipple\Communication\Agreement\CCL;
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
            $readList = array_merge([Dispatcher::$handlerSocketManager->getEntranceSocket()], Dispatcher::$handlerSocketManager->getClientSockets() ?? [] // 服务器列表
            );
            $writeList  = [];
            $exceptList = [];
            if (!socket_select($readList, $writeList, $exceptList, null)) {
                Console::debug("[Dispatcher]等待消息异常");
            }

            foreach ($readList as $readSocket) {
                switch ($readSocket) {
                    case Dispatcher::$handlerSocketManager->getEntranceSocket():
                        $name                             = self::$handlerSocketManager->accept($readSocket);
                        $aisle                            = Dispatcher::$handlerSocketManager->getClientByName($name);
                        Dispatcher::$socketHashMap[$name] = Dispatcher::SOCKET_HANDLER;
                        Console::debug("[Dispatcher]处理器上线,等待处理器签名 .... ");
                        if ($package = Build::getBuildByAgreement(Dispatcher::AGREE, $aisle)) {
                            if ($event = $package->getEvent()) {
                                if ($event->getName() === ServiceHandler::PS_START) {
                                    $publisher = $package->getPublisher();
                                    if (!$service = self::getServiceByName($publisher)) {
                                        $service = new Service($publisher, $aisle);
                                        Console::debug("[Dispatcher]处理签名 > " . $publisher . ':上线');
                                        self::$servers[$publisher] = $service;
                                        $service->setState(Service::STATE_START);
                                    } else {
                                        Console::debug("[Dispatcher]处理签名 > " . $publisher . ':重连');
                                        $service->reConnect($aisle);
                                    }

                                    self::$handlerSocketManager->setIdentityBySocket($aisle->getSocket(), $publisher);

                                }
                            }
                        }
                        break;
                    default:
                        $name = Manager::getNameBySocket($readSocket);
                        switch (Dispatcher::$socketHashMap[$name] ?? null) {
                            case Dispatcher::SOCKET_HANDLER: //TODO::来自处理的消息
                                $handlerAisle = self::$handlerSocketManager->getClientBySocket($readSocket);
                                try {
                                    $package = Build::getBuildByAgreement(Dispatcher::AGREE, $handlerAisle);
                                    Dispatcher::handleHandlerMessage($package);
                                } catch (Exception $e) {
                                    Console::debug('处理器断开连接:', $e->getMessage());
                                    if ($service = self::getServiceByName($handlerAisle->getIdentity())) {
                                        if ($service->getState() !== Service::STATE_CLOSE) {
                                            $service->setState(Service::STATE_EXPECT);
                                            Console::debug('非正常退出:' . $handlerAisle->getIdentity());
                                        }
                                    }
                                    self::$handlerSocketManager->removeClient($readSocket);
                                }
                                break;
                            default:
                                # code...
                                break;
                        }
                        break;
                }
            }
        }
    }

    /**
     * 事件消息服务器
     *
     * @param \Cclilshy\PRipple\Dispatch\Build $package
     * @return void
     */
    private static function handleHandlerMessage(Build $package): void
    {
        $publisher = $package->getPublisher();
        $message   = $package->getMessage();
        $event     = $package->getEvent();
        Console::debug("[Dispatcher]处理器消息 > " . $publisher);
        if ($event) {
            // 处理调度器内置事件
            if (self::handleBuiltEvent($event, $publisher)) {
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
     * @param string                                                  $publisher
     * @return bool
     */
    private static function handleBuiltEvent(CommonTemplate $event, string $publisher): bool
    {
        switch ($event->getName()) {
            case Dispatcher::PD_SUBSCRIBE: //TODO::订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    self::$subscribeManager->addSubscribes($subscribeInfo['publish'], $subscribeInfo['event'], $publisher, $subscribeInfo['type']);
                }
                break;

            case Dispatcher::PD_SUBSCRIBE_UN: //TODO::卸载订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    self::$subscribeManager->unSubscribes($publisher, $subscribeInfo['publish'], $subscribeInfo['event']);
                }
                break;

            case ServiceHandler::PS_CLOSE: //TODO:: 正常关闭服务事件
                // 移除服务对象
                self::removeServiceByName($publisher);
                // 移除服务套接字
                if ($socketAisle = self::$handlerSocketManager->getClientSocketByName($publisher)) {
                    self::$handlerSocketManager->removeClient($socketAisle);
                }
                // 删除所有订阅事件
                self::$subscribeManager->unSubscriber($publisher);
                break;
            default: //TODO::不是内置事件
                return false;
        }
        return true;
    }
}
