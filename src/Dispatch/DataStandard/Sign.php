<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple\Dispatch\DataStandard;


/**
 * In the message package,
 * some attributes of the message package
 * that create a signature release are only allowed to be defined at the time of creation,
 * such as: the publisher,
 * the message at the time of publishing, and the event at the time of publishing. Therefore,
 * the subsequent processors that get the package are allowed to sign the package to store data (example a native object)
 */
class Sign
{
    // signer
    public string $name;
    // signature specified data
    public mixed $info;
    // The counter of the signature
    // (the same package is signed twice by a person object only records the counter and does not overwrite the data)

    // I don't know what's the use
    public int $count;

    /**
     * @param string $name
     * @param mixed  $info
     */
    public function __construct(string $name, mixed $info)
    {
        $this->name  = $name;
        $this->info  = $info;
        $this->count = 0;
    }

    /**
     * @param string $name @ signer
     * @param mixed  $info @ saveData
     * @return static
     */
    public static function sign(string $name, mixed $info): self
    {
        return new self($name, $info);
    }

    /**
     * Count +1 to the original signature object
     *
     * @return void
     */
    public function counter(): void
    {
        $this->count++;
    }

}