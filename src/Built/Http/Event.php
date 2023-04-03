<?php

declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-15 20:43:00
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

use Fiber;
use Cclilshy\PRipple\Route\Route;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Built\Http\Text\Text;
use Cclilshy\PRipple\Communication\Socket\Client;

class Event
{
    public array   $tasks        = array();
    public array   $requests     = array();
    public array   $arrayHashMap = array();
    public Service $httpService;

    public function __construct(Service $httpService)
    {
        $this->httpService = $httpService;
    }

    public function access(Request $request): void
    {
        $client                                                         = $request->clientSocket;
        $this->requests[$request->getHash()]                            = $request;
        $this->arrayHashMap[$client->getKeyName()][$request->getHash()] = 1;
        $this->tasks[$request->getHash()]                               = new Fiber(function () use ($request) {
            if ($request->isStatic()) {
                $response = $request->route->run($request);
            } else {
                if (!isset($request->route)) {
                    $result = (Text::htmlErrorPage(404, "Not match Route '{$request->path}'", __FILE__, __LINE__, $request, $request->statistics));
                } else {
                    switch ($request->route->type) {
                        case 'Controller':
                            $className = $request->route->className;
                            $_         = new $className($request);
                            $result    = call_user_func([$_, $request->route->action], $request);
                            break;
                        case 'Closure':
                            $request->route = Route::guide($request->method(), $request->path);
                            $result         = call_user_func($request->route->callable, $request);
                            break;
                        default:
                            $result = '';
                            break;
                    }
                }
                $response = $request->response->setBody($result);
            }
            $event = new \Cclilshy\PRipple\Dispatch\DataStandard\Event($request->getHash(), 'response', $response);
            Fiber::suspend($event);
        });
        $event                                                          = $this->tasks[$request->getHash()]->start();
        $this->extracted($event);
    }


    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return void
     */
    public function extracted(\Cclilshy\PRipple\Dispatch\DataStandard\Event $event): void
    {
        switch ($event->getName()) {
            case 'response':
                $response = $event->getData();
                $request  = $response->request;
                $client   = $request->clientSocket;
                $client->write($response->__toString());
                unset($this->tasks[$request->getHash()]);
                unset($this->arrayHashMap[$client->getKeyName()][$request->getHash()]);
                break;
            case 'sleep':
                $requestHash = $event->getPublisher();
                if ($request = $this->requests[$requestHash] ?? null) {
                    $data = $event->getData();
                    $name = 'sleep:' . $request->getHash();
                    $this->httpService->subscribe('Timer', $name, Dispatcher::FORMAT_EVENT, ['oneOff' => true]);
                    $this->httpService->publishEvent('sleep', [
                        'time' => $data['time'],
                        'name' => $name
                    ]);
                }
                break;
            default:
                if ($event->getPublisher() === 'Timer') {
                    $name = $event->getName();
                    list($type, $hash) = explode(':', $name);
                    if ($type === 'sleep') {
                        if ($task = $this->tasks[$hash] ?? null) {
                            $event = $task->resume();
                            $this->extracted($event);
                        }
                    }
                }
        }
    }

    public function break(Client $client): void
    {
        foreach ($this->arrayHashMap[$client->getKeyName()] ?? [] as $requestHash => $_) {
            unset($this->requests[$requestHash]);
        }
    }
}
