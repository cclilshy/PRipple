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
    public const INCOMPLETE = 1;
    public string      $path;
    public string      $method;                                 // Request the URI
    public string      $version;                                // Request method
    public string      $clientAddress;                          // Request a version
    public mixed       $socket;                                 // Client address
    public array       $header;                                 // Client sockets
    public array       $fileInfo;                               // Request header content
    public array       $uploadBaseInfo;                         // Upload Base Info
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
            if ($headerEnd = strpos($context, "\r\n\r\n")) {
                //TODO: valid request
                $headerContext = substr($context, 0, $headerEnd);
                $this->body    = substr($context, $headerEnd + 4);
                $headerLines   = explode("\r\n", $headerContext);
                $base          = array_shift($headerLines);
                if (count($base = explode(' ', $base)) !== 3) {
                    //TODO:: method url and version not is valid
                    $this->signStatusCode(Request::INVALID);
                    return false;
                } else {
                    $this->setMethod($base[0]);
                    $this->setPath($base[1]);
                    $this->setVersion($base[2]);
                    $this->bodyLength = strlen($this->body);
                    foreach ($headerLines as $item) {
                        $lineParam                         = explode(':', $item);
                        $this->header[trim($lineParam[0])] = trim($lineParam[1]);
                    }
                    if ($this->method === 'GET') {
                        $this->signStatusCode(Request::COMPLETE);
                        return $this;
                    } elseif (str_starts_with($contentType = $this->header('Content-Type'), 'multipart/form-data')) {
                        $this->isUpload = true;
                        $uploadBaseInfo = explode(';', $contentType);
                        $_arr           = array();
                        foreach ($uploadBaseInfo as $item) {
                            $itemInfo                  = explode('=', $item);
                            $_arr[ltrim($itemInfo[0])] = $itemInfo[1] ?? '';
                        }
                        if (!$this->uploadBaseInfo['boundary'] = $_arr['boundary'] ?? null) {
                            //TODO: not find boundary
                            $this->signStatusCode(Request::INVALID);
                        }
                        $this->uploadBaseInfo['status'] = 'prepare';
                        // TODO: prepare recv file content
                        $this->bodyLength = strlen($this->body);
                        if (!$this->nextUpload(explode("\r\n", $this->body))) {
                            $this->signStatusCode(Request::INVALID);
                        } elseif ($this->bodyLength === intval($this->header('Content-Length'))) {
                            $this->signStatusCode(Request::COMPLETE);
                        } else {
                            $this->signStatusCode(Request::INCOMPLETE);
                        }
                    }
                }
            } else {
                $this->buffer($context);
            }
        } elseif ($this->method === 'POST') {
            $this->body       .= $context;
            $this->bodyLength += strlen($context);
            if ($this->isUpload) {
                if (!$this->nextUpload(explode("\r\n", $this->body))) {
                    $this->signStatusCode(Request::INVALID);
                }
            }
            if ($this->bodyLength === $this->header('Content-Length')) {
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
     * Set the method
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
     * Set the version
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
     * Set the request header
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
    private function initUploadBaseInfo(): void
    {
        $contentType                      = $this->header('Content-Type');
        $this->uploadBaseInfo['boundary'] = substr($contentType, strpos($contentType, 'boundary=') + 9);
    }

    /**
     * @return void
     */
    private function processCurrentPart(): void
    {
        if ($this->param['currentPartIndex'] < count($this->param['parts'])) {
            $part          = $this->param['parts'][$this->param['currentPartIndex']];
            $headerAndData = explode("\r\n\r\n", $part, 2);

            if (count($headerAndData) !== 2) {
                // Invalid part
                $this->signStatusCode(self::INVALID);
                return;
            }

            $header = $headerAndData[0];
            $data   = $headerAndData[1];

            $this->param['currentPartIndex']++;
        } else {
            // All parts processed
            $this->signStatusCode(self::COMPLETE);
        }
    }

    /**
     * continue parse upload context
     *
     * @param array $lines
     * @return bool
     */
    private function nextUpload(array $lines): bool
    {
        while ($streamLine = array_shift($lines)) {
            if ($this->uploadBaseInfo['status'] === 'prepare') {
                if (str_starts_with($streamLine, '--' . $this->uploadBaseInfo['boundary'])) {
                    $theFileInfo                    = array();
                    $this->uploadBaseInfo['status'] = 'transfer';
                    $dispositionAndName             = explode(';', array_shift($lines));
                    $disposition                    = explode(':', $dispositionAndName[0])[1];
                    $name                           = explode('=', $dispositionAndName[1])[1];
                    $fileName                       = explode('=', $dispositionAndName[2])[1];
                    $contentType                    = explode(':', array_shift($lines))[1] ?? '';
                    array_shift($lines);
                    $theFileInfo['disposition']                = ltrim($disposition);
                    $theFileInfo['name']                       = trim($name, '" ');
                    $theFileInfo['fileName']                   = trim($fileName, '" ');
                    $theFileInfo['contentType']                = ltrim($contentType);
                    $this->uploadBaseInfo['currentFilePath']   = PRIPPLE_CACHE_PATH . FS . md5(microtime(true) . rand(1, 9));
                    $this->uploadBaseInfo['currentFileStream'] = fopen($this->uploadBaseInfo['currentFilePath'], 'a+');
                    $theFileInfo['path']                       = $this->uploadBaseInfo['currentFilePath'];
                    $this->uploadBaseInfo['files'][]           = $theFileInfo;
                } else {
                    return false;
                }
            } elseif ($this->uploadBaseInfo['status'] = 'transfer') {
                if (str_contains('--' . $this->uploadBaseInfo['boundary'] . '--', $streamLine)) {
                    if ('--' . $this->uploadBaseInfo['boundary'] . '--' === $streamLine) {
                        //TODO: a file upload completed
                        $this->uploadBaseInfo['status'] = 'prepare';
                        fclose($this->uploadBaseInfo['currentFileStream']);
                        return $this->nextUpload($lines);
                    } else {
                        $this->body = $streamLine;
                        return true;
                    }
                } else {
                    fwrite($this->uploadBaseInfo['currentFileStream'], $streamLine);
                }
            } else {
                return false;
            }
        }
        $this->body = '';
        return true;
    }

    /**
     * Provide the file path and bind the file information
     *
     * @param string $path
     * @return $this
     */
    public function setFile(string $path): self
    {
        $this->fileInfo = pathinfo($path);
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
