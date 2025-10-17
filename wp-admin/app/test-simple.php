<?php
// Simple test to check basic PHP execution
header('Content-Type: application/json');

try {
    echo json_encode([
        'status' => 'PHP working',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
