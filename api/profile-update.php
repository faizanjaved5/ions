<?php
/**
 * API Endpoints for Profile Wizard
 * Place these files in /api/ directory
 */

// File: /api/profile-update.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['wizard_csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

try {
    global $db;
    
    $user_id = (int) $_SESSION['user_id'];
    
    // Prepare update data
    $update_data = [];
    
    // Basic info
    if (isset($_POST['fullName'])) {
        $update_data['fullname'] = trim($_POST['fullName']);
    }
    
    // Profile info
    if (isset($_POST['profileName'])) {
        $update_data['profile_name'] = trim($_POST['profileName']);
    }
    
    if (isset($_POST['profileHandle'])) {
        $handle = trim($_POST['profileHandle']);
        // Check if handle is available
        $existing = $db->get_row("SELECT user_id FROM IONEERS WHERE handle = ? AND user_id != ?", $handle, $user_id);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Handle already taken']);
            exit;
        }
        $update_data['handle'] = $handle;
    }
    
    // Contact info
    if (isset($_POST['phone'])) {
        $update_data['phone_number'] = trim($_POST['phone']);
    }
    
    if (isset($_POST['dateOfBirth'])) {
        $update_data['dob'] = $_POST['dateOfBirth'];
    }
    
    if (isset($_POST['location'])) {
        $update_data['location'] = trim($_POST['location']);
    }
    
    if (isset($_POST['websiteUrl'])) {
        $update_data['user_url'] = trim($_POST['websiteUrl']);
    }
    
    // About info
    if (isset($_POST['bio'])) {
        $update_data['about'] = trim($_POST['bio']);
    }
    
    // Handle photo upload
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
        // In production, you would:
        // 1. Validate file type and size
        // 2. Generate unique filename
        // 3. Upload to storage service (S3, etc.)
        // 4. Get the URL
        // For now, we'll just store a placeholder
        $update_data['photo_url'] = 'https://ui-avatars.com/api/?name=' . urlencode($update_data['fullname'] ?? '');
    } elseif (isset($_POST['profilePhotoUrl'])) {
        $update_data['photo_url'] = trim($_POST['profilePhotoUrl']);
    }
    
    // Add metadata
    $update_data['profile_completed'] = 1;
    $update_data['profile_completed_at'] = date('Y-m-d H:i:s');
    
    // Update database
    $result = $db->update('IONEERS', $update_data, ['user_id' => $user_id]);
    
    if ($result !== false) {
        // Update session
        if (isset($update_data['fullname'])) {
            $_SESSION['fullname'] = $update_data['fullname'];
        }
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
    
} catch (Exception $e) {
    error_log('Profile update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>