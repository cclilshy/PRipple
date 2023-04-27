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
use Cclilshy\PRipple\Dispatch\DataStandard\Event as EventStandard;


class Event
{
    public array   $fibers       = array();
    public array   $requests     = array();
    public array   $arrayHashMap = array();
    public Service $httpService;

    /**
     * @param Service $httpService
     */
    public function __construct(Service $httpService)
    {
        $this->httpService = $httpService;
    }

    /**
     * @param Request $request
     * @return void
     */
    public function access(Request $request): void
    {
        $client = $request->clientSocket;
        if ($this->httpService->config('fiber')) {
            $this->requests[$request->getHash()]                            = $request;
            $this->arrayHashMap[$client->getKeyName()][$request->getHash()] = 1;
            $this->fibers[$request->getHash()]                              = new Fiber(function () use ($request) {
                $this->goController($request);
            });
            $event                                                          = $this->fibers[$request->getHash()]->start();
            $this->extracted($event);
        } else {
            $response = $this->goController($request);
            $client->write($response->__toString());
        }

    }

    public function goController(Request $request): Response
    {
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
        if ($this->httpService->config('fiber')) {
            $event = new EventStandard($request->getHash(), 'response', $response);
            Fiber::suspend($event);
        } else {
            return $response;
        }
    }


    /**
     * @param EventStandard $event
     * @return void
     */
    public function extracted(EventStandard $event): void
    {
        switch ($event->getName()) {
            case 'response':
                $response = $event->getData();
                $request  = $response->request;
                $client   = $request->clientSocket;
                $client->write($response->__toString());
                unset($this->fibers[$request->getHash()]);
                unset($this->arrayHashMap[$client->getKeyName()][$request->getHash()]);
                unset($this->requests[$request->getHash()]);
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
                        if ($task = $this->fibers[$hash] ?? null) {
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
