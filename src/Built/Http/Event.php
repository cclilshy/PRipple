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

class Event
{
    public array   $accessList;
    public array   $requests;
    public Service $httpService;

    public function __construct(Service $httpService)
    {
        $this->httpService = $httpService;
    }

    public function access(Request $request): void
    {
        $this->requests[$request->getHash()]   = $request;
        $this->accessList[$request->getHash()] = new Fiber(function () use ($request) {
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
            $event = new \Cclilshy\PRipple\Dispatch\DataStandard\Event('http_request_' . $request->hash, 'response', $response);
            Fiber::suspend($event);
        });
        $event                                 = $this->accessList[$request->getHash()]->start();
        $this->extracted($event, $request);
    }


    /**
     * @param                                           $event
     * @param \Cclilshy\PRipple\Built\Http\Request|null $request
     * @return void
     */
    public function extracted($event, Request|null $request = null): void
    {
        switch ($event->getName()) {
            case 'response':
                $request->clientSocket->write($event->getData()->__toString());
                unset($this->accessList[$request->getHash()]);
                unset($this->requests[$request->getHash()]);
                break;
            case 'sleep':
                $data = $event->getData();
                $name = 'sleep:' . $request->getHash();
                $this->httpService->subscribe('Timer', $name, Dispatcher::FORMAT_EVENT, ['oneOff' => true]);
                $this->httpService->publishEvent('sleep', [
                    'time' => $data['time'],
                    'name' => $name
                ]);
                break;
            default:
                if ($event->getPublisher() === 'Timer') {
                    $name = $event->getName();
                    list($type, $hash) = explode(':', $name);
                    if ($type === 'sleep') {
                        if ($requestFiber = $this->accessList[$hash] ?? null) {
                            $event = $requestFiber->resume();
                            $this->extracted($event, $this->requests[$hash]);
                        }
                    }
                }
        }
    }
}
