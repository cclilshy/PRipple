<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-25 12:24:44
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\File\File;
use Cclilshy\PRipple\Communication\Aisle\FileAisle;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

class Service
{
    const STATE_CLOSE  = 'STATE_CLOSE';
    const STATE_EXPECT = 'STATE_EXPECT';
    const STATE_START  = 'STATE_START';

    private string $cacheFilePath;
    // 长缓存文件

    private int $cacheCount = 0;
    // 暂存区总数

    private string $name;
    // 暂存区名称

    private string $state = '';
    // 状态

    private FileAisle $cacheFile;
    // 长缓存文件

    private CommunicationInterface $socket;

    // 标准套接字

    public function __construct(string $publish, CommunicationInterface $socket)
    {
        $this->name          = $publish;
        $this->cacheFilePath = CACHE_PATH . '/server_cache_' . $publish . File::EXT;
        if (File::exists($this->cacheFilePath)) {
            $file = File::open($this->cacheFilePath, 'r+');
        } else {
            $file = File::create($this->cacheFilePath, 'r+');
        }
        $this->cacheFile = FileAisle::create($file);
        $this->socket    = $socket;
    }

    /**
     * 重新连接触发
     *
     * @param \Cclilshy\PRipple\Communication\Standard\CommunicationInterface $socket
     * @return void
     */
    public function handleServiceOnReconnect(CommunicationInterface $socket): void
    {
        $this->setState(self::STATE_START);
        $this->cacheFile->adjustPoint(0);
        $this->socket = $socket;
        $cacheCount   = $this->cacheCount;
        for ($i = 0; $i < $cacheCount; $i++) {
            $context = Dispatcher::AGREE::cutWithInt($this->cacheFile, $int);
            $this->cacheCount--;
            $this->sendWithInt($context, $int);
        }
        $this->cacheFile->flush();
    }

    /**
     * 发送数据
     *
     * @param string $package
     * @param int    $type
     * @return void
     */
    public function sendWithInt(string $package, int $type): void
    {
        if ($this->state === self::STATE_START) {
            Dispatcher::AGREE::sendWithInt($this->socket, $package, $type);
        } else {
            $this->cache($package, $type);
        }
    }

    /**
     * 写入缓存
     *
     * @param string $package
     * @param int    $type
     * @return bool
     */
    public function cache(string $package, int $type): bool
    {
        $this->cacheCount++;
        if (Dispatcher::AGREE::sendWithInt($this->cacheFile, $package, $type)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取服务状态
     *
     * @return string|null
     */
    public function getState(): string|null
    {
        return $this->state ?? null;
    }

    /**
     * 设置状态
     *
     * @param string $state
     * @return void
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function __sleep()
    {
        return ['cacheFilePath', 'name'];
    }
}