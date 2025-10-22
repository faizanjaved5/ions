<?php
/**
 * Test Session Variables
 * Quick diagnostic to check what's in the session
 */

session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
    'user_email' => $_SESSION['user_email'] ?? 'NOT SET',
    'user_role' => $_SESSION['user_role'] ?? 'NOT SET',
    'all_session_keys' => array_keys($_SESSION),
    'server_time' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);

