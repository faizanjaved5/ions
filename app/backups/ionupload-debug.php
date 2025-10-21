<?php
// Debug script to check R2 configuration
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Try different config paths
$config_paths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../../config/config.php'
];

$config_path = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config_path = $path;
        break;
    }
}
$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tested_paths' => $config_paths,
    'found_config_path' => $config_path,
    'config_file_exists' => $config_path ? file_exists($config_path) : false
];

if ($config_path && file_exists($config_path)) {
    $config = require $config_path;
    $debug_info['config_loaded'] = true;
    $debug_info['config_type'] = gettype($config);
    $debug_info['config_keys'] = is_array($config) ? array_keys($config) : 'NOT_ARRAY';
    
    if (is_array($config) && isset($config['cloudflare_r2_api'])) {
        $r2_config = $config['cloudflare_r2_api'];
        $debug_info['r2_config_exists'] = true;
        $debug_info['r2_config_keys'] = array_keys($r2_config);
        $debug_info['r2_has_account_id'] = !empty($r2_config['account_id']);
        $debug_info['r2_has_bucket_name'] = !empty($r2_config['bucket_name']);
        $debug_info['r2_has_access_key_id'] = !empty($r2_config['access_key_id']);
        $debug_info['r2_has_secret_key'] = !empty($r2_config['secret_access_key']);
        $debug_info['r2_has_endpoint'] = !empty($r2_config['endpoint']);
    } else {
        $debug_info['r2_config_exists'] = false;
    }
} else {
    $debug_info['config_loaded'] = false;
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
