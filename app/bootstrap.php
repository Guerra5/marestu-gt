<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Marestu\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require_once $path;
});

require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/flash.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/format.php';
