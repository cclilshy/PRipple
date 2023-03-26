<?php

namespace Cclilshy\PRipple;
class Console
{
    public static function debug(): void
    {
        $arguments = func_get_args();
        $text      = '';
        while ($argument = array_shift($arguments)) {
            if (is_callable($argument)) {
                $_ = 'function';
            } elseif (is_resource($argument)) {
                $_ = 'resource';
            } elseif (is_object($argument) || is_array($argument)) {
                $_ = json_encode($argument);
            } elseif (is_string($argument)) {
                $_ = $argument;
            } else {
                $_ = gettype($argument);
            }
            $text .= $_ . '|';
        }
        print('[' . microtime(true) . '] ' . $text . PHP_EOL);
    }
}