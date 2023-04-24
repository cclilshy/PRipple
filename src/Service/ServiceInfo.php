<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Service;

use Cclilshy\PRipple\Config;
use Cclilshy\PRipple\FileSystem\Pipe;

/**
 *
 */
class ServiceInfo
{
    public string     $name;
    public bool       $status;
    public int        $pid;
    public Pipe|false $pipe;
    public array      $data;
    public array      $config;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name   = $name;
        $this->config = Config::get($name) ?? [];
    }

    /**
     * @param string|null $name
     * @return false|self
     */
    public static function load(string|null $name = ''): self|false
    {
        if (!$name) {
            $name = str_replace('/', '_', debug_backtrace()[0]['file']);
        }
        $server = new self($name);
        return $server->initLoad();
    }

    /**
     * @return false|$this
     */
    public function initLoad(): self|false
    {
        if ($this->pipe = Pipe::link($this->name)) {
            if ($server = $this->pipe->read()) {
                if ($server = unserialize($server)) {
                    $this->status = $server->status;
                    $this->pid    = $server->pid;
                    $this->data   = $server->data;
                    return $this;
                } else {
                    $this->pipe->release();
                    return false;
                }
            } else {
                $this->pipe->release();
                return false;
            }
        }
        return false;
    }

    /**
     * @return void
     */
    public function release(): void
    {
        $this->pipe->release();
    }


    /**
     * @param string|null $name
     * @return false|self
     */
    public static function create(string|null $name): self|false
    {
        if (!$name) {
            $name = str_replace('/', '_', debug_backtrace()[0]['file']);
        }
        $server = new self($name);
        return $server->initCreate();
    }

    /**
     * @return false|$this|self
     */
    public function initCreate(): self|false
    {
        if ($this->pipe = Pipe::create($this->name)) {
            $this->status = false;
            $this->pid    = posix_getpid();
            $this->data   = [];
            $this->record();
            return $this;
        }
        return false;
    }

    // 设置与保存信息

    /**
     * @return void
     */
    public function record(): void
    {
        $this->pipe->write(serialize($this));
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return ['name', 'status', 'pid', 'data'];
    }

    /**
     * @param $data
     * @return array|bool
     */
    public function info($data = null): array|bool
    {
        if ($data === null) {
            return $this->data;
        }
        $this->data = $data;
        $this->record();
        return true;
    }

    /**
     * @return bool
     */
    public function setLock(): bool
    {
        return $this->pipe->lock();
    }

    /**
     * @return bool
     */
    public function unLock(): bool
    {
        return $this->pipe->unlock();
    }

    /**
     * @param $name
     * @return mixed
     */
    public function config($name): mixed
    {
        return $this->$name;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name): mixed
    {
        return $this->config[$name] ?? null;
    }
}
