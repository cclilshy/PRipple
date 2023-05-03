<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2022-12-08 15:37:42
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2022 by cclilshy email: cclilshy@163.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Route;

// Loading layer record all routing information configured by the system
// Guide for storing routes

use Cclilshy\PRipple\Statistics;
use Cclilshy\PRipple\Built\Http\Response;
use Cclilshy\PRipple\Built\Http\Text\Text;


class Map
{
    public string $type;
    public string $className;
    public string $action;
    public mixed  $callable;

    /**
     * Create a guide that supports class::static function class name -> method anonymous function
     *
     * @param string        $type
     * @param string        $className
     * @param string        $action
     * @param callable|null $callable
     */
    public function __construct(string $type, string $className, string $action, callable $callable = null)
    {
        $this->type = $type;
        switch ($this->type) {
            case 'Controller':
                $this->className = $className;
                $this->action    = $action;
                break;
            case 'Closure':
                $this->callable = $callable;
                break;
            case 'Static':
                $this->className = $className;
                break;
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->className, $name], $arguments);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * @return string[]
     */
    public function __sleep()
    {
        return ['type', 'className', 'action'];
    }

    /**
     * @return mixed
     */
    public function run(): mixed
    {
        switch ($this->type) {
            case 'Controller':
                return call_user_func_array([new $this->className($vars[0] ?? null), $this->action], func_get_args());
            case 'Static':
                $request    = func_get_args()[0];
                $response   = new Response($request);
                $statistics = new Statistics();
                $filePath   = $this->className . $request->path;
                if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
                    return $response->setContentType(mime_content_type($filePath))->setBody(file_get_contents($filePath))->setStatusCode(200);
                } else {
                    return $response->setBody(Text::htmlErrorPage(404, 'There is no such static file!', __FILE__, __LINE__, $request, $statistics))->setStatusCode(404);
                }
            default:
                return call_user_func_array($this->callable, func_get_args());
        }
    }

    /**
     * 将控制器路由取出一个实例
     *
     * @param mixed|null $initParam
     * @return object|false
     */
    public function instantiation(mixed $initParam = null): object|false
    {
        if ($this->type !== 'Controller' || !isset($this->className)) {
            return false;
        }
        return new $this->className($initParam);
    }
}
