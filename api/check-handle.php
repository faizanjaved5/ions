<?php

// File: /api/check-handle.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$handle = isset($_GET['handle']) ? trim($_GET['handle']) : '';

if (empty($handle)) {
    echo json_encode(['available' => false]);
    exit;
}

try {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Check if handle exists for another user
    $existing = $db->get_row("SELECT user_id FROM IONEERS WHERE handle = ? AND user_id != ?", $handle, $user_id);
    
    echo json_encode(['available' => !$existing]);
    
} catch (Exception $e) {
    echo json_encode(['available' => false]);
}

?>