<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 22:33:59
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: CCPHP
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Aisle;

use Cclilshy\PRipple\File\File;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

class FileAisle implements CommunicationInterface
{
    const EXT = '.aisle';

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
     * @param        $handledLength
     * @return bool
     */
    public function write(string $context, &$handledLength): bool
    {
        return $this->file->write($context) > 0;
    }

    public function release(): bool
    {
        //        $this->file->release();
        return false;
    }


    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool
    {
        //        $this->file->close();
        return false;
    }


    public function adjustPoint(int $location): int
    {
        return $this->file->adjustPoint($location);
    }

    public function flush()
    {
        $this->file->flush();
    }
}
