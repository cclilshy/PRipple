<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-25 12:24:44
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\FileSystem\File;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Communication\Aisle\FileAisle;


class Service
{
    public const STATE_CLOSE  = 'STATE_CLOSE';
    public const STATE_EXPECT = 'STATE_EXPECT';
    public const STATE_START  = 'STATE_START';

    public string $cacheFilePath;
    // long cache file

    public int $cacheCount = 0;
    // Total number of staging areas

    public string $name;
    // Staging area name

    public string $state = '';
    // State of affairs

    public FileAisle $cacheFile;
    // Long cache file

    public Client $socket;

    // standard socket

    /**
     * @param string $publish
     * @param Client $socket
     */
    public function __construct(string $publish, Client $socket)
    {
        $this->name          = $publish;
        $this->cacheFilePath = PRIPPLE_CACHE_PATH . '/server_cache_' . $publish . File::EXT;
        if (File::exists($this->cacheFilePath)) {
            $file = File::open($this->cacheFilePath, 'r+');
        } else {
            $file = File::create($this->cacheFilePath, 'r+');
        }
        $this->cacheFile = FileAisle::create($file);
        $this->socket    = $socket;
    }

    /**
     * Reconnect triggers
     *
     * @param Client $socket
     * @return void
     */
    public function handleServiceOnReconnect(Client $socket): void
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
     * Send data
     *
     * @param string $context
     * @param int    $type
     * @return void
     */
    public function sendWithInt(string $context, int $type): void
    {
        if ($this->state === self::STATE_START) {
            $this->socket->sendByAgree(Dispatcher::AGREE, 'sendWithInt', [$context, $type]);
        } else {
            $this->cache($context, $type);
        }
    }

    /**
     * Write caching
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
     * Get the service status
     *
     * @return string|null
     */
    public function getState(): string|null
    {
        return $this->state ?? null;
    }

    /**
     * Set the status
     *
     * @param string $state
     * @return void
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }

    /**
     * @return string[]
     */
    public function __sleep()
    {
        return ['cacheFilePath', 'name', 'cacheCount', 'state', 'socket'];
    }
}