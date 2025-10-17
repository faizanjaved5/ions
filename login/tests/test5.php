<?php
// test-config.php - Create this file in your /login/ directory to test config loading
echo "<h1>Config Test</h1>";

echo "<h2>1. Config File Path Test</h2>";
$config_path = __DIR__ . '/../config/config.php';
echo "<p><strong>Config path:</strong> " . $config_path . "</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($config_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($config_path)) {
    echo "<h2>2. Config File Contents</h2>";
    try {
        $config = require $config_path;
        echo "<p><strong>Config type:</strong> " . gettype($config) . "</p>";
        
        if (is_array($config)) {
            echo "<p><strong>Config keys:</strong> " . implode(', ', array_keys($config)) . "</p>";
            
            echo "<h3>Google OAuth Settings:</h3>";
            echo "<p><strong>Client ID:</strong> " . (isset($config['google_client_id']) ? substr($config['google_client_id'], 0, 20) . "..." : 'NOT SET') . "</p>";
            echo "<p><strong>Client Secret:</strong> " . (isset($config['google_client_secret']) ? 'SET (length: ' . strlen($config['google_client_secret']) . ')' : 'NOT SET') . "</p>";
            echo "<p><strong>Redirect URI:</strong> " . ($config['google_redirect_uri'] ?? 'NOT SET') . "</p>";
            
            // Test if the values are actually strings and not empty
            if (isset($config['google_client_id'])) {
                echo "<p><strong>Client ID is string:</strong> " . (is_string($config['google_client_id']) ? 'YES' : 'NO') . "</p>";
                echo "<p><strong>Client ID length:</strong> " . strlen($config['google_client_id']) . "</p>";
                echo "<p><strong>Client ID first 30 chars:</strong> " . substr($config['google_client_id'], 0, 30) . "...</p>";
            }
        } else {
            echo "<p style='color: red;'>ERROR: Config is not an array!</p>";
            echo "<pre>" . print_r($config, true) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>ERROR loading config: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>Config file not found!</p>";
}

echo "<h2>3. Direct Variable Test</h2>";
// Test the exact same way your google-oauth.php loads it
require_once __DIR__ . '/../config/config.php';

echo "<p><strong>Client ID variable:</strong> " . (isset($client_id) ? substr($client_id, 0, 20) . "..." : 'NOT SET') . "</p>";
echo "<p><strong>Client Secret variable:</strong> " . (isset($client_secret) ? 'SET (length: ' . strlen($client_secret) . ')' : 'NOT SET') . "</p>";
echo "<p><strong>Redirect URI variable:</strong> " . ($redirect_uri ?? 'NOT SET') . "</p>";

echo "<h2>4. Direct Config Array Access</h2>";
if (isset($config)) {
    $test_client_id = $config['google_client_id'];
    $test_client_secret = $config['google_client_secret'];
    $test_redirect_uri = $config['google_redirect_uri'];
    
    echo "<p><strong>Array Client ID:</strong> " . (isset($test_client_id) ? substr($test_client_id, 0, 20) . "..." : 'NOT SET') . "</p>";
    echo "<p><strong>Array Client Secret:</strong> " . (isset($test_client_secret) ? 'SET (length: ' . strlen($test_client_secret) . ')' : 'NOT SET') . "</p>";
    echo "<p><strong>Array Redirect URI:</strong> " . ($test_redirect_uri ?? 'NOT SET') . "</p>";
}
?>