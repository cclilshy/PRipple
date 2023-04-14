<?php

namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Service\ServiceInfo;
use Cclilshy\PRipple\Communication\Agreement\CCL;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Service\Service as ServiceHandler;
use Cclilshy\PRipple\Communication\Socket\Manager as SocketManager;

// 事件调度
class Dispatcher
{
    const AGREE               = CCL::class;
    const LOCAL_STREAM_TYPE   = SocketUnix::class;
    const MSF_CONTROL         = 1;
    const MSF_HANDLER         = 2;
    const PD_SUBSCRIBE        = 'PD_SUBSCRIBE';
    const PD_SUBSCRIBE_UN     = 'PD_SUBSCRIBE_UN';
    const PE_DISPATCHER_CLOSE = 'PE_DISPATCHER_CLOSE';

    const FORMAT_BUILD   = 1;
    const FORMAT_EVENT   = 2;
    const FORMAT_MESSAGE = 3;

    public static string $handleServiceUnixAddress  = PRIPPLE_SOCK_PATH . FS . 'dispatcher_handle' . SocketAisle::EXT;
    public static string $controlServiceUnixAddress = PRIPPLE_SOCK_PATH . FS . 'dispatcher_control' . SocketAisle::EXT;
    private static array $socketHashMap             = array();

    private static SubscribeManager $subscribeManager;
    private static SocketManager    $handlerSocketManager;
    private static SocketManager    $controlSocketManager;
    private static array            $servers;
    private static int              $lastUpdateStatusTime = 0;
    public static ServiceInfo       $serviceInfo;

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
            Console::debug("[Dispatcher]", $e->getMessage());
            return;
        }
        try {
            self::listen();
        } catch (Exception $e) {
            Console::debug($e->getMessage());
        }
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
        self::$servers = [];
        if (file_exists(Dispatcher::$handleServiceUnixAddress)) {
            unlink(Dispatcher::$handleServiceUnixAddress);
        }
        if (file_exists(Dispatcher::$controlServiceUnixAddress)) {
            unlink(Dispatcher::$controlServiceUnixAddress);
        }

        Dispatcher::$handlerSocketManager = SocketManager::createServer(Dispatcher::LOCAL_STREAM_TYPE, Dispatcher::$handleServiceUnixAddress);
        Dispatcher::$controlSocketManager = SocketManager::createServer(Dispatcher::LOCAL_STREAM_TYPE, Dispatcher::$controlServiceUnixAddress);
    }

    /**
     * 开始监听服务
     *
     * @return void
     * @throws \Exception
     */
    private static function listen(): void
    {
        Dispatcher::$serviceInfo->unLock();
        while (true) {
            $readList = array_merge([
                self::$controlSocketManager->getEntranceSocket(),
                self::$handlerSocketManager->getEntranceSocket()
            ], self::$controlSocketManager->getClientSockets() ?? [], self::$handlerSocketManager->getClientSockets() ?? []);
            if (socket_select($readList, $writeList, $exceptList, 0, 1000000)) {
                foreach ($readList as $readSocket) {
                    switch ($readSocket) {
                        case Dispatcher::$handlerSocketManager->getEntranceSocket():
                            //TODO:服务入口有连接
                            // self::handleServiceOnline($readSocket);
                            $name                             = self::$handlerSocketManager->accept($readSocket);
                            Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_HANDLER;
                            break;
                        case Dispatcher::$controlSocketManager->getEntranceSocket():
                            //TODO:控制入口有连接
                            $name = self::$controlSocketManager->accept($readSocket);
                            if ($client = self::$controlSocketManager->getClientByName($name)) {
                                $client->setNoBlock();
                            }
                            Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_CONTROL;
                            break;
                        default:
                            $name = SocketManager::getNameBySocket($readSocket);
                            switch (Dispatcher::$socketHashMap[$name] ?? null) {
                                case Dispatcher::MSF_HANDLER:
                                    //TODO:来自处理的消息
                                    self::handleHandlerMessage($readSocket);
                                    break;
                                case Dispatcher::MSF_CONTROL:
                                    try {
                                        self::handleControlMessage($readSocket);
                                    } catch (Exception $e) {
                                        self::$controlSocketManager->removeClient($readSocket);
                                        Console::debug("[Dispatcher]", "Controller exit," . $e->getMessage());
                                    }
                                    break;
                                default:
                                    //TODO:集群消息可能得有
                                    break;
                            }
                            break;
                    }
                }
            } else {
                // TODO:处理缓冲数据
                //                Console::debug("[Dispatcher]", '清理缓冲数据');
                self::$handlerSocketManager->handleBufferContext();
                // self::$controlSocketManager->handleBufferContext();
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
        $name   = SocketManager::getNameBySocket($socket);
        $client = self::$handlerSocketManager->getClientBySocket($socket);
        try {
            $package   = Build::getBuildByAgreement(Dispatcher::AGREE, $client);
            $publisher = $package->getPublisher();
            $message   = $package->getMessage();
            $event     = $package->getEvent();

            if ($event) {
                Console::debug($event->getPublisher() . " release " . $event->getName() . " event");
                // 处理调度器内置事件
                if (self::handleBuiltEvent($event, $client)) {
                    return;
                }
                // 订阅者列表
                $subscribers = self::$subscribeManager->getSubscribesByPublishAndEvent($publisher, $event->getName());
                // 通知订阅事件
                foreach ($subscribers as $subscriber => $options) {
                    if ($subscriber === 'count') {
                        continue;
                    }
                    self::notice($subscriber, $package, $options['type']);
                    if (isset($options['oneOff']) && $options['oneOff'] === true) {
                        self::$subscribeManager->unSubscribes($subscriber, $publisher, $event->getName());
                    }
                }
            }

            // 全局事件的订阅者列表
            $subscribers = self::$subscribeManager->getSubscribesByPublishAndEvent($publisher, 'DEFAULT');
            foreach ($subscribers as $subscriber => $options) {
                self::$subscribeManager->recordHappen($event);
                self::notice($subscriber, $package, $options['type']);
                if (isset($options['oneOff']) && $options['oneOff'] === true) {
                    self::$subscribeManager->unSubscribes($subscriber, $publisher, $event->getName());
                }
            }
        } catch (Exception $e) {
            self::handleServiceOnBlack($client, $e->getMessage());
            return;
        }
    }

    /**
     * 处理内置事件
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event 事件本身
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return bool
     */
    private static function handleBuiltEvent(Event $event, Client $client): bool
    {
        switch ($event->getName()) {
            case Dispatcher::PD_SUBSCRIBE:
                //TODO::订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    self::$subscribeManager->addSubscribes($subscribeInfo['publish'], $subscribeInfo['event'], $event->getPublisher(), $subscribeInfo);
                }
                break;
            case Dispatcher::PD_SUBSCRIBE_UN:
                //TODO: 卸载订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    self::$subscribeManager->unSubscribes($event->getPublisher(), $subscribeInfo['publish'], $subscribeInfo['event']);
                }
                break;
            case ServiceHandler::PS_START:
                //TODO 服务注册事件
                self::handleServiceRegister($event, $client);
                break;
            case ServiceHandler::PS_CLOSE:
                //TODO: 正常关闭服务事件
                self::handleServiceOnclose($event, $client);
                break;
            default:
                //TODO: 不是内置事件
                return false;
        }
        return true;
    }

    /**
     * 服务套接字声明注册
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    private static function handleServiceRegister(Event $event, Client $client): void
    {
        $client->setNoBlock();
        if (!$service = self::getServiceByName($event->getPublisher())) {
            $service = new Service($event->getPublisher(), $client);
            $msg     = "[Dispatcher]" . $event->getPublisher() . ' online';

            Console::debug($msg);
            self::$servers[$event->getPublisher()] = $service;
            $service->setState(Service::STATE_START);
        } else {
            $msg = "[Dispatcher]" . $event->getPublisher() . ' reconnect';
            Console::debug($msg);

            $service->handleServiceOnReconnect($client);
        }
        self::noticeControl($msg, true);
        self::$handlerSocketManager->setIdentityBySocket($client->getSocket(), $event->getPublisher());
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

    public static function noticeControl(string $message, bool|null $upStatus = false): void
    {
        if (isset(self::$controlSocketManager)) {
            if ($list = self::$controlSocketManager->getClientSockets()) {
                foreach ($list as $controlSocket) {
                    $client = self::$controlSocketManager->getClientBySocket($controlSocket);
                    if ($upStatus || self::$lastUpdateStatusTime + 10 < time()) {
                        self::updateStatus($client);
                    }
                    Dispatcher::AGREE::sendWithInt($client, $message, Dispatcher::FORMAT_MESSAGE);
                }
            }
        }
    }

    public static function updateStatus(Client $client): void
    {
        self::$lastUpdateStatusTime = time();
        $event                      = new Event('dispatcher', 'services', self::$servers);
        Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
        $event = new Event('dispatcher', 'subscribes', self::$subscribeManager->getSubscribes());
        Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
    }

    /**
     * 服务套接字声明关闭
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    private static function handleServiceOnclose(Event $event, Client $client): void
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
     * @param string                                        $subscriber
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Build $package
     * @param int                                           $type
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
     * 服务套接字断开连接
     *
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @param string                                        $message
     * @return void
     */
    private static function handleServiceOnBlack(Client $client, string $message): void
    {
        $msg = '[Dispatcher]' . 'link on block' . $message;
        self::noticeControl($msg, true);
        Console::debug($msg);
        if ($service = self::getServiceByName($client->getIdentity())) {
            if ($service->getState() !== Service::STATE_CLOSE) {
                $service->setState(Service::STATE_EXPECT);
                $msg = "[Dispatcher]" . '异常退出' . $client->getIdentity();
                self::noticeControl($msg, true);
                Console::debug($msg);
            }
        }
        self::$handlerSocketManager->removeClient($client->getSocket());
    }

    private static function handleControlMessage(mixed $socket): void
    {
        if (!$client = self::$controlSocketManager->getClientBySocket($socket)) {
            return;
        }

        if (!$build = Build::getBuildByAgreement(Dispatcher::AGREE, $client)) {
            return;
        }

        if (!$event = $build->getEvent()) {
            return;
        }

        switch ($event->getName()) {
            case 'getSubscribes':
                $event = new Event('dispatcher', 'subscribes', self::$subscribeManager->getSubscribes());
                Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
                break;
            case 'getServices':
                $event = new Event('dispatcher', 'services', self::$servers);
                Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
                break;
            case 'getServiceInfo':
                $event = new Event('dispatcher', 'serviceInfo', self::$servers[$event->getData()] ?? null);
                Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
                break;
            case 'termination':
                if ($serviceInfo = ServiceInfo::load('dispatcher')) {
                    foreach (Dispatcher::$socketHashMap as $socketHash => $socketType) {
                        if (Dispatcher::MSF_HANDLER === $socketType) {
                            $client = Dispatcher::$handlerSocketManager->getClientByName($socketHash);
                            $event  = new Event('', Dispatcher::PE_DISPATCHER_CLOSE, null);
                            $build  = new Build('', null, $event);
                            Dispatcher::notice($client->getIdentity(), $build, self::FORMAT_EVENT);
                        }
                    }
                    $serviceInfo->release();
                }
                Console::pdebug("[Dispatcher]", "closed");
                exit;
            default:
                # code...
                break;
        }
    }

    /**
     * 有新连接加入
     *
     * @param mixed $readSocket
     * @return void
     * @throws \Exception
     */
    private static function handleServiceOnline(mixed $readSocket): void
    {
        $name                             = self::$handlerSocketManager->accept($readSocket);
        Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_HANDLER;
    }
}
