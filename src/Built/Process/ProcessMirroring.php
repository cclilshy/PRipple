<?php
declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2023-03-02 21:01:04
 * @LastEditors: cclilshy cclilshy@163.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: cclilshy, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

// Process mirroring, used to store user-defined call stack sequences, which can reversely load the call stack


/**
 *
 */
class ProcessMirroring
{
    public mixed  $func;
    public array  $flow = [];
    public object $space;

    /**
     * @param callable    $func
     * @param object|null $space
     */
    public function __construct(callable $func, object $space = null)
    {
        $this->func = $func;
        if ($space !== null) {
            $this->space = $space;
        }
    }

    /**
     * 由指定程序反序列化处理栈序请求
     *
     * @param object $main
     * @param        $flow
     * @return mixed|object
     */
    public static function production(object $main, $flow = null): mixed
    {
        foreach ($flow as $k => $item) {
            $main = call_user_func_array([$main, $item['m']], $item['a']);
        }
        return $main;
    }

    /****/
    /**
     * @param $name
     * @return mixed
     */
    public function __get($name): mixed
    {
        return $this->$name;
    }

    /**
     * 接受任意方法，并将参数入栈
     *
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments): ProcessMirroring
    {
        $this->flow[] = ['m' => $name, 'a' => $arguments];
        return $this;
    }

    /**
     * @return mixed
     */
    public function go(): mixed
    {
        $result     = call_user_func($this->func, $this);
        $this->flow = [];
        return $result;
    }
}