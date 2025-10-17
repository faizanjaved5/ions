<?php
// File: /api/clear-wizard-flag.php
session_start();
header('Content-Type: application/json');

// Clear the wizard flag
unset($_SESSION['show_profile_wizard']);

echo json_encode(['success' => true]);
?>