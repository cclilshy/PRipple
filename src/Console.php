<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Route\Route;


class Console
{
    public const RESERVED = ['help', 'test', 'list', 'run'];
    private static array $commands = [];
    private static array $argv;

    /**
     * @return \Cclilshy\PRipple\Console
     */
    public static function init(): Console
    {
        $list = Route::getConsoles();
        foreach ($list as $key => $item) {
            $describe       = call_user_func([$item, 'register']);
            self::$commands = array_merge(self::$commands, [$key => $describe]);
        }
        return new self();
    }

    /**
     * @return array
     */
    public static function argv(): array
    {
        return self::$argv;
    }

    /**
     * @param mixed ...$args
     * @return void
     */
    public static function pdebug(...$args): void
    {
        if (PRipple::config('debug') === true) {
            self::extracted($args);
        }
    }

    /**
     * @param $args
     * @return void
     */
    public static function extracted($args): void
    {
        $content = '';
        foreach ($args as $index => $arg) {
            if (is_array($arg) || is_object($arg)) {
                $content .= json_encode($arg, JSON_UNESCAPED_UNICODE);
            } else {
                $content .= $arg;
            }
            if ($index !== count($args) - 1) {
                $content .= ',';
            }
        }

        $_micrometer = explode(' ', microtime());
        $date        = date("H:i:s", intval($_micrometer[1]));
        $date        .= substr($_micrometer[0], 1);
        self::printn("\033[33m[" . posix_getpid() . '][' . $date . "]{$content}\033[0m");
    }

    /**
     * @param string $content
     * @return void
     */
    public static function printn(string $content): void
    {
        printf($content . PHP_EOL);
    }

    /**
     * @param mixed ...$args
     * @return void
     */
    public static function debug(...$args): void
    {
        self::extracted($args);
    }

    /**
     * @param string $content
     * @return void
     */
    public static function pred(string $content): void
    {
        self::printn("\033[31m[" . posix_getpid() . "]{$content}\033[0m");
    }

    /**
     * @return void
     */
    public function run(): void
    {
        global $argc;
        global $argv;
        $option     = $argv[1] ?? 'help';
        $map        = Route::guide('console', $option);
        self::$argv = $argv;
        if ($map !== null) {
            array_shift($argv);
            $map->run($argv, $this);
        } elseif ($option === 'help' || $option === 'list') {
            self::printn("\033[32mPRipple is successfully initialized. Procedure \033[0m");
            self::brief('list', 'ApplicationList');
            self::brief('test', 'Server environment self-test');
            self::brief('help', 'Help');
            foreach (self::$commands as $key => $item)
                self::brief($key, $item);
        } elseif ($option === 'test') {
            Configure::inspection();
        }
    }

    /**
     * @param string $title
     * @param string $content
     * @return void
     */
    public function brief(string $title, string $content): void
    {
        $maxLength      = $this->getMaxCommandLength();
        $formattedTitle = str_pad($title, $maxLength);
        self::printn("\t\033[34m{$formattedTitle}\t\033[0m \t\t\033[37m {$content} \033[0m");
    }

    /**
     * @return int
     */
    private function getMaxCommandLength(): int
    {
        $maxLength = 0;
        foreach (self::$commands as $key => $item) {
            $length = strlen($key);
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }
        return $maxLength;
    }
}
