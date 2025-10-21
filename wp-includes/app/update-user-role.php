<?php
/*
 * Update user role endpoint for IONEERS
 */

// Start output buffering to prevent any stray output
ob_start();

// Suppress PHP warnings to prevent HTML output contamination
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start session and load required files
session_start();

// Try multiple paths to find config files
$config_paths = [
    '../config/config.php',
    '../../config/config.php',
    dirname(__DIR__) . '/config/config.php',
    __DIR__ . '/../config/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        // Use output buffering for config inclusion
        ob_start();
        require_once $path;
        ob_end_clean();
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

// Load database
$database_paths = [
    '../config/database.php',
    '../../config/database.php',
    dirname(__DIR__) . '/config/database.php',
    __DIR__ . '/../config/database.php'
];

$database_loaded = false;
foreach ($database_paths as $path) {
    if (file_exists($path)) {
        // Use output buffering for database inclusion
        ob_start();
        require_once $path;
        ob_end_clean();
        $database_loaded = true;
        break;
    }
}

// Load roles
$roles_paths = [
    '../login/roles.php',
    '../../login/roles.php',
    dirname(__DIR__) . '/login/roles.php',
    __DIR__ . '/../login/roles.php'
];

foreach ($roles_paths as $path) {
    if (file_exists($path)) {
        // Use output buffering for roles inclusion
        ob_start();
        require_once $path;
        ob_end_clean();
        break;
    }
}

// Clean any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

// Check authentication (using same pattern as other app files)
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get user role from database (like other files) instead of relying on session
$user_email = $_SESSION['user_email'];
$user_data = null;

try {
    // Database should already be loaded from the paths above
    if (!isset($db) || !$db) {
        error_log("UPDATE ROLE ERROR: Database connection not established");
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    $user_data = $db->get_row($db->prepare("SELECT user_role FROM IONEERS WHERE email = %s", $user_email));
    
    // Debug: Log the query result
    error_log("UPDATE ROLE DEBUG: User lookup for email '$user_email' returned: " . print_r($user_data, true));
    
} catch (Exception $e) {
    error_log("UPDATE ROLE ERROR: Database query failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database query failed: ' . $e->getMessage()]);
    exit;
}

if (!$user_data) {
    error_log("UPDATE ROLE ERROR: User not found in database for email: $user_email");
    echo json_encode(['success' => false, 'error' => 'User not found in database']);
    exit;
}

$user_role = trim($user_data->user_role ?? '');

// Debug log the role for troubleshooting  
error_log("UPDATE ROLE DEBUG: User email: $user_email, Role from DB: '$user_role', Session keys: " . implode(', ', array_keys($_SESSION)));
error_log("UPDATE ROLE DEBUG: Role length: " . strlen($user_role) . ", Trimmed length: " . strlen(trim($user_role)));
error_log("UPDATE ROLE DEBUG: Role in array check: " . (in_array($user_role, ['Owner', 'Admin']) ? 'TRUE' : 'FALSE'));

// Check if user has permission to update roles
if (!in_array($user_role, ['Owner', 'Admin'])) {
    error_log("UPDATE ROLE DEBUG: Permission denied - Role '$user_role' not in allowed roles");
    echo json_encode([
        'success' => false, 
        'error' => "Insufficient permissions. Your role: $user_role",
        'debug' => [
            'role' => $user_role,
            'role_length' => strlen($user_role),
            'allowed_roles' => ['Owner', 'Admin'],
            'email' => $user_email
        ]
    ]);
    exit;
}

// Get JSON input
$raw_input = file_get_contents('php://input');
error_log("UPDATE ROLE DEBUG: Raw input received: '$raw_input'");

$input = json_decode($raw_input, true);
error_log("UPDATE ROLE DEBUG: Decoded JSON: " . print_r($input, true));

// Try JSON first, then fall back to POST data
if (!$input || !isset($input['user_id']) || !isset($input['role'])) {
    error_log("UPDATE ROLE DEBUG: JSON failed, trying POST data");
    if (isset($_POST['user_id']) && isset($_POST['role'])) {
        $input = $_POST;
        error_log("UPDATE ROLE DEBUG: Using POST data: " . print_r($input, true));
    } else {
        error_log("UPDATE ROLE DEBUG: No valid data found - JSON: " . print_r($input, true) . " POST: " . print_r($_POST, true));
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: user_id or role']);
        exit;
    }
}

$user_id = trim($input['user_id']);
$new_role = trim($input['role']);

// Convert user_id to integer early for consistent use
$int_user_id = intval($user_id);

// Debug the input values
error_log("UPDATE ROLE DEBUG: Raw input user_id: '" . $input['user_id'] . "', role: '" . $input['role'] . "'");
error_log("UPDATE ROLE DEBUG: Trimmed user_id: '$user_id', role: '$new_role'");
error_log("UPDATE ROLE DEBUG: user_id type: " . gettype($user_id) . ", length: " . strlen($user_id));
error_log("UPDATE ROLE DEBUG: int_user_id: $int_user_id");

// Validate role - Owners can assign Owner role, others cannot
$valid_roles = ['Admin', 'Member', 'Viewer', 'Creator', 'Guest'];
if ($user_role === 'Owner') {
    $valid_roles[] = 'Owner'; // Owners can assign Owner role
}

if (!in_array($new_role, $valid_roles)) {
    echo json_encode(['success' => false, 'error' => 'Invalid role value']);
    exit;
}

// SECURITY: Only Owners can assign Owner role
if ($new_role === 'Owner' && $user_role !== 'Owner') {
    echo json_encode(['success' => false, 'error' => 'Only Owners can assign Owner role']);
    exit;
}

// Validate user_id
if (empty($user_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

try {
    // Check current user role to prevent changing Owners
    // Use integer for user_id lookup too
    $current_user = $db->get_row($db->prepare("SELECT user_role FROM IONEERS WHERE user_id = %d", $int_user_id));
    
    if (!$current_user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // SECURITY: Only Owners can change other Owner's roles, Admins cannot
    if ($current_user->user_role === 'Owner' && $user_role !== 'Owner') {
        echo json_encode(['success' => false, 'error' => 'Only Owners can change other Owner roles']);
        exit;
    }
    
    // Update user role using wpdb->update() method for more robust updates
    $result = $db->update(
        'IONEERS',
        ['user_role' => $new_role],
        ['user_id' => $int_user_id],
        ['%s'],
        ['%d']
    );
    
    error_log("UPDATE ROLE DEBUG: Updating user $user_id to role $new_role");
    error_log("UPDATE ROLE DEBUG: wpdb->update() result: " . var_export($result, true));
    error_log("UPDATE ROLE DEBUG: Rows affected: " . $db->rows_affected);
    error_log("UPDATE ROLE DEBUG: Last error: " . $db->last_error);
    
    if ($result > 0) {
        error_log("Successfully updated user $user_id role to $new_role by {$_SESSION['user_email']}");
        echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
    } else {
        error_log("Failed to update user $user_id role - no rows affected. DB Error: " . $db->last_error);
        echo json_encode(['success' => false, 'error' => 'User not found or role unchanged. Debug: ' . $db->last_error]);
    }
    
} catch (Exception $e) {
    error_log("Error updating user role: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>