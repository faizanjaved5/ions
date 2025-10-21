<?php
/**
 * Environment Variables Setup for ION Platform
 * 
 * This script helps set up environment variables for development.
 * In production, these should be set at the server level.
 */

// Load .env file if it exists
function loadEnvFile($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    
    return true;
}

// Try to load .env file from various locations
$env_paths = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
    $_SERVER['DOCUMENT_ROOT'] . '/.env'
];

$env_loaded = false;
foreach ($env_paths as $path) {
    if (loadEnvFile($path)) {
        $env_loaded = true;
        break;
    }
}

// Set default values if environment variables are not set
$defaults = [
    'CLOUDFLARE_R2_BUCKET_NAME' => 'ion-videos',
    'CLOUDFLARE_R2_REGION' => 'auto',
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'true'
];

foreach ($defaults as $key => $default_value) {
    if (!getenv($key)) {
        putenv("$key=$default_value");
        $_ENV[$key] = $default_value;
    }
}

return $env_loaded;
?>