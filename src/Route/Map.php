<?php
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
// 用于储存路由的导向

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
     * 创建一个导向，支持类::静态函数/类名->方法/匿名函数
     *
     * @param string        $type
     * @param string        $className
     * @param string        $action
     * @param callable|null $callable
     */
    public function __construct(string $type, string $className, string $action, callable $callable = null)
    {
        $this->type = $type;
        if ($type === 'Controller') {
            $this->className = $className;
            $this->action    = $action;
        } elseif ($type === 'Closure') {
            $this->callable = $callable;
        } elseif ($type === 'Static') {
            $this->className = $className;
        }
    }

    /**
     * @param ...$vars
     * @return mixed
     */
    public function run(...$vars): mixed
    {
        if ($this->type == 'Controller') {
            return call_user_func([new $this->className(...$vars), $this->action], ...$vars);
        } elseif ($this->type === 'Static') {
            $request    = $vars[0];
            $response   = new Response($request);
            $statistics = new Statistics();
            $filePath   = $this->className . $request->path;
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
                return $response->setContentType(mime_content_type($filePath))->setBody(file_get_contents($filePath))->setStatusCode(200);
            } else {
                return $response->setBody(Text::htmlErrorPage(404, 'There is no such static file!', __FILE__, __LINE__, $request, $statistics))->setStatusCode(404);
            }
        } else {
            return call_user_func_array($this->callable, ...$vars);
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
}
