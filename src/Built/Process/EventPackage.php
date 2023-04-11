<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 20:05:29
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\PRipple\Communication\Standard\PackageInterface;

class EventPackage implements PackageInterface
{
    private string     $action;
    private array|null $arguments = [];

    public function __construct(string $name)
    {
    }

    public static function create(string $name): EventPackage
    {
        return new self($name);
    }

    public function getStatusCode(): int
    {
        return 2;
    }

    public function complete(): bool
    {
        return true;
    }

    public function push(string $string): self
    {
        return $this;
    }

    public function setActionName(string $actionName): self
    {
        $this->action = $actionName;
        return $this;
    }

    public function getActionName(string $actionName): string|null
    {
        return $this->action ?? '';
    }

    public function getArguments(array|null $arguments = null): array|null
    {
        return $this->arguments;
    }

    public function setArguments(array|null $arguments = null): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function serialize(): string
    {
        return serialize($this);
    }
}
