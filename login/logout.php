<?php
session_start();
session_unset();
session_destroy();

// Get the return URL from query parameter or referer
$return_to = $_GET['return_to'] ?? $_SERVER['HTTP_REFERER'] ?? '/';

// Security: Only allow internal redirects
if (!str_starts_with($return_to, '/') && !str_starts_with($return_to, 'http://' . $_SERVER['HTTP_HOST']) && !str_starts_with($return_to, 'https://' . $_SERVER['HTTP_HOST'])) {
    $return_to = '/';
}

// Parse the URL to remove any existing login page references
$parsed = parse_url($return_to);
$path = $parsed['path'] ?? '/';

// Don't redirect back to protected pages (app/, creator dashboard, etc.)
$protected_paths = ['/app/', '/login/', '/join/'];
foreach ($protected_paths as $protected) {
    if (str_starts_with($path, $protected)) {
        $return_to = '/';
        break;
    }
}

header('Location: ' . $return_to);
exit;
?>