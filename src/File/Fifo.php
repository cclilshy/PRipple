<?php
declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2023-03-02 00:17:47
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: CCPHP
 * Copyright (c) 2023 by user email: cclilshy, All Rights Reserved.
 */

namespace Cclilshy\PRipple\File;


class Fifo
{
    private mixed  $stream;
    private string $name;
    private string $path;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name   = $name;
        $this->path   = CACHE_PATH . '/pipe/fifo_' . $name . '.fifo';
        $this->stream = fopen($this->path, 'r+');
    }

    /**
     * @param string $name
     * @return Fifo|false
     */
    public static function create(string $name): Fifo|false
    {
        $path = CACHE_PATH . '/pipe/fifo_' . $name;
        if (file_exists($path . '.fifo')) {
            return false;
        } elseif (posix_mkfifo($path . '.fifo', 0666)) {
            return new self($name);
        } else {
            return false;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return file_exists(CACHE_PATH . '/pipe/fifo_' . $name . '.fifo');
    }

    /**
     * @param string $name
     * @return Fifo|false
     */
    public static function link(string $name): Fifo|false
    {
        $path = CACHE_PATH . '/pipe/fifo_' . $name;
        if (!!file_exists($path . '.fifo')) {
            return new self($name);
        } else {
            return false;
        }
    }

    /**
     * @param string $context
     * @return int
     */
    public function write(string $context): int
    {
        return fwrite($this->stream, $context);
    }

    /**
     * @return string
     */
    public function fgets(): string
    {
        return fgets($this->stream);
    }

    /**
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        return fread($this->stream, $length);
    }

    /**
     * @return string
     */
    public function full(): string
    {
        return stream_get_contents($this->stream);
    }

    /**
     * @return void
     */
    public function release(): void
    {
        $this->close();
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if (get_resource_type($this->stream) !== 'Unknown') {
            fclose($this->stream);
        }
    }

    /**
     * @param bool $bool
     * @return bool
     */
    public function setBlocking(bool $bool): bool
    {
        return stream_set_blocking($this->stream, $bool);
    }
}
