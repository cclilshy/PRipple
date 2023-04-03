<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 13:18:59
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

use Fiber;
use Cclilshy\PRipple\Route\Map;
use Cclilshy\PRipple\Statistics;
use Cclilshy\PRipple\Route\Route;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;

class Request
{
    const INVALID    = -1;
    const COMPLETE   = 2;
    const INCOMPLETE = -1;
    public string      $path;
    public string      $method;                                 // 请求URI
    public string      $version;                                // 请求方法
    public string      $clientAddress;                          // 请求版本
    public mixed       $socket;                                 // 客户端地址
    public array       $header;                                 // 客户端套接字
    public array       $fileInfo;                               // 请求头内容
    public int         $bodyLength = 0;                         // 文件信息
    public string      $body;                                   // 主体长度
    public array       $param      = array();                   // 主体
    public bool        $isUpload   = false;                     // 变量
    public mixed       $fileStream;                             // 是否上传
    public bool        $complete   = false;                     // 文件流
    public string      $buffer     = '';                        // 是否完整
    public string      $name;                                   // 缓冲区
    public bool        $isStatic;                               // 客户端名称
    public string      $hash;                                   // 路由信息
    public int         $statusCode;                             // 当前包的随机哈希
    public Map         $route;                                  // 是否为静态请求
    public Statistics  $statistics;                             // 统计
    public Response    $response;
    public SocketAisle $clientSocket;

    public function __construct(string $name)
    {
        $this->statistics = new Statistics();
        $this->name       = $name;
        $this->hash       = md5(mt_rand(1111, 9999) . microtime(true));
        $this->statusCode = Request::INCOMPLETE;
        $this->response   = new Response($this);
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * @return bool
     */
    public function isStatic(): bool
    {
        return $this->isStatic ?? false;
    }

    /**
     * 获取请求方法
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method ?? 'undefined';
    }

    /**
     * 设置请求参数
     *
     * @param string $type
     * @param string $value
     * @return $this
     */
    public function setParam(string $type, string $value): self
    {
        $this->param[$type] = $value;
        return $this;
    }

    /**
     * 设置请求主体长度
     *
     * @param int $length
     * @return $this
     */
    public function setBodyLength(int $length): self
    {
        $this->bodyLength = $length;
        return $this;
    }

    /**
     * 推送请求主体
     *
     * @param string $context
     * @return self|false
     */
    public function push(string $context): self|false
    {
        return $this->pushBody($context);
    }

    public function pushBody(string $context): self|false
    {
        // 写入
        if (!isset($this->method)) {    // 没有方法
            $context = $this->buffer($context);
            // 解析HTTP报文过程
            if (str_contains($context, "\r\n\r\n")) {
                $_                = explode("\r\n\r\n", $context);
                $headerContext    = $_[0];
                $bodyContext      = $_[1];
                $this->body       = $bodyContext;
                $this->bodyLength = strlen($bodyContext);
                if ($headerLines = explode("\r\n", $headerContext)) {
                    $base = array_shift($headerLines);
                    if (count($base = explode(' ', $base)) !== 3) {
                        // 请求头非法
                        $this->signStatusCode(Request::INVALID);
                        return false;
                    } else {
                        $this->setMethod(strtoupper($base[0]));
                        $this->setPath($base[1]);
                        $this->setVersion($base[2]);
                        foreach ($headerLines as $item) {
                            $_ = explode(':', $item);
                            $this->setHeader($_[0], $_[1] ?? '');
                        }
                        if (isset($this->header['CONTENT-TYPE']) && str_starts_with(strtoupper($this->header['CONTENT-TYPE']), 'MULTIPART/FORM-DATA')) {
                            $this->parseUploadInfo();
                        }
                    }
                } else {
                    $this->signStatusCode(Request::INVALID);
                    return false;
                }
            }
        } elseif ($this->method === 'POST' && $this->bodyLength < intval($this->header('CONTENT-LENGTH'))) {    // POST处理
            if ($this->isUpload) {
                fwrite($this->fileStream, $context);
            } else {
                $this->body .= $context;
            }
            $this->bodyLength += strlen($context);
            return $this;
        } elseif ($this->isUpload) {
            return $this;
        } elseif (isset($this->method) && intval($this->header('CONTENT-LENGTH')) === $this->bodyLength) {
            $this->statusCode = Request::COMPLETE;
            parse_str($this->body, $this->param['POST']);
        }
        return $this;
    }

    /**
     * 获取缓存上下文
     *
     * @param string $context
     * @return string
     */
    public function buffer(string $context): string
    {
        return $this->buffer .= $context;
    }

    public function signStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * 设置方法
     *
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置版本
     *
     * @param string $version
     * @return $this
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * 设置请求头
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setHeader(string $key, string $value): self
    {
        $key   = trim(strtoupper($key));
        $value = trim($value);
        $this->header[$key] = $value;
        return $this;
    }

    public function parseUploadInfo(): void
    {
        $this->isUpload = true;
        do {
            $path = PRIPPLE_CACHE_PATH . '/' . md5(microtime(true) . rand(1, 9));
        } while (file_exists($path));
        $this->fileStream = fopen($path, 'a+');
        $this->setFile($path);
        fwrite($this->fileStream, $this->body);
        $this->bodyLength = strlen($this->body);
        $this->body       = '';
    }

    /**
     * 提供文件路径,绑定文件信息
     *
     * @param string $path
     * @return $this
     */
    public function setFile(string $path): self
    {
        if ($this->isUpload) {
            $this->fileInfo = pathinfo($path);
        }
        return $this;
    }

    /**
     * 获取头部消息
     *
     * @param string $key
     * @return string|null
     */
    public function header(string $key): string|null
    {
        return $this->header[strtoupper($key)] ?? null;
    }

    /**
     * 获取文件信息
     *
     * @return array|false
     */
    public function getFileInfo(): array|false
    {
        if (!$this->isUpload) {
            return false;
        }
        return $this->fileInfo;
    }

    /**
     * 获取GET参数
     *
     * @param string|null $key
     * @return mixed
     */
    public function get(string|null $key = null): mixed
    {
        if (!isset($key)) {
            return $this->param['GET'] ?? [];
        }
        return $this->param['GET'][$key] ?? null;
    }

    /**
     * 获取POST参数
     *
     * @param string|null $key
     * @return mixed
     */
    public function post(string|null $key = null): mixed
    {
        if (!isset($key)) {
            return $this->param['POST'] ?? [];
        }
        return $this->param['POST'][$key] ?? null;
    }

    public function build(): Request
    {
        return new Request((string)$this);
    }

    /**
     * 将包序列化
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * 包是否完整
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 是否为Ajax请求
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtoupper($this->header('HTTP_X_REQUESTED_WITH') ?? '') === 'XMLHTTPREQUEST';
    }

    /**
     * 获取客户端地址
     *
     * @return string|null
     */
    public function getClientAddress(): string|null
    {
        return $this->clientAddress;
    }

    /**
     * 设置客户端地址
     *
     * @param string $address
     * @return $this
     */
    public function setClientAddress(string $address): self
    {
        $this->clientAddress = $address;
        return $this;
    }

    /**
     * 获取套接字实体
     *
     * @return mixed
     */
    public function getSocket(): mixed
    {
        return $this->socket;
    }

    /**
     * @param mixed $socket
     * @return $this
     */
    public function setSocket(mixed $socket): self
    {
        $this->socket = $socket;
        return $this;
    }

    /**
     * 获取客户端名称
     *
     * @return string|null
     */
    public function getName(): string|null
    {
        return $this->name ?? null;
    }

    /**
     * 设定客户端名称
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * 获取请求主体
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body ?? '';
    }

    /**
     * 获取当前请求唯一标识
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    public function complete(): bool
    {
        return $this->statusCode === Request::COMPLETE;
    }

    public function getPath(): string|null
    {
        return $this->path ?? null;
    }

    /**
     * 设置路径
     *
     * @param string $path
     * @return bool
     */
    public function setPath(string $path): bool
    {
        // 设置路径时对路由配对
        $urlInfo = parse_url($path);
        if (isset($urlInfo['query'])) {
            parse_str($urlInfo['query'], $getParam);
            $this->param['GET'] = $getParam;
        } else {
            $this->param['GET'] = array();
        }
        $this->path = $urlInfo['path'];
        if ($route = Route::guide($this->method, $this->path)) {
            $this->route = $route;
            if ($this->method === 'GET') {
                $this->statusCode = Request::COMPLETE;
            }
            return true;
        } elseif ($route = Route::guide('STATIC', $this->path)) {
            if ($index = strpos($this->path, '/', 1)) {
                $this->path = substr($this->path, $index);
            } else {
                $this->path = '';
            }
            $this->route      = $route;
            $this->isStatic   = true;
            $this->statusCode = Request::COMPLETE;
            return true;
        }
        return false;
    }

    public function getRoute(): Map|null
    {
        return $this->route ?? null;
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return [
            'path',
            'method',
            'version',
            'clientAddress',
            'header',
            'fileInfo',
            'bodyLength',
            'body',
            'param',
            'isUpload',
            'complete',
            'name',
            'route',
            'hash',
            'clientSocket'
        ];
    }

    public function setClientSocket(Client $socket): void
    {
        $this->clientSocket = $socket;
    }
}
