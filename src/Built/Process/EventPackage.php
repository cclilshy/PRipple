<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 20:05:29
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\PRipple\Communication\Standard\PackageInterface;

/**
 *
 */
class EventPackage implements PackageInterface
{
    private string     $action;
    private array|null $arguments = [];

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
    }

    /**
     * @param string $name
     * @return \Cclilshy\PRipple\Built\Process\EventPackage
     */
    public static function create(string $name): EventPackage
    {
        return new self($name);
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return 2;
    }

    /**
     * @return bool
     */
    public function complete(): bool
    {
        return true;
    }

    /**
     * @param string $string
     * @return $this
     */
    public function push(string $string): self
    {
        return $this;
    }

    /**
     * @param string $actionName
     * @return $this
     */
    public function setActionName(string $actionName): self
    {
        $this->action = $actionName;
        return $this;
    }

    /**
     * @param string $actionName
     * @return string|null
     */
    public function getActionName(string $actionName): string|null
    {
        return $this->action ?? '';
    }

    /**
     * @param array|null $arguments
     * @return array|null
     */
    public function getArguments(array|null $arguments = null): array|null
    {
        return $this->arguments;
    }

    /**
     * @param array|null $arguments
     * @return $this
     */
    public function setArguments(array|null $arguments = null): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }
}
