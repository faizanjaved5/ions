<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Controllers\HomeController;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

require __DIR__ . '/../bootstrap/app.php';

// Minimal: always render the home page (no routing)
(new HomeController())->index();

/*
// If you later want a trivial 'controller/action' switch without a router lib,
// uncomment this tiny dispatcher (still optional):
// $c = $_GET['c'] ?? 'Home';
// $a = $_GET['a'] ?? 'index';
// $class = "App\\Controllers\\{$c}Controller";
// if (class_exists($class) && method_exists($class, $a)) {
//     (new $class())->{$a}();
// } else {
//     http_response_code(404);
//     echo "Not Found";
// }
*/
