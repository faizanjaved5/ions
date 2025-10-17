<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Badge API test endpoint working',
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'get_data' => $_GET
]);
?>