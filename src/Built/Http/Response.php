<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2023-03-06 16:48:58
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

class Response
{
    public mixed   $client;
    public int     $statusCode  = 400;
    public array   $header;
    public float   $version;
    public string  $charset     = 'utf-8';
    public string  $contentType = 'text/html';
    public string  $body;
    public string  $name;
    public string  $hash;
    public Request $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->version    = 1.1;
        $this->statusCode = 200;
        $this->header     = [
            'Server'       => 'PRipple',
            'Connection'   => 'keep-alive',
            'Content-Type' => "{$this->contentType}; charset={$this->charset}",
        ];
        $this->request    = $request;
        $this->name       = $request->getName();
        $this->hash       = $request->getHash();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * @param float $version
     * @return $this
     */
    public function setHttpVersion(float $version): Response
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setStatusCode(int $code): Response
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * @param string $charset
     * @return $this
     */
    public function setCharset(string $charset): Response
    {
        $this->charset = $charset;
        return $this->setHeader('Content-Type', "{$this->contentType}; charset={$charset}");
    }

    /**
     * @param string          $key
     * @param string|int|null $value
     * @return $this
     */
    public function setHeader(string $key, string|int|null $value): Response
    {
        $this->header[$key] = $value;
        return $this;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setContentType(string $type): Response
    {
        $this->contentType = $type;
        return $this->setHeader('Content-Type', "{$type}; charset={$this->charset}");
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setBody(string $content): Response
    {
        $this->body = $content;
        return $this->setHeader('Content-Length', strlen($content));
    }

    /**
     * @param $client
     * @return $this
     */
    public function setClient($client): Response
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @param string $cookie
     * @return $this
     */
    public function setCookie(string $cookie): Response
    {
        return $this->setHeader('Set-Cookie', $cookie);
    }

    /**
     * @param string $context
     * @return \Cclilshy\PRipple\Built\Http\Response
     */
    public function send(string $context): self
    {
        socket_write($this->client, $this->__toString());
        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getFullHttpContext();
    }

    /**
     * @return string
     */
    public function getFullHttpContext(): string
    {
        return $this->getHttpHeaderContext() . $this->getHttpBodyContext();
    }

    /**
     * @return string
     */
    public function getHttpHeaderContext(): string
    {
        $header = "HTTP/{$this->version} {$this->statusCode} OK\r\n";
        foreach ($this->header as $key => $value) {
            $header .= "{$key}: {$value}\r\n";
        }
        $header .= "\r\n";
        return $header;
    }

    /**
     * @return string
     */
    public function getHttpBodyContext(): string
    {
        return $this->body;
    }

    /**
     * @return \Cclilshy\PRipple\Built\Http\Response
     */
    public function result(): Response
    {
        return $this;
    }

    /**
     * @return int
     */
    public function getContentLength(): int
    {
        return strlen($this->body);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * @return string[]
     */
    public function __sleep()
    {
        return ['name', 'statusCode', 'header', 'version', 'charset', 'contentType', 'body', 'hash'];
    }
}
