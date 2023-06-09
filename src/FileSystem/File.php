<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple\FileSystem;


class File
{
    public const EXT = '.tmp';
    // File suffix

    private mixed $file;
    // File entity

    private int $point = 0;

    // Pointer position

    /**
     * @param string $path
     * @param string $mode
     */
    public function __construct(string $path, string $mode)
    {
        $this->file = fopen($path, $mode);
    }

    /**
     * Create a file
     *
     * @param string $path File path
     * @param string $mode Open mode
     * @return self|false
     */
    public static function create(string $path, string $mode): self|false
    {
        if (self::exists($path)) {
            return false;
        } else {
            touch($path, 0666);
            return new self($path, $mode);
        }
    }

    /**
     * Whether the file exists
     *
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Open the file
     *
     * @param string $path
     * @param string $mode
     * @return false|static
     */
    public static function open(string $path, string $mode): self|false
    {
        if (self::exists($path)) {
            return new self($path, $mode);
        } else {
            return false;
        }
    }

    /**
     * Read and follow the pointer
     *
     * @param int $length
     * @return string|false
     */
    public function readWithTrace(int $length): string|false
    {
        $content = $this->read($this->point, $length);
        $this->adjustPoint($this->point + $length);
        return $content;
    }

    /**
     * Reads content of the specified length starting at the specified position
     *
     * @param int $start
     * @param int $length
     * @return string|false
     */
    public function read(int $start, int $length): string|false
    {
        $this->adjustPoint($start);
        return fread($this->file, $length);
    }

    /**
     * Adjusts the pointer to the specified position
     *
     * @param int      $location
     * @param int|null $whence
     * @return int
     */
    public function adjustPoint(int $location, int|null $whence = SEEK_SET): int
    {
        $this->point = $location;
        return fseek($this->file, $location, $whence);
    }

    /**
     * Contents of the photocopying specification
     *
     * @param string $context Specification details
     * @return int|false
     */
    public function write(string $context): int|false
    {
        return fwrite($this->file, $context);
    }

    /**
     * Empty the file
     *
     * @return bool
     */
    public function flush(): bool
    {
        $this->adjustPoint(0);
        return ftruncate($this->file, 0);
    }

    /**
     * Gets the file pointer
     *
     * @return int|bool
     */
    public function getPoint(): int|bool
    {
        return ftell($this->file);
    }
}