<?php
/**
 * ION Channel Directory Router
 * 
 * This index.php handles all /channel/ requests and routes them to:
 * - iongeo.php → Geographic hierarchy (countries, states, cities)
 * - ioncity.php → Individual city pages
 * 
 * The existing .htaccess rules allow this to work naturally:
 * - /channel/ → loads this index.php → shows countries
 * - /channel/slug → loads this index.php with PATH_INFO → routes based on slug
 */

// Get the requested slug from the URL
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path_info = $_SERVER['PATH_INFO'] ?? '';

// Parse the slug from the URL
// Example: /channel/united-states → slug = 'united-states'
// Example: /channel/ → slug = '' (show countries)
$slug = '';

if (preg_match('#^/channel/([a-z0-9\-]+)/?#i', $request_uri, $matches)) {
    $slug = $matches[1];
} elseif (!empty($path_info) && $path_info !== '/') {
    $slug = trim($path_info, '/');
}

// Set $_GET['slug'] for iongeo.php to use
$_GET['slug'] = $slug;

// Route to iongeo.php which handles the geographic hierarchy
require_once __DIR__ . '/iongeo.php';

