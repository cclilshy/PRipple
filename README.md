# PRipple
> A pure event-driven PHP high-concurrency asynchronous framework developed in subscription-publishing mode

## Environmental requirements
- OS Linux- PHP 8.2+
- PHP-Extension `posix` `pcntl` `sockets` `fileinfo`

## Install
```bash
git clone https://github.com/cclilshy/PRipplecd PRipple
composer install
```

## Run
```bash
bin/pripple dth start
```

## Plan
> PERFORMANCE CULPRIT* Fiber* Recycle