<?php
declare(strict_types=1);
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
use function str_contains;
use function str_starts_with;

/**
 *
 */
class Request
{
    public const INVALID    = -1;
    public const COMPLETE   = 2;
    public const INCOMPLETE = -1;
    public string      $path;
    public string      $method;                                 // Request the URI
    public string      $version;                                // Request method
    public string      $clientAddress;                          // Request a version
    public mixed       $socket;                                 // Client address
    public array       $header;                                 // Client sockets
    public array       $fileInfo;                               // Request header content
    public int         $bodyLength = 0;                         // File information
    public string      $body;                                   // Body length
    public array       $param      = array();                   // BODY
    public bool        $isUpload   = false;                     // VARIABLE
    public mixed       $fileStream;                             // Whether to upload
    public bool        $complete   = false;                     // File stream
    public string      $buffer     = '';                        // Whether it is complete
    public string      $name;                                   // BUFFER
    public bool        $isStatic;                               // Client name
    public string      $hash;                                   // Routing information
    public int         $statusCode;                             // A random hash of the current packet
    public Map|null    $route;                                  // Whether it is a static request
    public Statistics  $statistics;                             // STATISTICS
    public Response    $response;
    public SocketAisle $clientSocket;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->statistics = new Statistics();
        $this->name       = $name;
        $this->hash       = md5(mt_rand(1111, 9999) . microtime(true));
        $this->statusCode = Request::INCOMPLETE;
        $this->response   = new Response($this);
    }

    /**
     * @param string $name
     * @return self
     */
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
     * Gets the request method
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method ?? 'undefined';
    }

    /**
     * Set the request parameters
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
     * Set the request body length
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
     * Push request body
     *
     * @param string $context
     * @return self|false
     */
    public function push(string $context): self|false
    {
        if (!isset($this->method)) {
            $context = $this->buffer($context);
            if (str_contains($context, "\r\n\r\n")) {
                //TODO: valid request
                $_                = explode("\r\n\r\n", $context);
                $headerContext    = $_[0];
                $this->body       = $_[1];
                $this->bodyLength = strlen($this->body);
                $headerLines      = explode("\r\n", $headerContext);
                $base             = array_shift($headerLines);
                if (count($base = explode(' ', $base)) !== 3) {
                    //TODO:: method url and version not is valid
                    $this->signStatusCode(Request::INVALID);
                    return false;
                } else {
                    $this->setMethod($base[0]);
                    $this->setPath($base[1]);
                    $this->setVersion($base[2]);
                    foreach ($headerLines as $item) {
                        $lineParam                         = explode(':', $item);
                        $this->header[trim($lineParam[0])] = trim($lineParam[1]);
                    }
                    if ($this->method === 'GET') {
                        $this->signStatusCode(Request::COMPLETE);
                        return $this;
                    }
                }
            } else {
                $this->buffer($context);
            }
        } elseif ($this->method === 'POST') {
            $this->body .= $context;
            if (strlen($this->body) === $this->bodyLength) {
                $this->signStatusCode(Request::COMPLETE);
            }
        }
        return $this;
    }

    /**
     * Gets the cache context
     *
     * @param string $context
     * @return string
     */
    public function buffer(string $context): string
    {
        return $this->buffer .= $context;
    }

    /**
     * @param int $statusCode
     * @return $this
     */
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
        $key                = trim($key);
        $value              = trim($value);
        $this->header[$key] = $value;
        return $this;
    }

    /**
     * @return void
     */
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
     * Provide the file path and bind the file information
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
     * Get the header message
     *
     * @param string $key
     * @return string|null
     */
    public function header(string $key): string|null
    {
        return $this->header[$key] ?? null;
    }

    /**
     * Get file information
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
     * Get the GET parameters
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
     * Get the POST parameter
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

    /**
     * Serialize the package
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * Whether the package is complete
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Whether it is an Ajax request
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('HTTP_X_REQUESTED_WITH') === 'XMLHTTPREQUEST';
    }

    /**
     * Get the client address
     *
     * @return string|null
     */
    public function getClientAddress(): string|null
    {
        return $this->clientAddress;
    }

    /**
     * Set the client address
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
     * Gets the socket entity
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
     * Gets the client name
     *
     * @return string|null
     */
    public function getName(): string|null
    {
        return $this->name ?? null;
    }

    /**
     * Set the client name
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the request body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body ?? '';
    }

    /**
     * Gets the unique identity of the current request
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return bool
     */
    public function complete(): bool
    {
        return $this->statusCode === Request::COMPLETE;
    }

    /**
     * @return string|null
     */
    public function getPath(): string|null
    {
        return $this->path ?? null;
    }

    /**
     * Set the path
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
        if ($this->route = Route::guide($this->method, $this->path)) {
            if ($this->method === 'GET') {
                $this->statusCode = Request::COMPLETE;
            }
            return true;
        } elseif ($this->route = Route::guide('STATIC', $this->path)) {
            //TODO: match static route
            if ($index = strpos($this->path, '/', 1)) {
                $this->path = substr($this->path, $index);
            } else {
                $this->path = '';
            }
            $this->isStatic   = true;
            $this->statusCode = Request::COMPLETE;
            return true;
        } else {
            //TODO: not match route
            $this->statusCode = Request::COMPLETE;
        }
        return false;
    }

    /**
     * @return \Cclilshy\PRipple\Route\Map|null
     */
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

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $socket
     * @return void
     */
    public function setClientSocket(Client $socket): void
    {
        $this->clientSocket = $socket;
    }
}
