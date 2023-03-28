<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 22:33:59
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: CCPHP
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Aisle;

use Cclilshy\PRipple\FileSystem\File;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

class FileAisle implements CommunicationInterface
{
    const EXT = '.pipe';

    private File $file;

    public function __construct(mixed $file)
    {
        $this->file = $file;
    }

    /**
     * 创建连接
     *
     * @param mixed $base
     * @return false|static
     */
    public static function create(mixed $base): self|false
    {
        return new self($base);
    }

    // 获取连接相关信息

    /**
     * 不可以直接连接
     *
     * @param string $name
     * @return false|static
     */
    public static function link(string $name): self|false
    {
        return false;
    }


    /**
     * 读取数据
     *
     * @param null     $data
     * @param int|null $length
     * @return bool
     */
    public function read(mixed &$data, int|null $length = null): bool
    {
        $data = $this->file->readWithTrace($length);
        return strlen($data) > 0;
    }


    /**
     * 写入数据
     *
     * @param string $context
     * @return int|bool
     */
    public function write(string $context): int|bool
    {
        return $this->file->write($context);
    }

    /**
     * 释放连接
     *
     * @return bool
     */
    public function release(): bool
    {
        return false;
    }


    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool
    {
        return false;
    }


    /**
     * 移动指针
     *
     * @param int      $location
     * @param int|null $whence
     * @return int
     */
    public function adjustPoint(int $location, int|null $whence = SEEK_SET): int
    {
        return $this->file->adjustPoint($location, $whence);
    }

    /**
     * 清空文件
     *
     * @return void
     */
    public function flush(): void
    {
        $this->file->flush();
    }

    /**
     * 获取指针位置
     *
     * @return int|bool
     */
    public function getPoint(): int|bool
    {
        return $this->file->getPoint();
    }
}
