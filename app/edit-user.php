<?php
require_once __DIR__ . '/../login/session.php';
require_once __DIR__ . '/../config/database.php';

// SECURITY CHECK: Ensure user is authenticated
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Fetch user data for role-based permissions
$user_email = $_SESSION['user_email'];
$user_data = $db->get_row($db->prepare("SELECT user_id, user_role FROM IONEERS WHERE email = %s", $user_email));

if (!$user_data) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Check if this is a self-edit request
$is_self_edit = isset($_POST['is_self_edit']) && $_POST['is_self_edit'] === '1';

if ($is_self_edit) {
    // Self-edit mode: user can only edit their own profile
    $edit_user_id = $_POST['edit_user_id'] ?? null;
    
    // Security check: ensure user is editing their own profile
    if ($edit_user_id != $user_data->user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only edit your own profile']);
        exit();
    }
    
    // For self-edit, we don't need role-based access control
} else {
    // Admin edit mode: require proper permissions
    require_once '../login/roles.php';
    
    // Only allow Admin and Owner roles to edit other users
    $user_role = trim($user_data->user_role ?? '');
    if (!IONRoles::canAccessSection($user_role, 'IONEERS')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }
    
    // Get the user ID to edit
    $edit_user_id = $_POST['edit_user_id'] ?? null;
    if (!$edit_user_id) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit();
    }
    
    // Check if the user exists
    $existing_user = $db->get_row($db->prepare("SELECT user_id, user_role FROM IONEERS WHERE user_id = %d", $edit_user_id));
    if (!$existing_user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    // Security check: Admins cannot edit Owners
    if ($user_role === 'Admin' && $existing_user->user_role === 'Owner') {
        echo json_encode(['success' => false, 'error' => 'Admins cannot edit Owner accounts']);
        exit();
    }
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get form data
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$profile_name = trim($_POST['profile_name'] ?? '');
$handle = trim($_POST['handle'] ?? '');
$phone_number = trim($_POST['phone'] ?? ''); // Form uses 'phone' but DB column is 'phone_number'
$dob = trim($_POST['dob'] ?? '');
$location = trim($_POST['location'] ?? '');
$user_url = trim($_POST['user_url'] ?? '');
$about = trim($_POST['about'] ?? '');

// Debug logging
error_log('Profile Update - POST data: ' . print_r($_POST, true));
error_log('Profile Update - handle: ' . $handle);
error_log('Profile Update - dob: ' . $dob);
error_log('Profile Update - photo_option: ' . ($_POST['photo_option'] ?? 'not set'));

// For self-edit mode, don't allow role/status changes
if (!$is_self_edit) {
    $user_role_new = trim($_POST['user_role'] ?? '');
    $status = trim($_POST['status'] ?? '');
}

// Validate required fields
if (empty($fullname) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Full name and email are required']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit();
}

// Check if email is already taken by another user
$existing_email = $db->get_var($db->prepare("SELECT user_id FROM IONEERS WHERE email = %s AND user_id != %d", $email, $edit_user_id));
if ($existing_email) {
    echo json_encode(['success' => false, 'error' => 'Email is already taken by another user']);
    exit();
}

// Check if handle is already taken by another user (if provided)
if (!empty($handle)) {
    $existing_handle = $db->get_var($db->prepare("SELECT user_id FROM IONEERS WHERE handle = %s AND user_id != %d", $handle, $edit_user_id));
    if ($existing_handle) {
        echo json_encode(['success' => false, 'error' => 'Handle is already taken by another user']);
        exit();
    }
}

// Handle profile photo
$photo_url = '';
if (isset($_POST['photo_option']) && $_POST['photo_option'] === 'url') {
    $photo_url = trim($_POST['photo_url'] ?? '');
} elseif (isset($_POST['photo_option']) && $_POST['photo_option'] === 'upload' && isset($_FILES['photo_file'])) {
    // Handle file upload
    $file = $_FILES['photo_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed']);
            exit();
        }
        
        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File size must be less than 5MB']);
            exit();
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $edit_user_id . '_' . time() . '.' . $extension;
        
        // Use config for upload path
        $config = include(__DIR__ . '/../config/config.php');
        $upload_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $config['mediaUploadPath'];
        
        // Create upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $photo_url = $config['mediaUploadPrefix'] . $filename;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
            exit();
        }
    }
}

// Prepare update data
$update_data = [
    'fullname' => $fullname,
    'email' => $email,
    'profile_name' => $profile_name,
    'handle' => $handle,
    'phone_number' => $phone_number, // Database column is phone_number
    'dob' => $dob,
    'location' => $location,
    'user_url' => $user_url,
    'about' => $about
];

// For admin edit mode, include role and status
if (!$is_self_edit) {
    $update_data['user_role'] = $user_role_new;
    $update_data['status'] = $status;
}

// Add photo URL if we have one
if (!empty($photo_url)) {
    $update_data['photo_url'] = $photo_url;
}

// Build the SQL update query
$set_clauses = [];
$update_params = [];

foreach ($update_data as $field => $value) {
    // Always include fullname and email (required fields)
    if ($field === 'fullname' || $field === 'email') {
        $set_clauses[] = "$field = %s";
        $update_params[] = $value;
    }
    // For other fields, update even if empty (allows clearing values)
    elseif ($value !== '') {
        $set_clauses[] = "$field = %s";
        $update_params[] = $value;
    }
    // For DOB and other nullable fields, allow NULL when empty
    elseif (in_array($field, ['dob', 'phone_number', 'location', 'user_url', 'photo_url']) && $value === '') {
        $set_clauses[] = "$field = NULL";
    }
}

if (empty($set_clauses)) {
    echo json_encode(['success' => false, 'error' => 'No data to update']);
    exit();
}

// Add the user ID to the parameters
$update_params[] = $edit_user_id;

// Build and execute the query
$sql = "UPDATE IONEERS SET " . implode(', ', $set_clauses) . " WHERE user_id = %d";
$result = $db->query($db->prepare($sql, ...$update_params));

if ($result === false) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->last_error]);
    exit();
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'User updated successfully',
    'user_id' => $edit_user_id
]);
?>
