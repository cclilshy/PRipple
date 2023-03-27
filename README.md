# PRipple

## 环境要求

- OS Linux
- PHP 8.2+
- 扩展
  - posix
  - pcntl
  - socket

```php
php server.php // 启用调度器
php client.php // 启用服务,(监听端口2222)
php notice.php // 模拟轮训通知
```