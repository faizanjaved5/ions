<?php
// Minimal upload handler to test basic functionality
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Start session
session_start();

// Load configuration
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    die(json_encode(['error' => 'Config file not found']));
}
$config = require_once $config_path;

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? 'debug';

switch ($action) {
    case 'debug':
        echo json_encode([
            'success' => true,
            'message' => 'Minimal upload handler working',
            'timestamp' => date('Y-m-d H:i:s'),
            'config_loaded' => isset($config),
            'r2_config_exists' => isset($config['cloudflare_r2_api']),
            'action' => $action
        ]);
        break;
        
    case 'upload':
        // Simple upload test
        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            break;
        }
        
        $file = $_FILES['file'];
        echo json_encode([
            'success' => true,
            'message' => 'File received successfully',
            'filename' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type']
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}
?>
