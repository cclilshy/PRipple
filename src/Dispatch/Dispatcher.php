<?php

namespace Cclilshy\PRipple\Dispatch;

use Exception;
use Cclilshy\PRipple\Communication\Agreement\CCL;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Dispatch\standard\ServiceAbstract;
use Cclilshy\PRipple\Communication\Socket\ServerSocketManager;

// 事件调度
class Dispatcher
{
    // 规范
    const AGREE             = CCL::class;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              // 通信协议
    const LOCAL_STREAM_TYPE = SocketUnix::class;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              // 本地通信套接字类型
    const SERVER_AISLE_TYPE = SocketAisle::class;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             // 通信通道规范
    const UNIX_HANDLE       = PIPE_PATH . FS . __CLASS__ . SocketAisle::EXT;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  // 调度器套接字地址

    // 消息的套接字类型
    const SOCKET_CLIENT = 1;
    const SOCKET_HANDLER = 2;

    // 事件列表
    const E_SUBSCRIBE      = 'E_SUBSCRIBE';                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    // 订阅事件
    const E_HANDLER_START  = 'E_HANDLER_START';
    const E_HANDLER_EXPECT = 'E_HANDLER_EXPECT'; // 服务器发生异常事件
    const E_HANDLER_CLOSE  = 'E_HANDLER_CLOSE';  // 服务器关闭事件

    // 事件服务器列表，储存事件服务器实体
    private static array $eventHandlerList = [];

    // 服务器异常回收资源方法注册，运行栈主进程时注册，由主进程调用
    private static array $expectedEventList = [];

    // 调度用于接收本地服务器消息的套接字
    private static mixed $handlerServerSocket;

    // 套接字类型哈希表
    private static array $socketHashMap = array();

    // 订阅的列表
    private static array $subscribes;

    private static array $publisherSocketHashMap;
    // 这个类储存Lister对象
    // Lister 这个类很容易混淆部分属性
    // Lister 是个父子进程不同内存空间运行的栈
    private static array $listenerList;

    private static ServerSocketManager $handlerSocketManager;

    // 服务列表
    private static array $servers;

    /**
     * 注册一个事件服务器
     *
     * @param \Cclilshy\PRipple\Dispatch\standard\ServiceAbstract $eventHandler 已经实现事件服务器规范的对象
     * @return bool 创建成功与否
     */
    public static function register(ServiceAbstract $eventHandler): bool
    {
        return false;
    }

    /**
     * 在主进程的栈中处理，非子进程
     * 发生异常后给你机会,你要做的不是修复重启而是释放资源
     * 尽可能的别出错,影响其他事件服务器释放
     *
     * @param mixed $e
     * @return void
     */
    public static function exceptionHandler(mixed $e): void
    {
        // TODO:优先处理自身异常,之后处理其他注册的异常
    }

    /**
     * @return void
     */
    private static function launchEventHandlers(): void
    {
        foreach (Dispatcher::$eventHandlerList as $eventHandler) {
            if ($lister = Lister::launch($eventHandler)) {
                // 注册所有订阅
                foreach ($eventHandler->getSubscribers() as $item) {
                    list($eventName, $option) = $item;
                    Dispatcher::$subscribes[$eventName][$option][]             = $eventHandler->eventHandlerName;
                    Dispatcher::$listenerList[$eventHandler->eventHandlerName] = $lister;
                }
            }
        }
    }

    /**
     * 启动
     *
     * @return void
     */
    public static function launch(): void
    {
        try {
            // 注册公共事件列表
            Dispatcher::initPublicEventList();

            // 注册事件异常服务器，允许服务器注册
            //（在本进程调用栈中执行，仅回收资源，不可滥用，禁止奔溃）
            Dispatcher::registerExpectedEvent();

            // 监听服务
            Dispatcher::launchServer();

            // 启动事件服务器列表
            //            Dispatcher::launchEventHandlers();
        } catch (Exception $e) {
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
        //TODO::公共事件
    }

    /**
     * 启用所有事件服务器
     *
     * @return void
     */

    /**
     * 注册异常服务器
     *
     * @return void
     */
    private static function registerExpectedEvent(): void
    {
        // set_exception_handler([__CLASS__, 'exceptionHandler']);
    }

    /**
     * 启动服务
     *
     * @return void
     * @throws \Exception
     */
    private static function launchServer(): void
    {
        // 集群客户端列表,暂时不用
        //        Dispatcher::$serverSocketManager  = ServerSocketManager::createServer(Dispatcher::LOCAL_STREAM_TYPE, Dispatcher::UNIX_SERVER);
        Dispatcher::$handlerSocketManager = ServerSocketManager::createServer(Dispatcher::LOCAL_STREAM_TYPE, Dispatcher::UNIX_HANDLE);
    }

    /**
     * 开始监听服务
     *
     * @return void
     */
    private static function listen(): void
    {
        //        Dispatcher::$serverSocket        = Dispatcher::$serverSocketManager->getEntranceSocket();
        Dispatcher::$handlerServerSocket = Dispatcher::$handlerSocketManager->getEntranceSocket();
        while (true) {
            $readList   = array_merge([
                //                    Dispatcher::$serverSocket,
                Dispatcher::$handlerServerSocket
            ], //                Dispatcher::$serverSocketManager->getClientSockets() ?? [] // 集群客户端列表
                Dispatcher::$handlerSocketManager->getClientSockets() ?? [] // 服务器列表
            );
            $writeList  = [];
            $exceptList = [];
            if (!socket_select($readList, $writeList, $exceptList, null)) {
                //                foreach (Dispatcher::$listenerList as $lister) {
                //                    $lister->kill();
                //                }
                echo "等待消息异常" . PHP_EOL;
            }

            foreach ($readList as $readSocket) {
                switch ($readSocket) {
                    case Dispatcher::$handlerServerSocket:
                        echo '处理器上线了' . PHP_EOL;
                        $name                             = self::$handlerSocketManager->accept($readSocket);
                        $aisle                            = Dispatcher::$handlerSocketManager->getClientByName($name);
                        Dispatcher::$socketHashMap[$name] = Dispatcher::SOCKET_HANDLER;
                        break;
                    default:
                        $name = ServerSocketManager::getNameBySocket($readSocket);
                        switch (Dispatcher::$socketHashMap[$name] ?? null) {
                            case Dispatcher::SOCKET_HANDLER:
                                Dispatcher::handlerMessageHandler($readSocket);
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
     * @param mixed $messageSocket @连接Socket对象实体
     * @return void
     */
    private static function handlerMessageHandler(mixed $messageSocket): void
    {
        if ($messageAisle = self::$handlerSocketManager->getClientBySocket($messageSocket)) {
            try {
                if ($build = Build::getBuildByAgreement(Dispatcher::AGREE, $messageAisle)) {
                    $publisher = $build->getPublisher();
                    $message   = $build->getMessage();
                    $event     = $build->getEvent();
                    if ($event) {
                        if ($event->getName() === 'E_SUBSCRIBE') {
                            if ($subscribesList = $event->getData()) {
                                if (!is_array($subscribesList))
                                    return;
                                foreach ($subscribesList as $byPublisher => $byEvents) {
                                    if (!is_array($byEvents))
                                        return;
                                    foreach ($byEvents as $byEventName => $optionType) {
                                        if (is_string($byPublisher) || is_string($byEventName) || is_string($publisher) || is_string($optionType))
                                            return;
                                        self::addSubscribes($byPublisher, $byEventName, $publisher, $optionType);
                                    }
                                }
                            }
                            $messageAisle->setIdentity($publisher);
                            return;
                        }

                        /**
                         * @var ServiceAbstract $eventHandler
                         */
                        foreach (self::getSubscribesByPublishAndEvent($publisher, $event->getName()) as $publisher => $needType) {
                            $name  = self::$publisherSocketHashMap[$publisher];
                            $aisle = self::$handlerSocketManager->getClientByName($name);
                            switch ($needType) {
                                case Lister::FORMAT_BUILD:
                                    Dispatcher::AGREE::sendWithInt($aisle, (string)$build, Lister::FORMAT_BUILD);
                                    break;
                                case Lister::FORMAT_EVENT:
                                    Dispatcher::AGREE::sendWithInt($aisle, $event->serialize(), Lister::FORMAT_EVENT);
                                    break;
                                case Lister::FORMAT_MESSAGE:
                                    if ($message) {
                                        Dispatcher::AGREE::sendWithInt($aisle, $message, Lister::FORMAT_MESSAGE);
                                    }
                                    break;
                            }
                        }
                    }
                }
            }catch (Exception $exception){
                echo '可能处理器断开连接:' . $exception->getMessage() . PHP_EOL;
                self::$handlerSocketManager->removeClient($messageSocket);
            }
        }
    }

    /**
     * @param string $publish    订阅的发布者
     * @param string $eventName  事件名称
     * @param string $subscriber 声明订阅者
     * @param int    $option
     * @return void
     */
    private static function addSubscribes(string $publish, string $eventName, string $subscriber, int $option): void
    {
        self::$subscribes[$publish][$eventName][$subscriber] = $option;
    }

    private static function getSubscribesByPublishAndEvent(string $publish, string $event): array
    {
        return self::$subscribes[$publish][$event] ?? [];
    }

    /**
     * 客户端消息服务器
     *
     * @param mixed $messageSocket @连接Socket对象实体
     * @return void
     */
    private static function clientMessageHandler(mixed $messageSocket): void
    {
        if ($messageAisle = self::$serverSocketManager->getClientAisleBySocket($messageSocket)) {
            if ($context = $messageAisle->read()) {
                foreach (Dispatcher::$subscribes[Lister::P_TCP_ORIGINAL][Lister::FORMAT_MESSAGE] ?? [] as $eventHandlerName) {
                    self::$listenerList[$eventHandlerName]->notice(Lister::FORMAT_MESSAGE, $context);
                }
            } else {
                self::$serverSocketManager->removeClient($messageSocket);
            }
        }
    }

}
