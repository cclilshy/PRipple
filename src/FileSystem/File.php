<?php

namespace Cclilshy\PRipple\FileSystem;

class File
{
    const EXT = '.tmp';
    // 文件后缀

    private mixed $file;
    // 文件实体

    private int $point = 0;

    // 指针位置

    public function __construct(string $path, string $mode)
    {
        $this->file = fopen($path, $mode);
    }

    /**
     * 创建文件
     *
     * @param string $path 文件路径
     * @param string $mode 打开模式
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
     * 文件是否存在
     *
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * 打开文件
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
     * 读并跟随指针
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
     * 从指定位置开始读指定长度内容
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
     * 调整指针到指定位置
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
     * 写入指定内容
     *
     * @param string $context 指定内容
     * @return int|false
     */
    public function write(string $context): int|false
    {
        return fwrite($this->file, $context);
    }

    /**
     * 清空文件
     * @return bool
     */
    public function flush(): bool
    {
        $this->adjustPoint(0);
        return ftruncate($this->file, 0);
    }

    /**
     * 获取文件指针
     * @return int|bool
     */
    public function getPoint(): int|bool
    {
        return ftell($this->file);
    }
}