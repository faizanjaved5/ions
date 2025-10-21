<?php
require_once __DIR__ . '/../login/session.php';
require_once __DIR__ . '/../config/database.php';

// Set headers for JSON response
header('Content-Type: application/json');

// SECURITY CHECK: Ensure user is authenticated
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// SECURITY CHECK: Ensure user has admin access
$user_email = $_SESSION['user_email'];
$user_data = $db->get_row($db->prepare("SELECT user_role FROM IONEERS WHERE email = %s", $user_email));

if (!$user_data) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Only allow Admin and Owner roles to add users
$user_role = trim($user_data->user_role ?? '');
if (!in_array($user_role, ['Admin', 'Owner'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit();
}

// Get input data - handle both JSON and multipart form data
$input = [];
if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    // Handle multipart form data (file uploads)
    $input = $_POST;
} else {
    // Handle JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Fallback to POST data
        $input = $_POST;
    }
}

// Debug logging
error_log("Add user request received. Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("Processed input: " . print_r($input, true));

// Additional debugging for required fields
error_log("Checking required fields:");
foreach (['fullname', 'email', 'user_role', 'status'] as $field) {
    if (isset($input[$field])) {
        error_log("Field '$field' exists with value: '" . $input[$field] . "' (length: " . strlen($input[$field]) . ")");
    } else {
        error_log("Field '$field' does NOT exist in input");
    }
}

// Validate required fields
$required_fields = ['fullname', 'email', 'user_role', 'status'];
$missing_fields = [];

foreach ($required_fields as $field) {
    error_log("Validating field '$field':");
    if (!isset($input[$field])) {
        error_log("  - Field '$field' is NOT SET");
        $missing_fields[] = $field;
    } elseif (empty($input[$field])) {
        error_log("  - Field '$field' is EMPTY (value: '" . $input[$field] . "')");
        $missing_fields[] = $field;
    } else {
        error_log("  - Field '$field' is VALID (value: '" . $input[$field] . "')");
    }
}

if (!empty($missing_fields)) {
    error_log("Validation failed. Missing fields: " . implode(', ', $missing_fields));
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Validate email format
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit();
}

// Check if email already exists
$existing_user = $db->get_row($db->prepare("SELECT email FROM IONEERS WHERE email = %s", $input['email']));
if ($existing_user) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email already exists']);
    exit();
}

// Validate role
$valid_roles = ['Owner', 'Admin', 'Creator', 'Member', 'Viewer', 'Guest'];
if (!in_array($input['user_role'], $valid_roles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit();
}

// Validate status
$valid_statuses = ['active', 'inactive', 'blocked'];
if (!in_array($input['status'], $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}

// Validate phone number if provided
if (!empty($input['phone']) && !preg_match('/^[\+]?[1-9][\d]{0,15}$/', $input['phone'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid phone number format']);
    exit();
}

// Validate photo URL if provided
if (!empty($input['photo_url']) && !filter_var($input['photo_url'], FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid photo URL format']);
    exit();
}

// Validate website URL if provided
if (!empty($input['user_url']) && !filter_var($input['user_url'], FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid website URL format']);
    exit();
}

// Handle photo file upload if provided
$photo_url = null;
if (!empty($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['photo_file'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $uploaded_file['tmp_name']);
    finfo_close($file_info);
    
    if (!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.']);
        exit();
    }
    
    // Validate file size (5MB max)
    if ($uploaded_file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File size must be less than 5MB.']);
        exit();
    }
    
    // Generate filename using username and user_id (we'll get user_id after insertion)
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($input['fullname'])));
    $temp_filename = $username . '_temp_' . uniqid() . '.' . pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    
    // Use config for upload path
    $config = include(__DIR__ . '/../config/config.php');
    $upload_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $config['mediaUploadPath'] . $temp_filename;
    
    // Ensure upload directory exists
    $upload_dir = dirname($upload_path);
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($uploaded_file['tmp_name'], $upload_path)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file.']);
        exit();
    }
    
    // Store temp filename for later processing
    $temp_photo_path = $upload_path;
}

// Prepare user data for insertion
$user_data = [
    'email' => trim($input['email']),
    'fullname' => trim($input['fullname']),
    'user_role' => $input['user_role'],
    'status' => $input['status'],
    'created_at' => date('Y-m-d H:i:s'),
    'last_login' => null,
    'login_count' => 0
];

// Add optional fields if provided
if (!empty($input['phone'])) {
    $user_data['phone'] = trim($input['phone']);
}

// Handle photo URL - either from upload or URL input
if (!empty($input['photo_url'])) {
    $user_data['photo_url'] = trim($input['photo_url']);
} elseif (isset($temp_photo_path)) {
    // We'll update this after we get the user_id
    $user_data['photo_url'] = null; // Temporary placeholder
}

if (!empty($input['dob'])) {
    $user_data['dob'] = $input['dob'];
}

if (!empty($input['profile_name'])) {
    $user_data['profile_name'] = trim($input['profile_name']);
}

if (!empty($input['user_url'])) {
    $user_data['user_url'] = trim($input['user_url']);
}

// Set default preferences
$default_preferences = [
    'Theme' => 'Default',
    'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
    'Background' => ['#6366f1', '#7c3aed'],
    'ButtonColor' => '#4f46e5',
    'DefaultMode' => 'LightMode'
];
$user_data['preferences'] = json_encode($default_preferences);

try {
    // Insert new user
    $result = $db->insert('IONEERS', $user_data);
    
    if ($result) {
        // Get the inserted user data for confirmation
        $new_user = $db->get_row($db->prepare("SELECT user_id, email, fullname, user_role, status FROM IONEERS WHERE email = %s", $input['email']));
        
        // Handle uploaded file renaming if we have a temp file
        if (isset($temp_photo_path) && $new_user && $new_user->user_id) {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($input['fullname'])));
            $final_filename = $username . '_' . $new_user->user_id . '.' . pathinfo($temp_photo_path, PATHINFO_EXTENSION);
            
            // Use config for final path
            $config = include(__DIR__ . '/../config/config.php');
            $final_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $config['mediaUploadPath'] . $final_filename;
            
            // Rename the temp file to final filename
            if (rename($temp_photo_path, $final_path)) {
                // Update the database with the final photo URL
                $photo_url = $config['mediaUploadPrefix'] . $final_filename;
                $db->update('IONEERS', ['photo_url' => $photo_url], ['user_id' => $new_user->user_id]);
            } else {
                // If renaming fails, clean up the temp file
                if (file_exists($temp_photo_path)) {
                    unlink($temp_photo_path);
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'User added successfully',
            'user' => [
                'user_id' => $new_user->user_id ?? 'N/A',
                'email' => $new_user->email,
                'fullname' => $new_user->fullname,
                'user_role' => $new_user->user_role,
                'status' => $new_user->status
            ]
        ]);
    } else {
        // Clean up temp file if insertion failed
        if (isset($temp_photo_path) && file_exists($temp_photo_path)) {
            unlink($temp_photo_path);
        }
        
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to add user: ' . ($db->last_error ?: 'Unknown error')
        ]);
    }
} catch (Exception $e) {
    // Clean up temp file if an error occurred
    if (isset($temp_photo_path) && file_exists($temp_photo_path)) {
        unlink($temp_photo_path);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
