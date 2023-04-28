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
use Cclilshy\PRipple\Built\Http\Text\Text;
use Cclilshy\PRipple\Dispatch\DataStandard\Event as EventStandard;


class Event
{
    public array   $fibers = array();
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
            $this->fibers[$request->getHash()] = $fiber = new Fiber(function () use ($request) {
                $this->executeController($request);
            });
            $event                             = $fiber->start();
            $this->parseEvent($event);
        } else {
            $event = $this->executeController($request);
            $client->write($event->getData()->__toString());
        }

    }

    // Execute user-defined controller methods that return an event.
    // And in Fiber mode, support for handling multiple events, otherwise support for handling Response events
    public function executeController(Request $request): EventStandard
    {
        if (!isset($request->route)) {
            $result   = (Text::htmlErrorPage(404, "Not match Route '{$request->path}'", __FILE__, __LINE__, $request, $request->statistics));
            $response = $request->response->setBody($result);
        } elseif ($request->isStatic()) {
            $response = $request->route->run($request);
        } else {
            switch ($request->route->type) {
                case 'Controller':
                    $className  = $request->route->className;
                    $controller = new $className($request);
                    $result     = call_user_func([$controller, $request->route->action], $request);
                    break;
                case 'Closure':
                    $request->route = Route::guide($request->method(), $request->path);
                    $result         = call_user_func($request->route->callable, $request);
                    break;
                default:
                    $result = '';
                    break;
            }
            $response = $request->response->setBody($result);
        }
        $event = new EventStandard($request->getHash(), 'response', $response);
        if ($this->httpService->config('fiber')) {
            Fiber::suspend($event);
        } else {
            return $event;
        }
    }


    /**
     * @param EventStandard $event
     * @return void
     */
    public function parseEvent(EventStandard $event): void
    {
        switch ($event->getName()) {
            case 'response':
                $response = $event->getData();
                $request  = $response->request;
                $client   = $request->clientSocket;
                $client->write($response->__toString());
                unset($this->fibers[$request->getHash()]);
                break;
            case 'sleep':
                $requestHash = $event->getPublisher();
                if (isset($this->fibers[$requestHash])) {
                    $data = $event->getData();
                    $this->httpService->publishEvent('ControllerSleep', [
                        'time' => $data['time'],
                        'hash' => $requestHash,
                    ]);
                }
                break;
            default:
                if ($event->getPublisher() === 'Timer') {
                    if ($event->getName() === 'ControllerSleep') {
                        if ($fiber = $this->fibers[$event->getData()['hash']] ?? null) {
                            $event = $fiber->resume();
                            $this->parseEvent($event);
                        }
                    }
                }
        }
    }
}
