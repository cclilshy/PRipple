<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

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


class Request
{
    public const INVALID    = -1; // Invalid status code constant
    public const COMPLETE   = 2;  // Request complete status code constant
    public const INCOMPLETE = 1;  // Request incomplete status code constant

    public mixed       $currentFileStream;
    public string      $path;                 // Request URI
    public string      $method;               // Request method
    public string      $version;              // Request version
    public string      $clientAddress;        // Client address
    public mixed       $socket;               // Client socket
    public array       $header;               // Request headers
    public array       $uploadInfo;           // Upload information
    public int         $bodyLength = 0;       // Request body length
    public string      $body;                 // Request body content
    public array       $param      = array(); // Request parameters in the body
    public bool        $isUpload   = false;   // Flag indicating whether the request is an upload request
    public bool        $complete   = false;   // Flag indicating whether the request is complete
    public string      $buffer     = '';      // Request buffer
    public string      $name;                 // Client name
    public bool        $isStatic;             // Flag indicating whether the request is for a static resource
    public string      $hash;                 // Request routing hash
    public int         $statusCode;           // Response status code
    public Map|null    $route;                // Request route
    public Statistics  $statistics;           // Request statistics
    public Response    $response;             // Request response
    public SocketAisle $clientSocket;         // Client socket for the request

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
                $headerContext = substr($context, 0, $headerEnd);
                $this->body    = substr($context, $headerEnd + 4);
                $base          = strtok($headerContext, "\r\n");
                $base          = explode(' ', $base);

                if (count($base) !== 3) {
                    $this->signStatusCode(Request::INVALID);
                    return false;
                }

                $this->method = $base[0];
                $this->setPath($base[1]);
                $this->version = $base[2];

                while ($line = strtok("\r\n")) {
                    $lineParam                         = explode(':', $line, 2);
                    $this->header[trim($lineParam[0])] = trim($lineParam[1]);
                }

                if ($this->method === 'GET') {
                    $this->signStatusCode(Request::COMPLETE);
                    return $this;
                } elseif (str_starts_with($contentType = $this->header('Content-Type'), 'multipart/form-data')) {
                    $this->isUpload = true;
                    $uploadInfo     = explode(';', $contentType);
                    foreach ($uploadInfo as $item) {
                        $itemInfo                             = explode('=', $item, 2);
                        $this->uploadInfo[trim($itemInfo[0])] = $itemInfo[1] ?? '';
                    }
                    if (!$this->uploadInfo['boundary']) {
                        $this->signStatusCode(Request::INVALID);
                        return false;
                    }
                    $this->uploadInfo['status'] = 'prepare';
                    $this->bodyLength           = strlen($this->body);
                    $list                       = explode("\r\n", $this->body);
                    if (!$this->nextUpload($list)) {
                        $this->signStatusCode(Request::INVALID);
                    } elseif ($this->bodyLength === intval($this->header('Content-Length'))) {
                        $this->signStatusCode(Request::COMPLETE);
                    } else {
                        $this->signStatusCode(Request::INCOMPLETE);
                    }
                }
            } else {
                $this->buffer($context);
            }
        } elseif ($this->method === 'POST') {
            $this->body       .= $context;
            $this->bodyLength += strlen($context);
            if ($this->isUpload) {
                $lines = explode("\r\n", $this->body);
                if (!$this->nextUpload($lines)) {
                    $this->signStatusCode(Request::INVALID);
                }
            }

            if ($this->bodyLength === intval($this->header('Content-Length'))) {
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
     * continue parse upload context
     *
     * @param array $lines
     * @return bool
     */
    private function nextUpload(array $lines): bool
    {
        $this->body = '';
        while ($streamLine = array_shift($lines)) {
            if ($this->uploadInfo['status'] === 'prepare') {
                // TODO: Parse the basic information of the uploaded file
                if (!str_starts_with($streamLine, '--' . $this->uploadInfo['boundary'])) {
                    return false;
                }
                $theFileInfo                = array();
                $this->uploadInfo['status'] = 'transfer';
                $dispositionAndName         = explode(';', array_shift($lines));
                $disposition                = explode(':', $dispositionAndName[0])[1];
                $name                       = explode('=', $dispositionAndName[1])[1];
                $fileName                   = explode('=', $dispositionAndName[2])[1];
                $contentType                = explode(':', array_shift($lines))[1] ?? '';
                array_shift($lines);
                $theFileInfo['disposition']          = trim($disposition);
                $theFileInfo['name']                 = trim($name, '" ');
                $theFileInfo['fileName']             = trim($fileName, '" ');
                $theFileInfo['contentType']          = trim($contentType);
                $this->uploadInfo['currentFilePath'] = PRIPPLE_CACHE_PATH . FS . md5(microtime(true) . rand(1, 9));
                $this->currentFileStream             = fopen($this->uploadInfo['currentFilePath'], 'a+');
                $theFileInfo['path']                 = $this->uploadInfo['currentFilePath'];
                $this->uploadInfo['files'][]         = $theFileInfo;
                $this->uploadInfo['endLine']         = '--' . $this->uploadInfo['boundary'] . '--';
            } elseif ($this->uploadInfo['status'] === 'transfer') {
                // TODO: transfer process
                if (str_contains($streamLine, $this->uploadInfo['endLine'])) {
                    if ($this->uploadInfo['endLine'] === $streamLine) {
                        // TODO: is the file context end
                        $this->uploadInfo['status'] = 'prepare';
                        fclose($this->currentFileStream);
                        return $this->nextUpload($lines);
                    }
                    // TODO: is a truncated packet
                    $this->body = $streamLine;
                    return true;
                }
                fwrite($this->currentFileStream, $streamLine);
                if (count($lines) > 0) {
                    fwrite($this->currentFileStream, "\r\n");
                }
            } else {
                return false;
            }
        }
        return true;
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
     * @return Map|null
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
            'header',
            'body',
            'bodyLength',
            'param',
            'complete',
            'name',
            'hash',
            'statusCode',
            'route',
            'response',
            'statistics',
            'isUpload',
            'clientAddress',
        ];
    }

    /**
     * @param Client $socket
     * @return void
     */
    public function setClientSocket(Client $socket): void
    {
        $this->clientSocket = $socket;
    }
}
