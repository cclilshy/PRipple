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

// event scheduling


class Dispatcher
{
    public const AGREE               = CCL::class;
    public const LOCAL_STREAM_TYPE   = SocketUnix::class;
    public const MSF_CONTROL         = 1;
    public const MSF_HANDLER         = 2;
    public const PD_SUBSCRIBE        = 'PD_SUBSCRIBE';
    public const PD_SUBSCRIBE_UN     = 'PD_SUBSCRIBE_UN';
    public const PE_DISPATCHER_CLOSE = 'PE_DISPATCHER_CLOSE';
    public const FORMAT_BUILD        = 1;
    public const FORMAT_EVENT        = 2;
    public const FORMAT_MESSAGE      = 3;

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
     * initiate
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
     * Register for a public event
     *
     * @return void
     */
    private static function initPublicEventList(): void
    {
        Dispatcher::$subscribeManager = new SubscribeManager();
        //TODO:Public events
    }

    /**
     * Start the service
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
     * Start listening to the service
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
                            //TODO:There is a connection to the service portal
                            // Dispatcher::handleServiceOnline($readSocket);
                            $name                             = Dispatcher::$handlerSocketManager->accept($readSocket);
                            Dispatcher::$socketHashMap[$name] = Dispatcher::MSF_HANDLER;
                            break;
                        case Dispatcher::$controlSocketManager->getEntranceSocket():
                            //TODO:The control portal has a connection
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
                                    //TODO:Service messages
                                    Dispatcher::handleHandlerMessage($readSocket);
                                    break;
                                case Dispatcher::MSF_CONTROL:
                                    //TODO:Control messages
                                    try {
                                        Dispatcher::handleControlMessage($readSocket);
                                    } catch (Exception $e) {
                                        Dispatcher::$controlSocketManager->removeClient($readSocket);
                                        Log::pdebug("[Dispatcher]", "Controller exit," . $e->getMessage());
                                    }
                                    break;
                                default:
                                    //TODO:Cluster messages may have to be
                                    break;
                            }
                            break;
                    }
                }
            } else {
                // TODO:Process buffered data
                Dispatcher::$handlerSocketManager->handleBufferContext();
                Dispatcher::$controlSocketManager->handleBufferContext();
                gc_collect_cycles();
            }
        }
    }

    /**
     * Event message server
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
                // Handle scheduler built-in events
                if (Dispatcher::handleBuiltEvent($event, $client)) {
                    return;
                }
                // List of subscribers
                $subscribers = Dispatcher::$subscribeManager->getSubscribesByPublishAndEvent($publisher, $event->getName());
                // Notification subscription events
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

            // A list of subscribers to the global event
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
     * Handle built-in events
     *
     * @param Event  $event The event itself
     * @param Client $client
     * @return bool
     */
    private static function handleBuiltEvent(Event $event, Client $client): bool
    {
        switch ($event->getName()) {
            case Dispatcher::PD_SUBSCRIBE:
                //TODO: Subscribe to events
                if (is_array($subscribeInfo = $event->getData())) {
                    Dispatcher::$subscribeManager->addSubscribes($subscribeInfo['publish'], $subscribeInfo['event'], $event->getPublisher(), $subscribeInfo);
                }
                break;
            case Dispatcher::PD_SUBSCRIBE_UN:
                //TODO: Uninstall the subscription event
                if (is_array($subscribeInfo = $event->getData())) {
                    Dispatcher::$subscribeManager->unSubscribes($event->getPublisher(), $subscribeInfo['publish'], $subscribeInfo['event']);
                }
                break;
            case ServiceHandler::PS_START:
                //TODO: The service registers for events
                Dispatcher::handleServiceRegister($event, $client);
                break;
            case ServiceHandler::PS_CLOSE:
                //TODO: Graceful shutdown of service events
                Dispatcher::handleServiceOnclose($event, $client);
                break;
            default:
                //TODO: Not a built-in event
                return false;
        }
        return true;
    }

    /**
     * Service socket claim registration
     *
     * @param Event  $event
     * @param Client $client
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
     * Gets a service entity
     *
     * @param string $name
     * @return \Cclilshy\PRipple\Dispatch\Service|null
     */
    private static function getServiceByName(string $name): Service|null
    {
        return Dispatcher::$servers[$name] ?? null;
    }

    /**
     * The service socket declaration is closed
     *
     * @param Event  $event
     * @param Client $client
     * @return void
     */
    private static function handleServiceOnclose(Event $event, Client $client): void
    {
        // Delete all subscription events
        Dispatcher::$subscribeManager->unSubscriber($event->getPublisher());
        // Remove the service socket
        if ($socketAisle = Dispatcher::$handlerSocketManager->getClientSocketByName($event->getPublisher())) {
            Dispatcher::$handlerSocketManager->removeClient($socketAisle);
        }
        // Remove a service object
        Dispatcher::removeServiceByName($event->getPublisher());
    }

    /**
     * Remove a service entity
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
     * Send notifications to subscribers
     *
     * @param string $subscriber
     * @param Build  $package
     * @param int    $type
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
     * The service socket is disconnected
     *
     * @param Client $client
     * @param string $message
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
                $msg = "[Dispatcher]" . 'Exit abnormally' . $client->getIdentity() . PHP_EOL;
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
                // TODO: send close command `PE_DISPATCHER_CLOSE`
                $serviceInfo = ServiceInfo::load('dispatcher');
                foreach (Dispatcher::$socketHashMap as $socketHash => $socketType) {
                    if (Dispatcher::MSF_HANDLER === $socketType) {
                        if ($client = Dispatcher::$handlerSocketManager->getClientByName($socketHash)) {
                            $event = new Event('Dispatcher', Dispatcher::PE_DISPATCHER_CLOSE, null);
                            $build = new Build('Dispatcher', null, $event);
                            Dispatcher::notice($client->getIdentity(), $build, Dispatcher::FORMAT_EVENT);
                        }
                    }
                }
                $serviceInfo->release();
                PRipple::stop();
                exit;
            default:
                # code...
                break;
        }
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
     * @param Client $client
     * @return void
     */
    public static function updateStatus(Client $client): void
    {
        Dispatcher::$lastUpdateStatusTime = time();
        $event                            = new Event('dispatcher', 'services', Dispatcher::$servers);
        Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
        $event = new Event('dispatcher', 'subscribes', Dispatcher::$subscribeManager->getSubscribes());
        Dispatcher::AGREE::sendWithInt($client, $event->serialize(), Dispatcher::FORMAT_EVENT);
    }

    /**
     * A new connection joins
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