<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? '1' : '0');

define('BASE_PATH', dirname(__DIR__));

require __DIR__ . '/helpers.php';

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
        echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Application error.';
    }
});
