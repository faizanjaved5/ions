<?php
/**
 * API Endpoint: Update Video Status
 * Allows Admin/Owner to change video status (Pending, Approved, Rejected, Paused)
 */

// Suppress any output before JSON header
ob_start();

// Suppress PHP warnings/notices that might output HTML
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON content type FIRST to prevent HTML errors
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Get user role from database
$user_email = $_SESSION['user_email'];
$user_data = $db->get_row("SELECT user_role FROM IONEERS WHERE email = %s", $user_email);

if (!$user_data) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_role = $user_data->user_role;

// Check if user can manage video status (Admin/Owner only)
if (!in_array($user_role, ['Owner', 'Admin'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Access denied - Admin permissions required']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $video_id = intval($_POST['video_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    
    // Validate input
    if ($video_id <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit();
    }
    
    // Valid status options
    $valid_statuses = ['Pending', 'Approved', 'Rejected', 'Paused'];
    
    if (!in_array($new_status, $valid_statuses)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid status value']);
        exit();
    }
    
    // Get video info using PDO directly
    $pdo = $db->getPDO();
    
    // Get video info with user email joined from IONEERS table
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.status, v.user_id, u.email as user_email 
        FROM IONLocalVideos v
        LEFT JOIN IONEERS u ON v.user_id = u.user_id
        WHERE v.id = ?
    ");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$video) {
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'error' => "Video not found (ID: $video_id)"
        ]);
        exit();
    }
    
    // Update video status using PDO directly
    $stmt = $pdo->prepare("UPDATE IONLocalVideos SET status = ? WHERE id = ?");
    $result = $stmt->execute([$new_status, $video_id]);
    
    if (!$result) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
        exit();
    }
    
    // Log the status change for security/audit purposes
    error_log("VIDEO STATUS CHANGE: User {$_SESSION['user_email']} ({$user_role}) changed video {$video_id} ('{$video->title}') status from '{$video->status}' to '{$new_status}'");
    
    // Optional: Send notification email to video owner if status changed to approved/rejected
    if (in_array($new_status, ['Approved', 'Rejected']) && !empty($video->user_email)) {
        // You could implement email notification here
        // sendStatusChangeNotification($video->user_email, $video->title, $new_status);
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => "Video status changed to {$new_status}",
        'new_status' => $new_status,
        'status_class' => 'status-' . str_replace(' ', '-', strtolower($new_status))
    ]);
    
} catch (Exception $e) {
    error_log('Video status update error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Clean any output buffer
    ob_end_clean();
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

