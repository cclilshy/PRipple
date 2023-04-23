<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Log;
use Cclilshy\PRipple\PRipple;
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

/**
 *
 */
class Dispatcher
{
    public const AGREE = CCL::class;
    public const LOCAL_STREAM_TYPE = SocketUnix::class;
    public const MSF_CONTROL       = 1;
    public const MSF_HANDLER = 2;
    public const PD_SUBSCRIBE = 'PD_SUBSCRIBE';
    public const PD_SUBSCRIBE_UN = 'PD_SUBSCRIBE_UN';
    public const PE_DISPATCHER_CLOSE = 'PE_DISPATCHER_CLOSE';
    public const FORMAT_BUILD = 1;
    public const FORMAT_EVENT = 2;
    public const FORMAT_MESSAGE = 3;

    public static string            $handleServiceUnixAddress  = PRIPPLE_SOCK_PATH . FS . 'dispatcher_handle' . SocketAisle::EXT;
    public static string            $controlServiceUnixAddress = PRIPPLE_SOCK_PATH . FS . 'dispatcher_control' . SocketAisle::EXT;
    public static ServiceInfo       $serviceInfo;
    private static array            $socketHashMap             = array();
    private static SubscribeManager $subscribeManager;
    private static SocketManager    $handlerSocketManager;
    private static SocketManager    $controlSocketManager;
    private static array            $servers;
    private static int              $lastUpdateStatusTime      = 0;

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
            Log::pdebug("[Dispatcher]", $e->getMessage());
            return;
        }
        try {
            Dispatcher::listen();
        } catch (Exception $e) {
            Log::pdebug($e->getMessage());
        }
    }

    /**
     * 注册公共事件
     *
     * @return void
     */
    private static function initPublicEventList(): void
    {
        Dispatcher::$subscribeManager = new SubscribeManager();
        //TODO:公共事件
    }

    /**
     * 启动服务
     *
     * @return void
     * @throws \Exception
     */
    private static function launchServer(): void
    {
        Dispatcher::$servers = [];
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
                Dispatcher::$controlSocketManager->getEntranceSocket(),
                Dispatcher::$handlerSocketManager->getEntranceSocket()
            ], Dispatcher::$controlSocketManager->getClientSockets() ?? [], Dispatcher::$handlerSocketManager->getClientSockets() ?? []);
            if (socket_select($readList, $writeList, $exceptList, 0, 1000000)) {
                foreach ($readList as $readSocket) {
                    switch ($readSocket) {
                        case Dispatcher::$handlerSocketManager->getEntranceSocket():
                            //TODO:服务入口有连接
                            // Dispatcher::handleServiceOnline($readSocket);
                            $name                             = Dispatcher::$handlerSocketManager->accept($readSocket);
                            Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_HANDLER;
                            break;
                        case Dispatcher::$controlSocketManager->getEntranceSocket():
                            //TODO:控制入口有连接
                            $name = Dispatcher::$controlSocketManager->accept($readSocket);
                            if ($client = Dispatcher::$controlSocketManager->getClientByName($name)) {
                                $client->setNoBlock();
                            }
                            Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_CONTROL;
                            break;
                        default:
                            $name = SocketManager::getNameBySocket($readSocket);
                            switch (Dispatcher::$socketHashMap[$name] ?? null) {
                                case Dispatcher::MSF_HANDLER:
                                    //TODO:服务消息
                                    Dispatcher::handleHandlerMessage($readSocket);
                                    break;
                                case Dispatcher::MSF_CONTROL:
                                    //TODO:控制消息
                                    try {
                                        Dispatcher::handleControlMessage($readSocket);
                                    } catch (Exception $e) {
                                        Dispatcher::$controlSocketManager->removeClient($readSocket);
                                        Log::pdebug("[Dispatcher]", "Controller exit," . $e->getMessage());
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
                Dispatcher::$handlerSocketManager->handleBufferContext();
                Dispatcher::$controlSocketManager->handleBufferContext();
                gc_collect_cycles();
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
        $client = Dispatcher::$handlerSocketManager->getClientBySocket($socket);
        try {
            $package   = Build::getBuildByAgreement(Dispatcher::AGREE, $client);
            $publisher = $package->getPublisher();
            $message   = $package->getMessage();
            $event     = $package->getEvent();

            if ($event) {
                Log::pdebug('[' . $event->getPublisher() . "]release " . $event->getName() . " event");
                // 处理调度器内置事件
                if (Dispatcher::handleBuiltEvent($event, $client)) {
                    return;
                }
                // 订阅者列表
                $subscribers = Dispatcher::$subscribeManager->getSubscribesByPublishAndEvent($publisher, $event->getName());
                // 通知订阅事件
                foreach ($subscribers as $subscriber => $options) {
                    if ($subscriber === 'count') {
                        continue;
                    }
                    Dispatcher::notice($subscriber, $package, $options['type']);
                    if (isset($options['oneOff']) && $options['oneOff'] === true) {
                        Dispatcher::$subscribeManager->unSubscribes($subscriber, $publisher, $event->getName());
                    }
                }
            }

            // 全局事件的订阅者列表
            $subscribers = Dispatcher::$subscribeManager->getSubscribesByPublishAndEvent($publisher, 'DEFAULT');
            foreach ($subscribers as $subscriber => $options) {
                Dispatcher::$subscribeManager->recordHappen($event);
                Dispatcher::notice($subscriber, $package, $options['type']);
                if (isset($options['oneOff']) && $options['oneOff'] === true) {
                    Dispatcher::$subscribeManager->unSubscribes($subscriber, $publisher, $event->getName());
                }
            }
        } catch (Exception $e) {
            Dispatcher::handleServiceOnBlack($client, $e->getMessage());
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
                //TODO:订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    Dispatcher::$subscribeManager->addSubscribes($subscribeInfo['publish'], $subscribeInfo['event'], $event->getPublisher(), $subscribeInfo);
                }
                break;
            case Dispatcher::PD_SUBSCRIBE_UN:
                //TODO: 卸载订阅事件
                if (is_array($subscribeInfo = $event->getData())) {
                    Dispatcher::$subscribeManager->unSubscribes($event->getPublisher(), $subscribeInfo['publish'], $subscribeInfo['event']);
                }
                break;
            case ServiceHandler::PS_START:
                //TODO 服务注册事件
                Dispatcher::handleServiceRegister($event, $client);
                break;
            case ServiceHandler::PS_CLOSE:
                //TODO: 正常关闭服务事件
                Dispatcher::handleServiceOnclose($event, $client);
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
        if (!$service = Dispatcher::getServiceByName($event->getPublisher())) {
            $service = new Service($event->getPublisher(), $client);
            $msg     = "[Dispatcher]" . $event->getPublisher() . ' online';

            Log::pdebug($msg);
            Dispatcher::$servers[$event->getPublisher()] = $service;
            $service->setState(Service::STATE_START);
        } else {
            $msg = "[Dispatcher]" . $event->getPublisher() . ' reconnect';
            Log::pdebug($msg);

            $service->handleServiceOnReconnect($client);
        }
        Log::realTimeOutput($msg);
        Dispatcher::$handlerSocketManager->setIdentityBySocket($client->getSocket(), $event->getPublisher());
    }

    /**
     * 获取一个服务实体
     *
     * @param string $name
     * @return \Cclilshy\PRipple\Dispatch\Service|null
     */
    private static function getServiceByName(string $name): Service|null
    {
        return Dispatcher::$servers[$name] ?? null;
    }

    public static function print(string $message, bool|null $upStatus = false): void
    {
        if (isset(Dispatcher::$controlSocketManager)) {
            if ($list = Dispatcher::$controlSocketManager->getClientSockets()) {
                foreach ($list as $controlSocket) {
                    $client = Dispatcher::$controlSocketManager->getClientBySocket($controlSocket);
                    if ($upStatus || Dispatcher::$lastUpdateStatusTime + 10 < time()) {
                        Dispatcher::updateStatus($client);
                    }
                    Dispatcher::AGREE::sendWithInt($client, $message, Dispatcher::FORMAT_MESSAGE);
                }
            }
        }
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public static function updateStatus(Client $client): void
    {
        Dispatcher::$lastUpdateStatusTime = time();
        $event                      = new Event('dispatcher', 'services', Dispatcher::$servers);
        Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
        $event = new Event('dispatcher', 'subscribes', Dispatcher::$subscribeManager->getSubscribes());
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
        Dispatcher::$subscribeManager->unSubscriber($event->getPublisher());
        // 移除服务套接字
        if ($socketAisle = Dispatcher::$handlerSocketManager->getClientSocketByName($event->getPublisher())) {
            Dispatcher::$handlerSocketManager->removeClient($socketAisle);
        }
        // 移除服务对象
        Dispatcher::removeServiceByName($event->getPublisher());
    }

    /**
     * 移除一个服务实体
     *
     * @param string $name
     * @return void
     */
    private static function removeServiceByName(string $name): void
    {
        if (isset(Dispatcher::$servers[$name])) {
            unset(Dispatcher::$servers[$name]);
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
        if (!$service = Dispatcher::getServiceByName($subscriber)) {
            return;
        }
        switch ($type) {
            case Dispatcher::FORMAT_BUILD:
                $service->sendWithInt($package->serialize(), Dispatcher::FORMAT_BUILD);
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
        Log::realTimeOutput($msg, true);
        Log::pdebug($msg);
        if ($service = Dispatcher::getServiceByName($client->getIdentity())) {
            if ($service->getState() !== Service::STATE_CLOSE) {
                $service->setState(Service::STATE_EXPECT);
                $msg = "[Dispatcher]" . '异常退出' . $client->getIdentity();
                Log::realTimeOutput($msg, true);
                Log::pdebug($msg);
            }
        }
        Dispatcher::$handlerSocketManager->removeClient($client->getSocket());
    }

    /**
     * @param mixed $socket
     * @return void
     */
    private static function handleControlMessage(mixed $socket): void
    {
        if (!$client = Dispatcher::$controlSocketManager->getClientBySocket($socket)) {
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
                $event = new Event('dispatcher', 'subscribes', Dispatcher::$subscribeManager->getSubscribes());
                Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
                break;
            case 'getServices':
                $event = new Event('dispatcher', 'services', Dispatcher::$servers);
                Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
                break;
            case 'getServiceInfo':
                $event = new Event('dispatcher', 'serviceInfo', Dispatcher::$servers[$event->getData()] ?? null);
                Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
                break;
            case 'termination':
                if ($serviceInfo = ServiceInfo::load('dispatcher')) {
                    foreach (Dispatcher::$socketHashMap as $socketHash => $socketType) {
                        if (Dispatcher::MSF_HANDLER === $socketType) {
                            $client = Dispatcher::$handlerSocketManager->getClientByName($socketHash);
                            $event  = new Event('Dispatcher', Dispatcher::PE_DISPATCHER_CLOSE, null);
                            $build  = new Build('Dispatcher', null, $event);
                            Dispatcher::notice($client->getIdentity(), $build, Dispatcher::FORMAT_EVENT);
                        }
                    }
                    $serviceInfo->release();
                }
                Log::print("[Dispatcher] is closed.");
                PRipple::stop();
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
        $name                             = Dispatcher::$handlerSocketManager->accept($readSocket);
        Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_HANDLER;
    }
}