<?php
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2023-03-02 00:17:47
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: CCPHP
 * Copyright (c) 2023 by user email: cclilshy, All Rights Reserved.
 */
declare(strict_types=1);

namespace Cclilshy\PRipple\FileSystem;

class Fifo
{
    const EXT = '.fifo';
    private mixed  $stream;
    private string $name;
    private string $path;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name   = $name;
        $this->path   = PRIPPLE_PIPE_PATH . '/fifo_' . $name . self::EXT;
        $this->stream = fopen($this->path, 'r+');
    }

    /**
     * @param string $name
     * @return Fifo|false
     */
    public static function create(string $name): Fifo|false
    {
        $path = PRIPPLE_PIPE_PATH . '/fifo_' . $name;
        if (file_exists($path . self::EXT)) {
            return false;
        } elseif (posix_mkfifo($path . self::EXT, 0666)) {
            return new self($name);
        } else {
            return false;
        }
    }


    /**
     * 创建管道
     *
     * @param string $name
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return file_exists(PRIPPLE_PIPE_PATH . '/fifo_' . $name . self::EXT);
    }


    /**
     * 连接管道
     *
     * @param string $name
     * @return Fifo|false
     */
    public static function link(string $name): Fifo|false
    {
        $path = PRIPPLE_PIPE_PATH . '/fifo_' . $name;
        if (!!file_exists($path . self::EXT)) {
            return new self($name);
        } else {
            return false;
        }
    }


    /**
     * 向管道写入数据
     *
     * @param string $context
     * @return int
     */
    public function write(string $context): int
    {
        return fwrite($this->stream, $context);
    }

    /**
     * 读取一行内容
     *
     * @return string
     */
    public function fgets(): string
    {
        return fgets($this->stream);
    }


    /**
     * 读指定长度内容
     *
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        return fread($this->stream, $length);
    }

    /**
     * 获取整个管道内容
     *
     * @return string
     */
    public function full(): string
    {
        return stream_get_contents($this->stream);
    }


    /**
     * 销毁管道
     *
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
     * 关闭管道
     *
     * @return void
     */
    public function close(): void
    {
        if (get_resource_type($this->stream) !== 'Unknown') {
            fclose($this->stream);
        }
    }

    /**
     * 设置堵塞模式
     *
     * @param bool $bool
     * @return bool
     */
    public function setBlocking(bool $bool): bool
    {
        return stream_set_blocking($this->stream, $bool);
    }


    /**
     * 获取当前管道名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

}
