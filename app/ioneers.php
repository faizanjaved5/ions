<?php
require_once __DIR__ . '/../login/session.php';
error_log('ioneers.php: Execution started.');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/database.php';
error_log('ioneers.php: Database environment loaded.');
// Using $db directly instead of wpdb alias
error_log('ioneers.php: Database object is valid.');
// Test database connection
if (!$db->isConnected()) {
    error_log('IONEERS CRITICAL: Database connection failed!');
    error_log('IONEERS CRITICAL: Last error: ' . $db->last_error);
    die('Database connection failed. Please check server configuration.');
}
error_log('IONEERS: Database connection verified.');
// SECURITY CHECK: Ensure user is authenticated
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login/index.php');
    exit();
}
// Fetch user data from IONEERS table for profile photo, role-based permissions, and UI preferences
$user_email = $_SESSION['user_email'];
$user_data = $db->get_row($db->prepare("SELECT user_id, photo_url, user_role, preferences, fullname FROM IONEERS WHERE email = %s", $user_email));
// SECURITY CHECK: Ensure user exists in database and has admin access
if (!$user_data) {
    error_log("SECURITY: User {$user_email} not found in database");
    session_unset();
    session_destroy();
    header('Location: /login/index.php?error=unauthorized');
    exit();
}
// Include role-based access control
require_once '../login/roles.php';
// Only allow Admin and Owner roles to access user management
$user_role = trim($user_data->user_role ?? '');
$user_unique_id = $user_data->user_id ?? null;

// Debug: Log the current user's role for troubleshooting
error_log("IONEERS DEBUG: Current user email: $user_email, Role: '$user_role', User ID: $user_unique_id");
if (!IONRoles::canAccessSection($user_role, 'IONEERS')) {
    error_log("SECURITY: User {$user_email} with role {$user_role} denied access to user management");
    header('Location: /app/directory.php?error=access_denied');
    exit();
}
$user_photo_url = $user_data->photo_url ?? null;
$user_fullname = $user_data->fullname ?? null;
// Parse user preferences with defaults
$default_preferences = [
    'Theme' => 'Default',
    'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
    'Background' => ['#6366f1', '#7c3aed'],
    'ButtonColor' => '#4f46e5',
    'DefaultMode' => 'LightMode'
];
$user_preferences = $default_preferences;
if (!empty($user_data->preferences)) {
    $parsed_preferences = json_decode($user_data->preferences, true);
    if (is_array($parsed_preferences)) {
        $user_preferences = array_merge($default_preferences, $parsed_preferences);
    }
}
// Helper function for escaping output
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
// Fetch user statistics
$stats = [
    'total'    => $db->get_var("SELECT COUNT(*) FROM IONEERS"),
    'active'   => $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE status = 'active' OR status IS NULL OR status = ''"),
    'admins'   => $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE user_role IN ('Owner', 'Admin')"),
    'members'  => $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE user_role = 'Member'"),
    'viewers'  => $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE user_role = 'Viewer'"),
    'creators' => $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE user_role = 'Creator'")
];
// Handle search and filter functionality - UPDATED WITH PROVEN LOGIC
$search_term = trim($_GET['q'] ?? $_GET['search'] ?? '');

// DEBUG: Check database collation
if (!empty($search_term)) {
    $collation_info = $db->get_results("SHOW TABLE STATUS LIKE 'IONEERS'");
    if (!empty($collation_info)) {
        error_log("IONEERS SEARCH DEBUG: Table collation: " . ($collation_info[0]->Collation ?? 'Unknown'));
    }
    
    // Test case sensitivity directly
    $test_query = "SELECT fullname FROM IONEERS WHERE LOWER(fullname) LIKE '%dave%' LIMIT 1";
    $test_result = $db->get_var($test_query);
    error_log("IONEERS SEARCH DEBUG: Direct LOWER test for 'dave': " . ($test_result ?? 'NULL'));
    
    $test_query2 = "SELECT fullname FROM IONEERS WHERE LOWER(fullname) LIKE '%Dave%' LIMIT 1";
    $test_result2 = $db->get_var($test_query2);
    error_log("IONEERS SEARCH DEBUG: Direct LOWER test for 'Dave': " . ($test_result2 ?? 'NULL'));
    
    // Test the specific problematic names
    $test_query3 = "SELECT fullname FROM IONEERS WHERE LOWER(fullname) LIKE '%sayed%' LIMIT 1";
    $test_result3 = $db->get_var($test_query3);
    error_log("IONEERS SEARCH DEBUG: Direct LOWER test for 'sayed': " . ($test_result3 ?? 'NULL'));
    
    $test_query4 = "SELECT fullname FROM IONEERS WHERE LOWER(fullname) LIKE '%Sayed%' LIMIT 1";
    $test_result4 = $db->get_var($test_query4);
    error_log("IONEERS SEARCH DEBUG: Direct LOWER test for 'Sayed': " . ($test_result4 ?? 'NULL'));
    
    // Check for exact matches and character analysis
    $exact_sayed = $db->get_results("SELECT fullname, LENGTH(fullname) as name_length, HEX(fullname) as name_hex FROM IONEERS WHERE fullname LIKE '%Sayed%' LIMIT 3");
    error_log("IONEERS SEARCH DEBUG: Exact 'Sayed' matches: " . json_encode($exact_sayed));
    
    $exact_sayed_lower = $db->get_results("SELECT fullname, LENGTH(fullname) as name_length, HEX(fullname) as name_hex FROM IONEERS WHERE LOWER(fullname) LIKE '%sayed%' LIMIT 3");
    error_log("IONEERS SEARCH DEBUG: LOWER 'sayed' matches: " . json_encode($exact_sayed_lower));
}
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'role';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$country_filter = isset($_GET['country']) ? $_GET['country'] : '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$date_field = $_GET['date_field'] ?? '';

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30; // Show 30 users per page
$offset = ($page - 1) * $per_page;

// Initialize search arrays
$where_conditions = [];
$params = [];

// Advanced search term parsing (from search-test.php)
if (!empty($search_term)) {
    $search_conditions = [];
    $search_params = [];
    
    // Define search fields for IONEERS - ALL SEARCHABLE USER PROPERTIES
    $search_fields = ['email', 'fullname', 'wp_user_id', 'user_id', 'profile_name', 'handle', 'user_login', 'location', 'slug', 'phone_number'];
    
    // DEBUG: Log search fields
    error_log("IONEERS SEARCH DEBUG: Search fields: " . json_encode($search_fields));
    
    // DEBUG: Check what fields actually contain the search term
    $debug_fields = ['fullname', 'profile_name', 'handle', 'email'];
    foreach ($debug_fields as $field) {
        $debug_query = "SELECT {$field} FROM IONEERS WHERE {$field} LIKE '%Sayed%' LIMIT 1";
        $debug_result = $db->get_var($debug_query);
        error_log("IONEERS SEARCH DEBUG: Field '{$field}' contains 'Sayed': " . ($debug_result ?? 'NULL'));
    }
    
    // Check for @ search - profile handle or email domain search
    if (strpos($search_term, '@') !== false) {
        // @ search: search for profile handle and email domain
        // Extract term after @
        if (strpos($search_term, '@') === 0) {
            // If @ is at the beginning, use everything after it
            $handle_term = strtolower(substr($search_term, 1));
        } else {
            // If @ is somewhere else, use it as is for email search and extract after @ for handle
            $at_pos = strpos($search_term, '@');
            $handle_term = strtolower(substr($search_term, $at_pos + 1));
        }
        $escaped_handle = $db->esc_like($handle_term);
        
        error_log("IONEERS SEARCH DEBUG: @ search detected, searching for handle/domain: " . $handle_term);
        
        // Search for:
        // 1. Handle matching the term (without @)
        // 2. Email containing @term in the domain part
        $search_conditions[] = "(LOWER(handle) LIKE CONCAT('%%', %s, '%%') OR LOWER(email) LIKE CONCAT('%%@', %s, '%%'))";
        $search_params[] = $escaped_handle;
        $search_params[] = $escaped_handle;
        
    // Parse search term based on type - CASE-INSENSITIVE using LOWER()
    } elseif (preg_match('/^"(.+)"$/', $search_term, $matches)) {
        // EXACT PHRASE: "john doe" - CASE-INSENSITIVE
        $exact_phrase = strtolower($matches[1]);
        $escaped_phrase = $db->esc_like($exact_phrase);
        $field_conditions = [];
        
        foreach ($search_fields as $field) {
            if (in_array($field, ['user_id', 'wp_user_id'], true)) {
                $field_conditions[] = "LOWER(CAST($field AS CHAR)) LIKE CONCAT('%%', %s, '%%')";
            } else {
                $field_conditions[] = "LOWER($field) LIKE CONCAT('%%', %s, '%%')";
            }
            $search_params[] = $escaped_phrase;
        }
        $search_conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
        
    } elseif (stripos($search_term, ' AND ') !== false) {
        // AND SEARCH: john AND doe - CASE-INSENSITIVE
        $and_terms = preg_split('/\s+AND\s+/i', $search_term);
        $and_terms = array_values(array_filter(array_unique($and_terms))); // dedupe & remove empties
        
        foreach ($and_terms as $term) {
            $term = strtolower(trim($term));
            if (!empty($term)) {
                $escaped_term = $db->esc_like($term);
                $field_conditions = [];
                foreach ($search_fields as $field) {
                    if (in_array($field, ['user_id', 'wp_user_id'], true)) {
                        $field_conditions[] = "LOWER(CAST($field AS CHAR)) LIKE CONCAT('%%', %s, '%%')";
                    } else {
                        $field_conditions[] = "LOWER($field) LIKE CONCAT('%%', %s, '%%')";
                    }
                    $search_params[] = $escaped_term;
                }
                $search_conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
            }
        }
        
    } else {
        // OR SEARCH: john doe (either word) - CASE-INSENSITIVE
        $search_params = [];
        
        $or_terms = preg_split('/\s+/', trim($search_term));
        $or_terms = array_values(array_filter(array_unique($or_terms))); // dedupe & remove empties
        
        $term_conditions = [];
        foreach ($or_terms as $term) {
            $term = strtolower($term);
            $escaped_term = $db->esc_like($term);
            $field_conditions = [];
            foreach ($search_fields as $field) {
                if (in_array($field, ['user_id', 'wp_user_id'], true)) {
                    $field_conditions[] = "LOWER(CAST($field AS CHAR)) LIKE CONCAT('%%', %s, '%%')";
                } else {
                    $field_conditions[] = "LOWER($field) LIKE CONCAT('%%', %s, '%%')";
                }
                $search_params[] = $escaped_term;
            }

            $term_conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
        }
        
        if ($term_conditions) {
            // OR = match ANY keyword (changed from AND to match partial names)
            $search_conditions[] = '(' . implode(' OR ', $term_conditions) . ')';
        }
    }
    
    // Add search conditions to main WHERE clause
    if (!empty($search_conditions)) {
        $where_conditions[] = implode(' AND ', $search_conditions);
        $params = array_merge($params, $search_params);
    }
}

// Role filter
if (!empty($role_filter)) {
    if ($role_filter === 'Admin') {
        // When filtering by "Admin", include both Owner and Admin roles
        $where_conditions[] = "user_role IN ('Owner', 'Admin')";
    } else {
        $where_conditions[] = "user_role = %s";
        $params[] = $role_filter;
    }
}

// Status filter
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "(status = 'active' OR status IS NULL OR status = '')";
    } else {
        $where_conditions[] = "status = %s";
        $params[] = $status_filter;
    }
}

// Date range filter
if (!empty($date_from) || !empty($date_to)) {
    if (!empty($date_field)) {
        $date_conditions = [];
        
        if (!empty($date_from)) {
            $date_conditions[] = "$date_field >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        
        if (!empty($date_to)) {
            $date_conditions[] = "$date_field <= %s";
            $params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($date_conditions)) {
            $where_conditions[] = '(' . implode(' AND ', $date_conditions) . ')';
        }
    }
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count first for pagination - UPDATED WITH PROVEN METHOD
$count_query = "SELECT COUNT(*) FROM IONEERS $where_clause";

if (!empty($params)) {
    $prepared_count = call_user_func_array(array($db, 'prepare'), array_merge(array($count_query), $params));
    $total_users = $db->get_var($prepared_count);
} else {
    $total_users = $db->get_var($count_query);
}

$total_pages = ceil($total_users / $per_page);

// Build ORDER BY clause
$order_clause = 'ORDER BY ';
switch ($sort_option) {
    case 'name':
        $order_clause .= 'fullname ASC';
        break;
    case 'login':
        $order_clause .= 'last_login DESC';
        break;
    case 'joined':
        $order_clause .= 'created_at DESC';
        break;
    default: // role
        $order_clause .= 'CASE user_role 
            WHEN \'Owner\' THEN 1 
            WHEN \'Admin\' THEN 2 
            WHEN \'Creator\' THEN 3 
            WHEN \'Member\' THEN 4 
            WHEN \'Viewer\' THEN 5 
            ELSE 6 
        END, last_login DESC';
        break;
}

// Build final query with manual LIMIT for MySQL compatibility
$users_query = "
    SELECT user_id, email, fullname, user_role, status, photo_url, last_login, created_at,
           google_id, discord_user_id, x_user_id, meta_facebook_id, linkedin_id,
           user_login, wp_user_id, user_url, profile_name, dob, handle, profile_visibility,
           location, about, slug, phone_number
    FROM IONEERS 
    $where_clause
    $order_clause
    LIMIT $offset, $per_page
";

// Execute with proper parameter handling
if (!empty($params)) {
    $prepared_users = call_user_func_array(array($db, 'prepare'), array_merge(array($users_query), $params));
    
    // DEBUG: Log the final query and parameters
    error_log("IONEERS SEARCH DEBUG: Final query: " . $prepared_users);
    error_log("IONEERS SEARCH DEBUG: Parameters: " . json_encode($params));
    
    $users = $db->get_results($prepared_users);
} else {
    error_log("IONEERS SEARCH DEBUG: No parameters, executing: " . $users_query);
    $users = $db->get_results($users_query);
}
if ($db->last_error) {
    error_log("IONEERS ERROR: " . $db->last_error);
} else {
    error_log("IONEERS SEARCH DEBUG: Found " . count($users) . " users");
    if (!empty($users) && !empty($search_term)) {
        error_log("IONEERS SEARCH DEBUG: First result fullname: '" . ($users[0]->fullname ?? 'NULL') . "'");
        error_log("IONEERS SEARCH DEBUG: First result email: '" . ($users[0]->email ?? 'NULL') . "'");
    }
}

// Debug: Log the first user to see what fields we have
if (!empty($users)) {
    error_log("IONEERS DEBUG: First user object: " . print_r($users[0], true));
    error_log("IONEERS DEBUG: User count: " . count($users));
}
// Add video counts to users after main query (more efficient)
if (!empty($users)) {
    foreach ($users as $user) {
        // Count all videos created by this user (regardless of upload_status for admin view)
        // Use user_id which is how videos are actually linked to users
        // Include ALL videos regardless of status for comprehensive admin view
        $video_count = $db->get_var($db->prepare(
            "SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s", 
            $user->user_id
        ));
        $user->video_count = intval($video_count);
        
        // Debug logging to help troubleshoot
        if ($user->user_role === 'Admin' && $video_count > 0) {
            error_log("IONEERS DEBUG: Admin user {$user->email} (ID: {$user->user_id}) has {$video_count} videos");
        }
    }
}
// Basic validation check
if (count($users) < $per_page && $page == 1 && empty($search_term) && empty($status_filter) && empty($role_filter)) {
    error_log("IONEERS WARNING: Only " . count($users) . " users returned on page 1, expected up to $per_page");
}
// Duplicate video count logic removed - already handled above

// Handle AJAX requests - return JSON instead of HTML
if ($is_ajax) {
    error_log("IONEERS: AJAX request detected, returning JSON response");
    
    // Set JSON content type
    header('Content-Type: application/json');
    
    // Prepare JSON response
    $json_response = [
        'success' => true,
        'users' => $users,
        'total' => $total_users,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'showing_start' => count($users) > 0 ? ($offset + 1) : 0,
        'showing_end' => count($users) > 0 ? min($offset + count($users), $total_users) : 0,
        'search_term' => $search_term,
        'filters' => [
            'status' => $status_filter,
            'role' => $role_filter,
            'country' => $country_filter
        ],
        'stats' => $stats
    ];
    
    // Return JSON response
    echo json_encode($json_response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION User Management</title>
    <link rel="stylesheet" href="directory.css?v=<?php echo time(); ?>">
    <!-- Force refresh for debugging -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        /* Base styles for dark theme */
        body {
            background: #1a1a1a;
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 0px;
            margin: 0;
        }
        /* User Card Container */
        .user-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            max-width: 330px;
            margin: 0 auto 20px;
        }
        /* Card gradients based on Role */
        .user-card[data-role="owner"] {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(167, 139, 250, 0.1));
            border: 1px solid rgba(139, 92, 246, 0.4);
        }
        .user-card[data-role="admin"] {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.15), rgba(239, 68, 68, 0.1));
            border: 1px solid rgba(220, 38, 38, 0.4);
        }
        .user-card[data-role="member"] {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(59, 130, 246, 0.1));
            border: 1px solid rgba(37, 99, 235, 0.4);
        }
        .user-card[data-role="viewer"] {
            background: linear-gradient(135deg, rgba(107, 114, 246, 0.15), rgba(156, 163, 175, 0.1));
            border: 1px solid rgba(107, 114, 246, 0.4);
        }
        .user-card[data-role="guest"] {
            background: linear-gradient(135deg, rgba(156, 163, 175, 0.15), rgba(209, 213, 219, 0.1));
            border: 1px solid rgba(156, 163, 175, 0.4);
        }
        .user-card[data-role="creator"] {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1));
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        /* Status modifiers - adds subtle accent */
        .user-card[data-status="active"] {
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15);
        }
        .user-card[data-status="inactive"] {
            opacity: 0.85;
            filter: grayscale(0.3);
        }
        .user-card[data-status="banned"],
        .user-card[data-status="blocked"] {
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.5) !important;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        /* Hover effects for each role */
        .user-card[data-role="owner"]:hover {
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
        }
        .user-card[data-role="admin"]:hover {
            box-shadow: 0 8px 30px rgba(220, 38, 38, 0.3);
        }
        .user-card[data-role="member"]:hover {
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.3);
        }
        .user-card[data-role="viewer"]:hover {
            box-shadow: 0 8px 30px rgba(107, 114, 246, 0.3);
        }
        .user-card[data-role="guest"]:hover {
            box-shadow: 0 8px 30px rgba(156, 163, 175, 0.3);
        }
        .user-card[data-role="creator"]:hover {
            box-shadow: 0 8px 30px rgba(245, 158, 11, 0.3);
        }
        
        /* Fix for Chrome Edge gray background issue */
        @supports (-ms-ime-align: auto) {
            .user-header, .user-info, .role-status-row {
                background: transparent !important;
            }
        }
        
        /* Additional Chrome Edge specific fix */
        .user-card .user-header,
        .user-card .user-info,
        .user-card .role-status-row {
            background: transparent !important;
        }
        /* User Header Section */
        .user-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0rem;
            position: relative;
            background: transparent; /* Fix gray background issue in Chrome Edge */
        }
        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            flex-shrink: 0;
            margin-top: 0;
        }
        .user-info {
            flex: 1;
            min-width: 0;
            position: relative;
            margin-top: -2px; /* Fine-tune alignment with avatar top */
            background: transparent; /* Fix gray background issue in Chrome Edge */
        }
        .user-info h3 {
            margin: 0 0 0.75rem 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
            line-height: 1;
        }
        /* Role and Status Row - Spaced Apart */
        .role-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
            background: transparent; /* Fix gray background issue in Chrome Edge */
            margin-top: 30px
        }
        /* Role Badge and Dropdown */
        .role-dropdown-container {
            position: relative;
            display: inline-block;
        }
        .user-role {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            white-space: nowrap;
        }
        /* Role-specific colors */
        .role-owner {
            background: rgba(139, 92, 246, 0.2);
            color: #e9d5ff;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        .role-owner:hover {
            background: rgba(139, 92, 246, 0.3);
        }
        .role-admin {
            background: rgba(220, 38, 38, 0.2);
            color: #fecaca;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }
        .role-admin:hover {
            background: rgba(220, 38, 38, 0.3);
        }
        .role-member {
            background: rgba(37, 99, 235, 0.2);
            color: #bfdbfe;
            border: 1px solid rgba(37, 99, 235, 0.3);
        }
        .role-member:hover {
            background: rgba(37, 99, 235, 0.3);
        }
        .role-viewer {
            background: rgba(107, 114, 246, 0.2);
            color: #e5e7eb;
            border: 1px solid rgba(107, 114, 246, 0.3);
        }
        .role-viewer:hover {
            background: rgba(107, 114, 246, 0.3);
        }
        .role-guest {
            background: rgba(156, 163, 175, 0.2);
            color: #f3f4f6;
            border: 1px solid rgba(156, 163, 175, 0.3);
        }
        .role-guest:hover {
            background: rgba(156, 163, 175, 0.3);
        }
        .role-creator {
            background: rgba(245, 158, 11, 0.2);
            color: #fef3c7;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .role-creator:hover {
            background: rgba(245, 158, 11, 0.3);
        }
        .user-role:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .role-arrow {
            transition: transform 0.2s ease;
        }
        .user-role.dropdown-open .role-arrow {
            transform: rotate(180deg);
        }
        /* Dropdown */
        .role-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            background: #2a2a2a;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            min-width: 120px;
            z-index: 100;
            overflow: hidden;
        }
        .role-option {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background 0.2s ease;
            font-size: 0.85rem;
        }
        .role-option:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        /* Status Badge */
        .status-container {
            position: relative;
            display: inline-block;
        }
        .user-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        /* Status-specific colors */
        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-active:hover {
            background: rgba(16, 185, 129, 0.3);
        }
        .status-inactive {
            background: rgba(107, 114, 246, 0.2);
            color: #e5e7eb;
            border: 1px solid rgba(107, 114, 246, 0.3);
        }
        .status-inactive:hover {
            background: rgba(107, 114, 246, 0.3);
        }
        .status-banned, .status-blocked {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .status-banned:hover, .status-blocked:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .user-status:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .status-arrow {
            transition: transform 0.2s ease;
        }
        .user-status.dropdown-open .status-arrow {
            transform: rotate(180deg);
        }
        .status-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            background: #2a2a2a;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            min-width: 100px;
            z-index: 100;
            overflow: hidden;
        }
        .status-option {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background 0.2s ease;
            font-size: 0.85rem;
        }
        .status-option:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        /* OAuth Icons - Positioned below status */
        .oauth-icons-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }
        .social-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .social-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        .social-icon img {
            width: 20px;
            height: 20px;
            filter: brightness(0) invert(1);
        }
        /* Individual OAuth provider styling */
        .social-icon.google:hover {
            background: rgba(219, 68, 55, 0.3);
        }
       
        .social-icon.discord:hover {
            background: rgba(88, 101, 242, 0.3);
        }
       
        .social-icon.linkedin:hover {
            background: rgba(0, 119, 181, 0.3);
        }
        /* User Details with OAuth Container */
        .details-with-oauth {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin: 0rem 0;
        }
        /* User Details Section */
        .user-details {
            flex: 1;
            font-size: 0.9rem;
        }
        .user-details p {
            margin: 0.5rem 0;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .user-details p strong {
            display: inline-block;
            min-width: 24px;
        }
        /* User Actions - Smaller with Subtle Colors */
        .user-actions {
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        .action-btn {
            padding: 0.4rem 0.6rem;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: default;
            font-weight: 500;
            transition: none;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.05);
            pointer-events: none;
        }
        /* Updated action button styles for role-specific actions */
        .btn-view {
            background: rgba(5, 150, 105, 0.15);
            border-color: rgba(5, 150, 105, 0.3);
            color: #a7f3d0;
        }
        .btn-edit {
            background: rgba(37, 99, 235, 0.15);
            border-color: rgba(37, 99, 235, 0.3);
            color: #bfdbfe;
        }
        .btn-delete {
            background: rgba(220, 38, 38, 0.15);
            border-color: rgba(220, 38, 38, 0.3);
            color: #fecaca;
        }
        .btn-manage {
            background: rgba(124, 58, 246, 0.15);
            border-color: rgba(124, 58, 246, 0.3);
            color: #e9d5ff;
        }
        /* Legacy button classes for backward compatibility */
        .btn-read {
            background: rgba(5, 150, 105, 0.15);
            border-color: rgba(5, 150, 105, 0.3);
            color: #a7f3d0;
        }
        .btn-write {
            background: rgba(37, 99, 235, 0.15);
            border-color: rgba(37, 99, 235, 0.3);
            color: #bfdbfe;
        }


        /* Video Row - Special Styling */
        .video-row {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin: 1rem 0 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }
        /* Video count 0 - matches card background */
        .video-row.count-0 {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .video-row.count-0 .video-label,
        .video-row.count-0 .video-count {
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
        }
        /* Video count 1-10 - Yellow */
        .video-row.count-low {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .video-row.count-low .video-label,
        .video-row.count-low .video-count {
            color: #fbbf24;
        }
        /* Video count 11-99 - Green */
        .video-row.count-medium {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .video-row.count-medium .video-label,
        .video-row.count-medium .video-count {
            color: #34d399;
        }
        /* Video count 100+ - Orange/Red */
        .video-row.count-high {
            background: rgba(251, 113, 133, 0.1);
            border: 1px solid rgba(251, 113, 133, 0.3);
        }
        .video-row.count-high .video-label,
        .video-row.count-high .video-count {
            color: #fb7185;
        }
        .video-row:hover {
            transform: translateX(2px);
        }
        .video-row.count-0:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }
        .video-row.count-low:hover {
            background: rgba(245, 158, 11, 0.15);
            border-color: rgba(245, 158, 11, 0.4);
        }
        .video-row.count-medium:hover {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.4);
        }
        .video-row.count-high:hover {
            background: rgba(251, 113, 133, 0.15);
            border-color: rgba(251, 113, 133, 0.4);
        }
        .video-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        .video-count {
            font-size: 1.2rem;
            font-weight: 700;
        }
        /* Utility class for clickable elements */
        .clickable {
            cursor: pointer;
            user-select: none;
        }
        /* Container for consistent layout */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0px;
        }
        /* Additional styles for user management */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            margin-bottom: 2rem;
        }

        /* Pagination styles */
        .pagination-btn:hover {
            background: rgba(59, 130, 246, 0.3) !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
            font-weight: bold;
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        .stat-card p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
            color: #e2e8f0 !important;
        }
        .stat-card.active-filter {
            border-color: rgba(59, 130, 246, 0.6);
            background: rgba(59, 130, 246, 0.1);
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.3);
        }
        .stat-card.total    { border-left: 4px solid #f59e0b; }
        .stat-card.active   { border-left: 4px solid #10b981; }
        .stat-card.admins   { border-left: 4px solid #ef4444; }
        .stat-card.members  { border-left: 4px solid #3b82f6; }
        .stat-card.viewers  { border-left: 4px solid #8b5cf6; }
        .stat-card.creators { border-left: 4px solid #f97316; }
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        /* User videos modal */
        .user-videos-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
        }
        .user-videos-modal.active {
            display: flex;
        }
        .user-videos-modal-content {
            background: var(--bg-primary, #1a1a1a);
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 900px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            color: var(--text-primary, #ffffff);
        }
        .user-videos-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-videos-modal-title {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-videos-modal-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .user-videos-modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .user-videos-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
        }
        .user-video-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .user-video-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .user-video-thumbnail {
            width: 80px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .user-video-info {
            flex: 1;
        }
        .user-video-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .user-video-meta {
            font-size: 0.85em;
            color: rgba(255, 255, 255, 0.6);
        }
        .user-video-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-live { background: #22c55e; color: white; }
        .status-pending { background: #f59e0b; color: white; }
        .status-rejected { background: #ef4444; color: white; }
        /* Search results info */
        .search-results-info {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #22c55e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-results-info svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 0.75rem;
            }
        }
        

    </style>
</head>
<body style="background: <?= h($user_preferences['Background'][0] ?? '#0f172a') ?>;">
<?php
// Configure header for ION User Directory
$header_config = [
    'title' => 'ION User Directory',
    'search_placeholder' => 'Search by name or email',
    'search_value' => $search_term,
    'active_tab' => 'IONEERS',
    'button_text' => '+ Add ION User',
    'button_id' => 'add-user-btn',
    'button_class' => '',
    'show_button' => true,
    'mobile_button_text' => 'Add ION User'
];
include 'headers.php';
?>
<!-- Consistent Toolbar (same as IONS Directory) -->
<div class="results-controls">
    <div class="left">
        <strong>Showing <?= count($users) > 0 ? ($offset + 1) : 0 ?>-<?= count($users) > 0 ? min($offset + count($users), $total_users) : 0 ?> of <?= $total_users ?? 0 ?> users</strong>
    </div>
    <div class="center">
        <form action="" method="get" id="filter-form" style="display: contents;">
            <input type="hidden" name="view" value="grid">
            <!-- Preserve current page when sorting -->
            <input type="hidden" name="page" value="<?= $page ?>">
            <?php if (!empty($search_term)): ?>
                <input type="hidden" name="q" value="<?= h($search_term) ?>">
            <?php endif; ?>
            <?php if (!empty($status_filter)): ?>
                <input type="hidden" name="status" value="<?= h($status_filter) ?>">
            <?php endif; ?>
            <?php if (!empty($role_filter)): ?>
                <input type="hidden" name="role" value="<?= h($role_filter) ?>">
            <?php endif; ?>
            <select name="sort" onchange="this.form.submit()">
                <option value="name" <?= $sort_option === 'name' ? 'selected' : '' ?>>Sort by Name</option>
                <option value="role" <?= $sort_option === 'role' ? 'selected' : '' ?>>Sort by Role</option>
                <option value="login" <?= $sort_option === 'login' ? 'selected' : '' ?>>Sort by Last Login</option>
                <option value="joined" <?= $sort_option === 'joined' ? 'selected' : '' ?>>Sort by Join Date</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active (<?= number_format($stats['active']) ?>)</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>
            <select name="role" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="Owner"   <?= $role_filter === 'Owner'   ? 'selected' : '' ?>>Owner</option>
                <option value="Admin"   <?= $role_filter === 'Admin'   ? 'selected' : '' ?>>Admin   (<?= number_format($stats['admins']) ?>)</option>
                <option value="Creator" <?= $role_filter === 'Creator' ? 'selected' : '' ?>>Creator (<?= number_format($stats['creators']) ?>)</option>
                <option value="Member"  <?= $role_filter === 'Member'  ? 'selected' : '' ?>>Member  (<?= number_format($stats['members']) ?>)</option>
                <option value="Viewer"  <?= $role_filter === 'Viewer'  ? 'selected' : '' ?>>Viewer  (<?= number_format($stats['viewers']) ?>)</option>
            </select>
            <select name="country" onchange="this.form.submit()">
                <option value="">All Countries</option>
                <option value="US" <?= $country_filter === 'US' ? 'selected' : '' ?>>🇺🇸 United States</option>
                <option value="CA" <?= $country_filter === 'CA' ? 'selected' : '' ?>>🇨🇦 Canada</option>
            </select>
        </form>
    </div>
    <div class="right toggle-buttons">
        <a href="?view=grid" class="active">Grid</a>
        <a href="?view=list" class="">List</a>
        <a href="?view=map" class="">Map</a>
    </div>
</div>
<div class="container">
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <a href="?<?= http_build_query(array_merge($_GET, ['role' => '', 'status' => '', 'page' => 1])) ?>" class="stat-card total <?= empty($role_filter) && empty($status_filter) ? 'active-filter' : '' ?>">
            <h3><?= number_format($stats['total']) ?></h3>
            <p>👥 Total Users</p>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'active', 'role' => '', 'page' => 1])) ?>" class="stat-card active <?= $status_filter === 'active' ? 'active-filter' : '' ?>">
            <h3><?= number_format($stats['active']) ?></h3>
            <p>✅ Active</p>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['role' => 'Creator', 'status' => '', 'page' => 1])) ?>" class="stat-card creators <?= $role_filter === 'Creator' ? 'active-filter' : '' ?>">
            <h3><?= number_format($stats['creators']) ?></h3>
            <p>🟠 Creators</p>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['role' => 'Admin', 'status' => '', 'page' => 1])) ?>" class="stat-card admins <?= $role_filter === 'Admin' ? 'active-filter' : '' ?>">
            <h3><?= number_format($stats['admins']) ?></h3>
            <p>🔴 Admins</p>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['role' => 'Member', 'status' => '', 'page' => 1])) ?>" class="stat-card members <?= $role_filter === 'Member' ? 'active-filter' : '' ?>">
            <h3><?= number_format($stats['members']) ?></h3>
            <p>🔵 Members</p>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['role' => 'Viewer', 'status' => '', 'page' => 1])) ?>" class="stat-card viewers <?= $role_filter === 'Viewer' ? 'active-filter' : '' ?>">
            <h3><?= number_format($stats['viewers']) ?></h3>
            <p>🟣 Viewers</p>
        </a>
    </div>
    <!-- Users Grid -->
    <div class="users-grid">
        <?php if (empty($users)): ?>
            <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 3rem; opacity: 0.7;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
                <h3>No users found</h3>
                <p>There are no users to display with the current filters.</p>
                <p><small>Debug: Query returned <?= count($users) ?> users out of <?= $total_users ?> total</small></p>
                <?php if (!empty($search_term) || !empty($status_filter) || !empty($role_filter)): ?>
                    <p><a href="?" style="color: #3b82f6;">Clear all filters</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php include 'ioneers-user-cards.php'; ?>
        <?php endif; ?>
    </div>
   
    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 2rem 0; padding: 1rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
            <!-- Previous Page -->
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                   class="pagination-btn"
                   style="padding: 8px 12px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; color: white; text-decoration: none; transition: all 0.2s;">
                    ← Previous
                </a>
            <?php endif; ?>be l
           
            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
           
            if ($start_page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                   class="pagination-btn"
                   style="padding: 8px 12px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; color: white; text-decoration: none;">1</a>
                <?php if ($start_page > 2): ?>
                    <span style="color: rgba(255, 255, 255, 0.5);">...</span>
                <?php endif; ?>
            <?php endif; ?>
           
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="pagination-current"
                          style="padding: 8px 12px; background: rgba(59, 130, 246, 0.8); border: 1px solid rgba(59, 130, 246, 1); border-radius: 6px; color: white; font-weight: bold;">
                        <?= $i ?>
                    </span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                       class="pagination-btn"
                       style="padding: 8px 12px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; color: white; text-decoration: none; transition: all 0.2s;">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
           
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span style="color: rgba(255, 255, 255, 0.5);">...</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"
                   class="pagination-btn"
                   style="padding: 8px 12px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; color: white; text-decoration: none;">
                    <?= $total_pages ?>
                </a>
            <?php endif; ?>
           
            <!-- Next Page -->
            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                   class="pagination-btn"
                   style="padding: 8px 12px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; color: white; text-decoration: none; transition: all 0.2s;">
                    Next →
                </a>
            <?php endif; ?>
           
            <!-- Page Info -->
            <div style="margin-left: 16px; color: rgba(255, 255, 255, 0.7); font-size: 14px;">
                Page <?= $page ?> of <?= $total_pages ?>
            </div>
        </div>
    <?php endif; ?>
   
    <!-- Show search/filter results info -->
    <?php if (!empty($search_term) || !empty($status_filter) || !empty($role_filter) || !empty($country_filter)): ?>
        <div class="search-results-info" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 12px; margin: 1rem 0; color: var(--text-primary); font-size: 14px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <span>
                    Found <?= $total_users ?> user(s) (showing <?= count($users) ?> on this page)
                    <?php if (!empty($search_term)): ?>
                        matching "<strong><?= h($search_term) ?></strong>"
                    <?php endif; ?>
                    <?php if (!empty($status_filter) || !empty($role_filter) || !empty($country_filter)): ?>
                        with applied filters
                    <?php endif; ?>
                </span>
                <a href="ioneers.php" style="margin-left: auto; color: inherit; text-decoration: underline; font-size: 13px;">Clear all filters</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Debug Info -->
    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #e2e8f0; font-family: monospace; font-size: 14px;">
        🔍 You're logged in as <?= h($user_email) ?> | Role: <strong style="color: #10b981;"><?= h($user_role) ?></strong> | User ID: <?= h($user_unique_id) ?>
        <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
            | <span style="color: #10b981;">✅ Can modify roles/status</span>
        <?php else: ?>
            | <span style="color: #ef4444;">❌ Cannot modify roles/status</span>
        <?php endif; ?>
    </div>

</div>
<!-- User Videos Modal -->
<div id="userVideosModal" class="user-videos-modal">
    <div class="user-videos-modal-content">
        <div class="user-videos-modal-header">
            <div class="user-videos-modal-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <path d="M22 4L12 14.01l-3-3"></path>
                </svg>
                <span id="userVideosModalTitle">User Videos</span>
            </div>
            <button class="user-videos-modal-close" onclick="closeUserVideosModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="user-videos-modal-body" id="userVideosModalBody">
            <!-- Video list will be loaded here -->
        </div>
    </div>
</div>
<!-- Debug Info -->
<?php if (isset($_GET['debug'])): ?>
<div style="background: rgba(255,255,255,0.1); padding: 1rem; margin: 1rem; border-radius: 8px;">
    <h4>Debug Information:</h4>
    <p><strong>Total users found:</strong> <?= count($users) ?></p>
    <p><strong>Database connection:</strong> <?= $db ? 'OK' : 'FAILED' ?></p>
    <p><strong>Last DB error:</strong> <?= $db->last_error ?: 'None' ?></p>
    <p><strong>Stats totals:</strong> <?= $stats['total'] ?></p>
                <?php if (!empty($users)): ?>
                <p><strong>First user:</strong> <?= $users[0]->email ?? 'N/A' ?> (<?= $users[0]->user_role ?? 'N/A' ?>)</p>
            <?php else: ?>
                <p><strong>Troubleshooting:</strong></p>
                <p>• Database class: <?= get_class($db) ?></p>
                <p>• Connection test: <?php
                    $test = $db->get_var("SELECT 1");
                    echo $test === '1' ? '✅ Connected' : '❌ Failed';
                ?></p>
                <p>• Table exists: <?php
                    $table_exists = $db->get_var("SHOW TABLES LIKE 'IONEERS'");
                    echo $table_exists ? '✅ Yes' : '❌ No';
                ?></p>
                <p>• Simple count: <?php
                    $simple_count = $db->get_var("SELECT COUNT(*) FROM IONEERS");
                    echo $simple_count ?: 'Failed';
                ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
<script>
// Store current user's role for permission checks
const CURRENT_USER_ROLE = '<?php echo h($user_role); ?>';
const CURRENT_USER_ID = '<?php echo h($user_unique_id); ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Action buttons are now informational only - no click handlers needed
   
    // Test filter dropdowns
    const filterSelects = document.querySelectorAll('#filter-form select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            console.log('Filter changed:', this.name, '=', this.value);
        });
    });
   
    // Add user button handler
    const addUserBtn = document.getElementById('add-user-btn');
    if (addUserBtn) {
        addUserBtn.addEventListener('click', openAddUserDialog);
    }
   
    // Filter and view controls
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
   
    // Store all users for filtering
    window.allUsers = Array.from(document.querySelectorAll('.user-card'));
    
    // Initialize AJAX search functionality
    initAjaxSearch();
});

// AJAX Search Functionality
function initAjaxSearch() {
    const searchInput = document.getElementById('search-input');

    if (!searchInput) {
        console.log('IONEERS AJAX: search-input element not found');
        return;
    }
    console.log('IONEERS AJAX: Search input found, initializing AJAX search');
    
    let searchTimeout;
    
    // Handle form submission (prevent default form behavior)
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            performSearch();
        });
    }
    
    // Handle input changes with debouncing
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear any existing timeout
        clearTimeout(searchTimeout);
        
        // Debounce search - wait 300ms after user stops typing
        if (query.length >= 1) {
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 300);
        } else {
            // Empty query - search immediately to show all results
            performSearch();
        }
    });
    
    // Handle Enter key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimeout);
            performSearch();
        }
    });
    
    function performSearch() {
        const query = searchInput.value.trim();

        // Show loading state
        showSearchLoading();

        // Build AJAX URL with search parameters (preserve paging and filters if any)
        const base = new URL(window.location.origin + window.location.pathname);
        base.searchParams.set('ajax', '1');
        base.searchParams.set('q', query);
        const ajaxUrl = base.toString();

        // Make AJAX request
        fetch(ajaxUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text();
            }
        })
        .then(data => {
            if (typeof data === 'object' && data.success) {
                // Handle JSON response
                updateSearchResults(data);
            } else {
                // Handle HTML response (fallback)
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                
                // Find the users grid container
                const usersGrid = document.querySelector('.users-grid');
                const newUsersGrid = doc.querySelector('.users-grid');
                
                if (usersGrid && newUsersGrid) {
                    usersGrid.innerHTML = newUsersGrid.innerHTML;
                }
                
                // Update result count
                const resultCount = document.querySelector('.left strong');
                const newResultCount = doc.querySelector('.left strong');
                
                if (resultCount && newResultCount) {
                    resultCount.textContent = newResultCount.textContent;
                }
            }
            
            hideSearchLoading();
        })
        .catch(error => {
            console.error('IONEERS AJAX: Search error:', error);
            hideSearchLoading();
            // Do not reload the page; keep UX stable and allow retry
        });
    }
    
    function showSearchLoading() {
        const container = document.querySelector('.users-grid');
        if (container) {
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';
        }
    }
    
    function hideSearchLoading() {
        const container = document.querySelector('.users-grid');
        if (container) {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        }
    }
    
    // Function to update search results from JSON data
    function updateSearchResults(data) {
        console.log('Updating search results with JSON data:', data);
        
        // Update result count
        const resultCount = document.querySelector('.left strong');
        if (resultCount) {
            resultCount.textContent = `Showing ${data.showing_start}-${data.showing_end} of ${data.total} users`;
        }
        
        // Update the main content area
        const usersGrid = document.querySelector('.users-grid');
        if (usersGrid && data.users) {
            if (data.users.length === 0) {
                // No results
                usersGrid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 3rem; opacity: 0.7;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
                        <h3>No users found</h3>
                        <p>There are no users to display with the current filters.</p>
                        <p><small>Debug: Query returned ${data.users.length} users out of ${data.total} total</small></p>
                        ${data.search_term ? `<p><a href="?" style="color: #3b82f6;">Clear all filters</a></p>` : ''}
                    </div>
                `;
            } else {
                // Generate results HTML
                let resultsHTML = '';
                data.users.forEach(user => {
                    if (user && typeof user === 'object') {
                        resultsHTML += generateUserCardHTML(user);
                    }
                });
                usersGrid.innerHTML = resultsHTML;
            }
        }
        
        // Update search results info
        updateSearchResultsInfo(data);
        
        // Update pagination
        updatePagination(data);
    }
    
    function generateUserCardHTML(user) {
        const roleClass = (user.user_role || 'guest').toLowerCase();
        const statusClass = (user.status || 'active').toLowerCase();
        const fullname = user.fullname || user.email || 'Unknown User';
        const avatarUrl = user.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(fullname.substring(0, 2))}&background=6366f1&color=fff&rounded=false&size=80`;
        
        // Format dates
        const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString('en-US', { 
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true 
        }) : 'Never';
        const joined = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { 
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true 
        }) : 'Unknown';
        
        // OAuth icons
        const oauthIcons = [];
        if (user.google_id) oauthIcons.push('<div class="social-icon google" title="Connected via Google"><img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/google.svg" alt="Google"></div>');
        if (user.discord_user_id) oauthIcons.push('<div class="social-icon discord" title="Connected via Discord"><img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/discord.svg" alt="Discord"></div>');
        if (user.linkedin_id) oauthIcons.push('<div class="social-icon linkedin" title="Connected via LinkedIn"><img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/linkedin.svg" alt="LinkedIn"></div>');
        if (user.meta_facebook_id) oauthIcons.push('<div class="social-icon facebook" title="Connected via Facebook"><img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/facebook.svg" alt="Facebook"></div>');
        if (user.x_user_id) oauthIcons.push('<div class="social-icon x" title="Connected via X (Twitter)"><img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/x.svg" alt="X"></div>');
        
        // Action buttons based on role
        let actionButtons = '';
        switch (user.user_role) {
            case 'Owner':
                actionButtons = '<button class="action-btn btn-view">View All</button><button class="action-btn btn-edit">Add & Edit</button><button class="action-btn btn-delete">Delete Any</button><button class="action-btn btn-manage">Manage</button>';
                break;
            case 'Admin':
                actionButtons = '<button class="action-btn btn-view">View All</button><button class="action-btn btn-edit">Add & Edit</button><button class="action-btn btn-delete">Delete</button><button class="action-btn btn-manage">Manage</button>';
                break;
            case 'Member':
                actionButtons = '<button class="action-btn btn-view">View All</button><button class="action-btn btn-edit">Add & Edit Own</button><button class="action-btn btn-delete">Delete Own</button>';
                break;
            case 'Creator':
                actionButtons = '<button class="action-btn btn-view">View Own</button><button class="action-btn btn-edit">Add & Edit Own</button><button class="action-btn btn-delete">Delete Own</button>';
                break;
            case 'Viewer':
                actionButtons = '<button class="action-btn btn-view">View All</button>';
                break;
            default:
                actionButtons = '<button class="action-btn btn-view">View Only</button>';
                break;
        }
        
        // Video count
        const videoCount = user.video_count || 0;
        let videoCountClass = 'video-count--none';
        if (videoCount >= 50) videoCountClass = 'video-count--high';
        else if (videoCount >= 11) videoCountClass = 'video-count--medium';
        else if (videoCount >= 1) videoCountClass = 'video-count--low';
        
        return `
            <div class="user-card" data-role="${roleClass}" data-status="${statusClass}" data-user-id="${user.user_id || ''}" data-fullname="${fullname}" data-email="${user.email || ''}" data-profile-name="${user.profile_name || ''}" data-handle="${user.handle || ''}" data-phone="${user.phone || ''}" data-dob="${user.dob || ''}" data-location="${user.location || ''}" data-user-url="${user.user_url || ''}" data-about="${user.about || ''}" data-photo-url="${user.photo_url || ''}" data-user-role="${user.user_role || 'Guest'}" data-status="${statusClass}" onclick="openEditUserDialogFromCard(this)" style="cursor: pointer;">
                <div class="user-card__content">
                    <div class="user-header">
                        <img src="${avatarUrl}" alt="${fullname}" class="user-avatar-large">
                        <div class="user-info">
                            <h3>${fullname}</h3>
                            <div class="role-status-row">
                                <div class="role-dropdown-container">
                                    <div class="user-role role-${roleClass} clickable" onclick="event.stopPropagation(); toggleRoleDropdown('${user.user_id}', '${user.user_role}')" id="role-${user.user_id}">
                                        ${user.user_role || 'Guest'}
                                        <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6,9 12,15 18,9"></polyline>
                                        </svg>
                                    </div>
                                    <div class="role-dropdown" id="role-dropdown-${user.user_id}" style="display: none;">
                                        <div class="role-option" onclick="event.stopPropagation(); changeUserRole('${user.user_id}', 'Owner')">Owner</div>
                                        <div class="role-option" onclick="event.stopPropagation(); changeUserRole('${user.user_id}', 'Admin')">Admin</div>
                                        <div class="role-option" onclick="event.stopPropagation(); changeUserRole('${user.user_id}', 'Member')">Member</div>
                                        <div class="role-option" onclick="event.stopPropagation(); changeUserRole('${user.user_id}', 'Viewer')">Viewer</div>
                                        <div class="role-option" onclick="event.stopPropagation(); changeUserRole('${user.user_id}', 'Creator')">Creator</div>
                                        <div class="role-option" onclick="event.stopPropagation(); changeUserRole('${user.user_id}', 'Guest')">Guest</div>
                                    </div>
                                </div>
                                <div class="status-container">
                                    <div class="user-status status-${statusClass} clickable" onclick="event.stopPropagation(); toggleStatusDropdown('${user.user_id}', '${statusClass}')" id="status-${user.user_id}">
                                        ${(user.status || 'Active').charAt(0).toUpperCase() + (user.status || 'Active').slice(1)}
                                        <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6,9 12,15 18,9"></polyline>
                                        </svg>
                                    </div>
                                    <div class="status-dropdown" id="status-dropdown-${user.user_id}" style="display: none;">
                                        <div class="status-option" onclick="event.stopPropagation(); changeUserStatus('${user.user_id}', 'active')">Active</div>
                                        <div class="status-option" onclick="event.stopPropagation(); changeUserStatus('${user.user_id}', 'inactive')">Inactive</div>
                                        <div class="status-option" onclick="event.stopPropagation(); changeUserStatus('${user.user_id}', 'blocked')">Blocked</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="details-with-oauth">
                        <div class="user-details">
                            <p><strong>📧</strong> ${user.email || ''}</p>
                            <p><strong>🕐</strong> Last login: ${lastLogin}</p>
                            <p><strong>📅</strong> Joined on: ${joined}</p>
                        </div>
                        <div class="oauth-icons-container">
                            ${oauthIcons.join('')}
                        </div>
                    </div>
                    <div class="user-actions">
                        ${actionButtons}
                    </div>
                </div>
                <div class="user-card__footer ${videoCountClass}" id="video-footer-${user.user_id}">
                    <div class="video-count__info">
                        <span class="video-count__icon">📹</span>
                        <span class="video-count__number" id="video-count-${user.user_id}">${videoCount.toLocaleString()}</span>
                        <span class="video-count__label">video${videoCount !== 1 ? 's' : ''}</span>
                    </div>
                    ${user.handle ? `<div class="profile-link-container">
                        ${(() => {
                            const profileVisibility = user.profile_visibility || 'Private';
                            let iconPath = '';
                            let iconColor = '';
                            
                            switch (profileVisibility) {
                                case 'Public':
                                    iconPath = '../assets/icons/profile-public.svg';
                                    iconColor = 'profile-public';
                                    break;
                                case 'Private':
                                    iconPath = '../assets/icons/profile-private.svg';
                                    iconColor = 'profile-private';
                                    break;
                                case 'Friends':
                                    iconPath = '../assets/icons/profile-friends.svg';
                                    iconColor = 'profile-friends';
                                    break;
                                default:
                                    iconPath = '../assets/icons/profile-private.svg';
                                    iconColor = 'profile-private';
                            }
                            
                            return `<a href="https://ions.com/@${user.handle}" 
                                       class="profile-link ${iconColor}" 
                                       target="_blank" 
                                       title="View ${fullname}'s profile (${profileVisibility})">
                                        <img src="${iconPath}" alt="Profile" class="profile-icon">
                                    </a>`;
                        })()}
                    </div>` : `<div class="profile-link-container">
                        <span style="color: red; font-size: 12px;">No handle</span>
                    </div>`}
                </div>
            </div>
        `;
    }
    
    function updateSearchResultsInfo(data) {
        const searchInfo = document.querySelector('.search-results-info');
        if (searchInfo && (data.search_term || data.filters.status || data.filters.role || data.filters.country)) {
            let infoText = `Found ${data.total} user(s) (showing ${data.users.length} on this page)`;
            
            if (data.search_term) {
                infoText += ` matching "<strong>${data.search_term}</strong>"`;
            }
            
            if (data.filters.status || data.filters.role || data.filters.country) {
                infoText += ' with applied filters';
            }
            
            searchInfo.querySelector('span').innerHTML = infoText;
        }
    }
    
    function updatePagination(data) {
        const paginationContainer = document.querySelector('.pagination');
        if (!paginationContainer || data.total_pages <= 1) {
            if (paginationContainer) {
                paginationContainer.style.display = 'none';
            }
            return;
        }
        
        paginationContainer.style.display = 'block';
        
        // Build pagination HTML
        let paginationHTML = '';
        
        // Previous button
        if (data.has_prev) {
            const prevPage = data.page - 1;
            const prevUrl = new URL(window.location);
            prevUrl.searchParams.set('page', prevPage);
            paginationHTML += `<a href="${prevUrl.pathname + prevUrl.search}" class="pagination-link">← Previous</a>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, data.page - 2);
        const endPage = Math.min(data.total_pages, data.page + 2);
        
        if (startPage > 1) {
            const firstUrl = new URL(window.location);
            firstUrl.searchParams.set('page', 1);
            paginationHTML += `<a href="${firstUrl.pathname + firstUrl.search}" class="pagination-link">1</a>`;
            if (startPage > 2) {
                paginationHTML += '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageUrl = new URL(window.location);
            pageUrl.searchParams.set('page', i);
            const activeClass = i === data.page ? ' active' : '';
            paginationHTML += `<a href="${pageUrl.pathname + pageUrl.search}" class="pagination-link${activeClass}">${i}</a>`;
        }
        
        if (endPage < data.total_pages) {
            if (endPage < data.total_pages - 1) {
                paginationHTML += '<span class="pagination-ellipsis">...</span>';
            }
            const lastUrl = new URL(window.location);
            lastUrl.searchParams.set('page', data.total_pages);
            paginationHTML += `<a href="${lastUrl.pathname + lastUrl.search}" class="pagination-link">${data.total_pages}</a>`;
        }
        
        // Next button
        if (data.has_next) {
            const nextPage = data.page + 1;
            const nextUrl = new URL(window.location);
            nextUrl.searchParams.set('page', nextPage);
            paginationHTML += `<a href="${nextUrl.pathname + nextUrl.search}" class="pagination-link">Next →</a>`;
        }
        
        paginationContainer.innerHTML = paginationHTML;
    }
    
    console.log('AJAX search initialized');
}

// User videos functionality
function showUserVideos(userId, userName, videoCount) {
    if (videoCount === 0) return;
   
    const modal = document.getElementById('userVideosModal');
    const title = document.getElementById('userVideosModalTitle');
    const body = document.getElementById('userVideosModalBody');
   
    title.textContent = `Videos by ${userName} (${videoCount})`;
    body.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="display: inline-block; width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #22c55e; animation: spin 1s ease-in-out infinite;"></div><p style="margin-top: 16px;">Loading videos...</p></div>';
   
    modal.classList.add('active');
   
    // Fetch user videos
    fetch(`get-user-videos.php?user_id=${encodeURIComponent(userId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserVideos(data.videos);
            } else {
                body.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;"><p>Error loading videos: ' + (data.error || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error fetching user videos:', error);
            body.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;"><p>Error loading videos. Please try again.</p></div>';
        });
}
function displayUserVideos(videos) {
    const body = document.getElementById('userVideosModalBody');
   
    if (videos.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 40px;"><p>No videos found for this user.</p></div>';
        return;
    }
   
    let html = '';
    videos.forEach(video => {
        const thumbnail = video.thumbnail || 'https://iblog.bz/assets/ionthumbnail.png';
        const title = video.title || 'Untitled Video';
        const dateAdded = new Date(video.date_added).toLocaleDateString();
        const status = video.status || 'unknown';
       
        html += `
            <div class="user-video-item">
                <div class="user-video-thumbnail">
                    <img src="${thumbnail}" alt="${title}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;" onerror="this.src='https://iblog.bz/assets/ionthumbnail.png';">
                </div>
                <div class="user-video-info">
                    <div class="user-video-title">${title}</div>
                    <div class="user-video-meta">
                        ${video.category || 'Uncategorized'} • ${dateAdded}
                        ${video.source ? ' • ' + video.source.charAt(0).toUpperCase() + video.source.slice(1) : ''}
                    </div>
                </div>
                <div class="user-video-status status-${status.toLowerCase().replace(/\s+/g, '-')}">${status}</div>
            </div>
        `;
    });
   
    body.innerHTML = html;
}
function closeUserVideosModal() {
    document.getElementById('userVideosModal').classList.remove('active');
}
// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('userVideosModal').classList.contains('active')) {
        closeUserVideosModal();
    }
});
// Close modal on overlay click
document.getElementById('userVideosModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUserVideosModal();
    }
});
// Status and Role dropdown functionality
function toggleStatusDropdown(userId, currentStatus) {
    const dropdown = document.getElementById('status-dropdown-' + userId);
    const trigger = document.getElementById('status-' + userId);
    // Close all other dropdowns
    document.querySelectorAll('.status-dropdown, .role-dropdown').forEach(d => {
        if (d !== dropdown) d.style.display = 'none';
    });
    document.querySelectorAll('.user-status, .user-role').forEach(t => {
        if (t !== trigger) t.classList.remove('dropdown-open');
    });
    // Toggle current dropdown
    if (dropdown.style.display === 'none' || !dropdown.style.display) {
        dropdown.style.display = 'block';
        trigger.classList.add('dropdown-open');
    } else {
        dropdown.style.display = 'none';
        trigger.classList.remove('dropdown-open');
    }
}
function toggleRoleDropdown(userId, currentRole) {
    const dropdown = document.getElementById('role-dropdown-' + userId);
    const trigger = document.getElementById('role-' + userId);
    // Close all other dropdowns
    document.querySelectorAll('.status-dropdown, .role-dropdown').forEach(d => {
        if (d !== dropdown) d.style.display = 'none';
    });
    document.querySelectorAll('.user-status, .user-role').forEach(t => {
        if (t !== trigger) t.classList.remove('dropdown-open');
    });
    // Toggle current dropdown
    if (dropdown.style.display === 'none' || !dropdown.style.display) {
        dropdown.style.display = 'block';
        trigger.classList.add('dropdown-open');
    } else {
        dropdown.style.display = 'none';
        trigger.classList.remove('dropdown-open');
    }
}
function changeUserStatus(userId, newStatus) {
    const statusElement = document.getElementById('status-' + userId);
    const dropdown = document.getElementById('status-dropdown-' + userId);
    const userCard = statusElement.closest('.user-card');
   
    // Store original values for reverting on error
    const originalHTML = statusElement.innerHTML;
    const originalClassName = statusElement.className;
   
    // Close dropdown
    dropdown.style.display = 'none';
    statusElement.classList.remove('dropdown-open');
   
    // Show loading
    statusElement.innerHTML = '⏳ Updating...';
   
    // Send request
    fetch('/app/update-user-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, status: newStatus }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const displayStatus = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            statusElement.innerHTML = `${displayStatus}<svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"></polyline></svg>`;
            statusElement.className = 'user-status clickable status-' + newStatus.toLowerCase();
            if (userCard) userCard.dataset.status = newStatus.toLowerCase();
            // Refresh page to update counts
            setTimeout(() => location.reload(), 500);
        } else {
            // Revert to original values
            statusElement.innerHTML = originalHTML;
            statusElement.className = originalClassName;
            const errorMsg = data.error || 'Unknown error';
        console.error('Status update failed:', data);
        alert('Failed to update user status: ' + errorMsg + 
              (data.debug ? '\n\nDebug info:\nRole: ' + data.debug.role + 
               '\nLength: ' + data.debug.role_length + 
               '\nEmail: ' + data.debug.email : ''));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert to original values
        statusElement.innerHTML = originalHTML;
        statusElement.className = originalClassName;
        alert('Network error updating user status: ' + error.message + '\n\nCheck browser console for details.');
    });
}
function changeUserRole(userId, newRole) {
    const roleElement = document.getElementById('role-' + userId);
    const dropdown = document.getElementById('role-dropdown-' + userId);
    const userCard = roleElement.closest('.user-card');
   
    // Store original values for reverting on error
    const originalHTML = roleElement.innerHTML;
    const originalClassName = roleElement.className;
   
    // Close dropdown
    dropdown.style.display = 'none';
    roleElement.classList.remove('dropdown-open');
   
    // Show loading
    roleElement.innerHTML = '⏳ Updating...';
   
    // Send request
    fetch('/app/update-user-role.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, role: newRole }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            roleElement.innerHTML = `${newRole}<svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"></polyline></svg>`;
            roleElement.className = 'user-role clickable role-' + newRole.toLowerCase();
            if (userCard) userCard.dataset.role = newRole.toLowerCase();
            // Refresh page to update counts
            setTimeout(() => location.reload(), 500);
        } else {
            // Revert to original values
            roleElement.innerHTML = originalHTML;
            roleElement.className = originalClassName;
            const errorMsg = data.error || 'Unknown error';
        console.error('Role update failed:', data);
        alert('Failed to update user role: ' + errorMsg + 
              (data.debug ? '\n\nDebug info:\nRole: ' + data.debug.role + 
               '\nLength: ' + data.debug.role_length + 
               '\nEmail: ' + data.debug.email : ''));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert to original values
        roleElement.innerHTML = originalHTML;
        roleElement.className = originalClassName;
        alert('Network error updating user role: ' + error.message + '\n\nCheck browser console for details.');
    });
}
// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.role-dropdown-container') && !e.target.closest('.status-container')) {
        document.querySelectorAll('.role-dropdown, .status-dropdown').forEach(d => {
            d.style.display = 'none';
        });
        document.querySelectorAll('.user-role, .user-status').forEach(t => {
            t.classList.remove('dropdown-open');
        });
    }
});
// Add spin animation for loading spinner and dropdown styles
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Add ION User Dialog Functionality
document.addEventListener('DOMContentLoaded', function() {
    const addUserBtn = document.getElementById('add-user-btn');
    const mobileAddUserBtn = document.getElementById('mobile-add-user-btn');
    
    if (addUserBtn) {
        addUserBtn.addEventListener('click', openAddUserDialog);
    }
    if (mobileAddUserBtn) {
        mobileAddUserBtn.addEventListener('click', openAddUserDialog);
    }
});

function openAddUserDialog() {
    // Calculate date range (18-100 years ago)
    const today = new Date();
    const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    const minDate = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate());
    const maxDateStr = maxDate.toISOString().split('T')[0];
    const minDateStr = minDate.toISOString().split('T')[0];
    
    // Create modal overlay
    const modal = document.createElement('div');
    modal.className = 'add-user-modal-overlay';
    modal.innerHTML = `
        <div class="add-user-modal">
            <div class="add-user-modal-header">
                <h2>Add New ION User</h2>
                <div class="header-controls">
                    <div class="role-dropdown-container">
                        <div class="user-role role-member clickable" onclick="toggleAddUserRoleDropdown()" id="add-user-role-display">
                            Creator
                            <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                        <div class="role-dropdown" id="add-user-role-dropdown" style="display: none;">
                            <div class="role-option" onclick="selectAddUserRole('Owner')">Owner</div>
                            <div class="role-option" onclick="selectAddUserRole('Admin')">Admin</div>
                            <div class="role-option" onclick="selectAddUserRole('Member')">Member</div>
                            <div class="role-option" onclick="selectAddUserRole('Viewer')">Viewer</div>
                            <div class="role-option" onclick="selectAddUserRole('Creator')">Creator</div>
                            <div class="role-option" onclick="selectAddUserRole('Guest')">Guest</div>
                        </div>
                    </div>
                    <div class="status-container">
                        <div class="user-status status-active clickable" onclick="toggleAddUserStatusDropdown()" id="add-user-status-display">
                            Active
                            <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                        <div class="status-dropdown" id="add-user-status-dropdown" style="display: none;">
                            <div class="status-option" onclick="selectAddUserStatus('active')">Active</div>
                            <div class="status-option" onclick="selectAddUserStatus('inactive')">Inactive</div>
                            <div class="status-option" onclick="selectAddUserStatus('blocked')">Blocked</div>
                        </div>
                    </div>
                </div>
                <button class="close-btn" onclick="closeAddUserDialog()">&times;</button>
            </div>
            <div class="add-user-modal-body">
                <form id="add-user-form">
                    <input type="hidden" id="user_role" name="user_role" value="Creator" required>
                    <input type="hidden" id="status" name="status" value="active" required>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="fullname">Full Name *</label>
                            <input type="text" id="fullname" name="fullname" required>
                        </div>
                        <div class="form-field">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="profile_name">Profile Name</label>
                            <input type="text" id="profile_name" name="profile_name">
                        </div>
                        <div class="form-field">
                            <label for="handle">Profile Handle</label>
                            <div class="handle-input-container">
                                <span class="handle-prefix">@</span>
                                <input type="text" id="handle" name="handle" placeholder="username" pattern="[a-zA-Z0-9._-]+" title="Only letters, numbers, dots, underscores, and hyphens allowed">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        <div class="form-field">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" placeholder="Select date" max="${maxDateStr}" min="${minDateStr}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="location">Your Location (Public)</label>
                            <div class="location-search-container">
                                <input type="text" id="locationSearch" class="form-input" placeholder="Search for your city..." autocomplete="off">
                                <div id="locationSearchResults" class="location-search-results"></div>
                            </div>
                            <input type="hidden" id="location" name="location" value="">
                        </div>
                        <div class="form-field">
                            <label for="user_url">Website URL</label>
                            <input type="url" id="user_url" name="user_url" placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="about">Bio</label>
                            <div class="bio-input-container">
                                <div class="bio-toolbar" id="bio-toolbar" style="display: none;">
                                    <button type="button" class="toolbar-btn" data-command="bold" title="Bold"><strong>B</strong></button>
                                    <button type="button" class="toolbar-btn" data-command="italic" title="Italic"><em>I</em></button>
                                    <button type="button" class="toolbar-btn" data-command="underline" title="Underline"><u>U</u></button>
                                    <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="Bullet List">•</button>
                                    <button type="button" class="toolbar-btn" data-command="insertOrderedList" title="Numbered List">1.</button>
                                    <button type="button" class="toolbar-btn" data-command="createLink" title="Insert Link">🔗</button>
                                    <button type="button" class="toolbar-btn" data-command="removeFormat" title="Clear Formatting">🗑️</button>
                                </div>
                                <div class="bio-editor-container">
                                    <div id="bio-editor" class="bio-editor" contenteditable="true" placeholder="Tell us about yourself..." data-placeholder="Tell us about yourself..."></div>
                                    <textarea id="about" name="about" style="display: none;"></textarea>
                                </div>
                                <div class="bio-edit-icon">✏️</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="photo_option">Profile Photo</label>
                            <div class="photo-option-container">
                                <div class="photo-option">
                                    <input type="radio" id="photo_url_option" name="photo_option" value="url" checked>
                                    <label for="photo_url_option">Use URL</label>
                                    <input type="url" id="photo_url" name="photo_url" placeholder="https://example.com/photo.jpg" class="photo-input">
                                </div>
                                <div class="photo-option">
                                    <input type="radio" id="photo_upload_option" name="photo_option" value="upload">
                                    <label for="photo_upload_option">Upload File</label>
                                    <input type="file" id="photo_file" name="photo_file" accept="image/*" class="photo-input" style="display: none;">
                                    <div class="file-upload-area" id="file_upload_area" style="display: none;">
                                        <div class="upload-placeholder">
                                            <span>📁</span>
                                            <p>Click to select image or drag & drop</p>
                                            <small>Supports: JPG, PNG, GIF, WebP (Max 5MB)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="add-user-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddUserDialog()">Cancel</button>
                <button type="button" class="btn-primary" onclick="submitAddUser()">Add User</button>
            </div>
        </div>
    `;
    
    // Add styles
    const modalStyles = document.createElement('style');
    modalStyles.textContent = `
        :root {
            --gold: 45 100% 51%;
            --gold-glow: 45 100% 61%;
        }
        
        .add-user-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
        }
        
        .add-user-modal {
            background: #1b1d23;
            border: 1px solid #282c34;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            height: auto;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.4),
                0 0 0 1px hsl(var(--gold)/0.22),
                0 0 30px hsl(var(--gold)/0.35),
                0 0 80px hsl(var(--gold-glow)/0.25);
            animation: modalGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes modalGlow {
            0% {
                box-shadow: 
                    0 8px 32px rgba(0, 0, 0, 0.4),
                    0 0 0 1px hsl(var(--gold)/0.22),
                    0 0 30px hsl(var(--gold)/0.35),
                    0 0 80px hsl(var(--gold-glow)/0.25);
            }
            100% {
                box-shadow: 
                    0 8px 32px rgba(0, 0, 0, 0.4),
                    0 0 0 1px hsl(var(--gold)/0.26),
                    0 0 35px hsl(var(--gold)/0.35),
                    0 0 90px hsl(var(--gold-glow)/0.30);
            }
        }
        
        .add-user-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #282c34;
        }
        
        .add-user-modal-header h2 {
            margin: 0;
            color: #fff;
            font-size: 1.5rem;
        }
        
        .add-user-modal-header .header-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .add-user-modal .close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        
        .add-user-modal .close-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .add-user-modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
            max-height: calc(90vh - 200px);
        }
        
        .add-user-modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .add-user-modal-body::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        
        .add-user-modal-body::-webkit-scrollbar-thumb {
            background: rgba(138, 105, 72, 0.5);
            border-radius: 4px;
        }
        
        .add-user-modal-body::-webkit-scrollbar-thumb:hover {
            background: rgba(138, 105, 72, 0.7);
        }
        
        .add-user-modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #282c34;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            flex-shrink: 0;
        }
        
        .add-user-modal .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .add-user-modal .form-field {
            flex: 1;
        }
        
        .add-user-modal .form-field.full-width {
            flex: 1 1 100%;
        }
        
        .add-user-modal .form-field label {
            display: block;
            margin-bottom: 0.5rem;
            color: #fff;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .add-user-modal .form-field input,
        .add-user-modal .form-field textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #282c34;
            border-radius: 8px;
            background: #2a2d35;
            color: #fff;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .add-user-modal .form-field input:focus,
        .add-user-modal .form-field textarea:focus {
            outline: none;
            border-color: #4f46e5;
        }
        
        .add-user-modal .form-field input::placeholder,
        .add-user-modal .form-field textarea::placeholder {
            color: #6b7280;
        }
        
        .add-user-modal .form-field textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        /* Handle input styling */
        .add-user-modal .handle-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .add-user-modal .handle-prefix {
            position: absolute;
            left: 0.75rem;
            color: #6b7280;
            font-size: 1rem;
            font-weight: 500;
            pointer-events: none;
            z-index: 1;
        }
        
        .add-user-modal .handle-input-container input {
            padding-left: 2rem;
        }
        
        /* Bio input styling */
        .add-user-modal .bio-input-container {
            position: relative;
        }
        
        .add-user-modal .bio-toolbar {
            display: flex;
            gap: 4px;
            padding: 8px;
            background: #2a2d35;
            border: 1px solid #282c34;
            border-radius: 8px 8px 0 0;
            border-bottom: none;
            margin-bottom: -1px;
        }
        
        .add-user-modal .toolbar-btn {
            background: #3a3d45;
            border: 1px solid #4a4d55;
            border-radius: 4px;
            padding: 6px 10px;
            color: #e6edf8;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            min-width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-user-modal .toolbar-btn:hover {
            background: #4a4d55;
            border-color: #5a5d65;
        }
        
        .add-user-modal .toolbar-btn:active {
            background: #2a2d35;
        }
        
        .add-user-modal .bio-editor-container {
            position: relative;
        }
        
        .add-user-modal .bio-editor {
            min-height: 80px;
            padding: 12px;
            border: 1px solid #282c34;
            border-radius: 0 0 8px 8px;
            background: #2a2d35;
            color: #e6edf8;
            font-size: 14px;
            line-height: 1.5;
            outline: none;
            overflow-y: auto;
            resize: vertical;
        }
        
        .add-user-modal .bio-editor:empty:before {
            content: attr(data-placeholder);
            color: #6b7280;
            pointer-events: none;
            font-style: italic;
        }
        
        .add-user-modal .bio-editor:focus:empty:before {
            display: none;
        }
        
        .add-user-modal .bio-editor:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }
        
        .add-user-modal .bio-editor strong {
            font-weight: 600;
        }
        
        .add-user-modal .bio-editor em {
            font-style: italic;
        }
        
        .add-user-modal .bio-editor u {
            text-decoration: underline;
        }
        
        .add-user-modal .bio-editor ul,
        .add-user-modal .bio-editor ol {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        .add-user-modal .bio-editor li {
            margin: 4px 0;
        }
        
        .add-user-modal .bio-editor a {
            color: #60a5fa;
            text-decoration: none;
        }
        
        .add-user-modal .bio-editor a:hover {
            text-decoration: underline;
        }
        
        .add-user-modal .bio-edit-icon {
            position: absolute;
            bottom: 0.5rem;
            right: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
            pointer-events: none;
        }
        
        .add-user-modal .photo-option-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .add-user-modal .photo-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 1px solid #282c34;
            border-radius: 8px;
            background: #2a2d35;
        }
        
        .add-user-modal .photo-option input[type="radio"] {
            width: auto;
            margin: 0;
        }
        
        .add-user-modal .photo-option label {
            margin: 0;
            font-weight: 400;
            min-width: 80px;
        }
        
        .add-user-modal .photo-input {
            flex: 1;
            margin: 0;
        }
        
        .add-user-modal .file-upload-area {
            flex: 1;
            border: 2px dashed #4f46e5;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(79, 70, 229, 0.05);
        }
        
        .add-user-modal .file-upload-area:hover {
            border-color: #6366f1;
            background: rgba(79, 70, 229, 0.1);
        }
        
        .add-user-modal .file-upload-area.dragover {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        
        .add-user-modal .upload-placeholder {
            color: #9ca3af;
        }
        
        .add-user-modal .upload-placeholder span {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .add-user-modal .upload-placeholder p {
            margin: 0.5rem 0;
            font-weight: 500;
        }
        
        .add-user-modal .upload-placeholder small {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .add-user-modal .file-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        /* Role and Status Dropdown Styling - Using same styling as user cards */
        .add-user-modal .header-controls .role-dropdown-container,
        .add-user-modal .header-controls .status-container {
            position: relative;
        }
        
        .add-user-modal .header-controls .user-role,
        .add-user-modal .header-controls .user-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            cursor: pointer;
            transition: opacity 0.2s ease;
            border: none;
            color: #fff;
        }
        
        .add-user-modal .header-controls .user-role:hover,
        .add-user-modal .header-controls .user-status:hover {
            opacity: 0.8;
        }
        
        .add-user-modal .header-controls .role-member { background: #2563eb; }
        .add-user-modal .header-controls .role-admin { background: #dc2626; }
        .add-user-modal .header-controls .role-owner { background: #7c2d12; }
        .add-user-modal .header-controls .role-creator { background: #ea580c; }
        .add-user-modal .header-controls .role-viewer { background: #7c3aed; }
        .add-user-modal .header-controls .role-guest { background: #6b7280; }
        
        .add-user-modal .header-controls .status-active { background: #10b981; }
        .add-user-modal .header-controls .status-inactive { background: #6b7280; }
        .add-user-modal .header-controls .status-blocked { background: #dc2626; }
        
        .add-user-modal .header-controls .role-dropdown,
        .add-user-modal .header-controls .status-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
            margin-top: 4px;
            display: none;
            min-width: 120px;
        }
        
        .add-user-modal .header-controls .role-option,
        .add-user-modal .header-controls .status-option {
            padding: 0.75rem;
            cursor: pointer;
            transition: background-color 0.2s;
            color: #fff;
        }
        
        .add-user-modal .header-controls .role-option:hover,
        .add-user-modal .header-controls .status-option:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .add-user-modal .header-controls .role-arrow,
        .add-user-modal .header-controls .status-arrow {
            margin-left: auto;
            transition: transform 0.2s;
        }
        
        .add-user-modal .header-controls .clickable.active .role-arrow,
        .add-user-modal .header-controls .clickable.active .status-arrow {
            transform: rotate(180deg);
        }
        
        .add-user-modal .btn-primary,
        .add-user-modal .btn-secondary {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .add-user-modal .btn-primary {
            background: #4f46e5;
            color: #fff;
        }
        
        .add-user-modal .btn-primary:hover {
            background: #4338ca;
        }
        
        .add-user-modal .btn-secondary {
            background: #6b7280;
            color: #fff;
        }
        
        .add-user-modal .btn-secondary:hover {
            background: #4b5563;
        }
        
        .add-user-modal .btn-primary:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }
        
        .add-user-modal .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .add-user-modal .success-message {
            color: #10b981;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .add-user-modal {
                width: 95%;
                max-width: none;
                margin: 1rem;
            }
            
            .add-user-modal .form-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .add-user-modal .form-field {
                flex: none;
            }
            
            .add-user-modal-body {
                padding: 1rem;
            }
            
            .add-user-modal-footer {
                padding: 1rem;
                flex-direction: column;
            }
            
            .add-user-modal .btn-primary,
            .add-user-modal .btn-secondary {
                width: 100%;
            }
            
            .add-user-modal-header .header-controls {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .add-user-modal {
                width: 98%;
                margin: 0.5rem;
            }
            
            .add-user-modal-header h2 {
                font-size: 1.25rem;
            }
            
            .add-user-modal .form-field input,
            .add-user-modal .form-field textarea {
                padding: 0.625rem;
                font-size: 0.875rem;
            }
            
            .add-user-modal .header-controls .user-role,
            .add-user-modal .header-controls .user-status {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
        }
    `;
    document.head.appendChild(modalStyles);
    
    document.body.appendChild(modal);
    
    // Setup bio editor functionality
    setupBioEditor();
    
    // Prompt before closing modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = modal.querySelector('#add-user-form');
            const bioEditor = modal.querySelector('.bio-editor');
            
            // Check if any input has content
            const hasContent = Array.from(form.elements).some(el => {
                if (el.type === 'hidden') return false;
                if (el.value && el.value.trim() !== '') {
                    // Exclude placeholder-like values
                    if (el.value === 'https://example.com' || el.value === '@_username') return false;
                    return true;
                }
                return false;
            }) || (bioEditor && bioEditor.textContent.trim() !== '');
            
            if (hasContent) {
                if (confirm('You have unsaved changes. Are you sure you want to close this dialog?')) {
                    closeAddUserDialog();
                }
            } else {
                closeAddUserDialog();
            }
        }
    });
    
    // Prompt before closing modal with Escape key
    const escapeHandler = function(e) {
        if (e.key === 'Escape' && document.querySelector('.add-user-modal-overlay')) {
            e.preventDefault();
            e.stopPropagation();
            
            const modal = document.querySelector('.add-user-modal-overlay');
            const form = modal.querySelector('#add-user-form');
            const bioEditor = modal.querySelector('.bio-editor');
            
            // Check if any input has content
            const hasContent = Array.from(form.elements).some(el => {
                if (el.type === 'hidden') return false;
                if (el.value && el.value.trim() !== '') {
                    // Exclude placeholder-like values
                    if (el.value === 'https://example.com' || el.value === '@_username') return false;
                    return true;
                }
                return false;
            }) || (bioEditor && bioEditor.textContent.trim() !== '');
            
            if (hasContent) {
                if (confirm('You have unsaved changes. Are you sure you want to close this dialog?')) {
                    closeAddUserDialog();
                }
            } else {
                closeAddUserDialog();
            }
        }
    };
    document.addEventListener('keydown', escapeHandler);
    
    // Setup photo upload functionality
    setupPhotoUpload();
    // Setup dropdown functionality
    setupAddUserDropdowns();
    
    // Initialize location selector for Add User dialog
    if (typeof IONLocationSelector !== 'undefined') {
        window.addUserLocationSelector = new IONLocationSelector('locationSearch', 'locationSearchResults', 'location');
    }
}

function closeAddUserDialog() {
    const modal = document.querySelector('.add-user-modal-overlay');
    if (modal) {
        modal.remove();
    }
}

function setupPhotoUpload() {
    const urlOption = document.getElementById('photo_url_option');
    const uploadOption = document.getElementById('photo_upload_option');
    const urlInput = document.getElementById('photo_url');
    const fileInput = document.getElementById('photo_file');
    const uploadArea = document.getElementById('file_upload_area');
    
    // Handle radio button changes
    urlOption.addEventListener('change', function() {
        if (this.checked) {
            urlInput.style.display = 'block';
            uploadArea.style.display = 'none';
            fileInput.style.display = 'none';
        }
    });
    
    uploadOption.addEventListener('change', function() {
        if (this.checked) {
            urlInput.style.display = 'none';
            uploadArea.style.display = 'block';
            fileInput.style.display = 'none';
        }
    });
    
    // Handle file upload area clicks
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Handle file selection
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            handleFileSelection(file);
        }
    });
    
    // Handle drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelection(file);
        }
    });
}

function handleFileSelection(file) {
    // Validate file type
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showMessage('Please select a valid image file (JPG, PNG, GIF, WebP)', 'error');
        return;
    }
    
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        showMessage('File size must be less than 5MB', 'error');
        return;
    }
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const uploadArea = document.getElementById('file_upload_area');
        uploadArea.innerHTML = `
            <img src="${e.target.result}" alt="Preview" class="file-preview">
            <p style="margin-top: 0.5rem; color: #10b981;">✓ ${file.name}</p>
        `;
    };
    reader.readAsDataURL(file);
}

function setupAddUserDropdowns() {
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.role-dropdown-container') && !e.target.closest('.status-container')) {
            const roleDropdown = document.getElementById('add-user-role-dropdown');
            const statusDropdown = document.getElementById('add-user-status-dropdown');
            const roleDisplay = document.getElementById('add-user-role-display');
            const statusDisplay = document.getElementById('add-user-status-display');
            
            if (roleDropdown) roleDropdown.style.display = 'none';
            if (statusDropdown) statusDropdown.style.display = 'none';
            if (roleDisplay) roleDisplay.classList.remove('active');
            if (statusDisplay) statusDisplay.classList.remove('active');
        }
    });
}

function openEditUserDialog(userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status, isSelfEdit = false) {
    // Format date for HTML5 date input (convert from MM/DD/YYYY to YYYY-MM-DD if needed)
    if (dob && dob.includes('/')) {
        const parts = dob.split('/');
        if (parts.length === 3) {
            // MM/DD/YYYY -> YYYY-MM-DD
            dob = `${parts[2]}-${parts[0].padStart(2, '0')}-${parts[1].padStart(2, '0')}`;
        }
    }
    
    // Calculate date range (18-100 years ago)
    const today = new Date();
    const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    const minDate = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate());
    const maxDateStr = maxDate.toISOString().split('T')[0];
    const minDateStr = minDate.toISOString().split('T')[0];
    // Check if this is a self-edit call (first parameter is an object)
    if (typeof userId === 'object' && userId !== null) {
        // This is a self-edit call with userData object
        const userData = userId;
        isSelfEdit = fullname; // second parameter is the boolean flag
        userId = userData.user_id;
        fullname = userData.fullname;
        email = userData.email;
        profileName = userData.profile_name;
        handle = userData.handle;
        phone = userData.phone;
        dob = userData.dob;
        location = userData.location;
        userUrl = userData.user_url;
        about = userData.about;
        photoUrl = userData.photo_url;
        userRole = userData.user_role;
        status = userData.status;
    }

    // Create modal overlay
    const modal = document.createElement('div');
    modal.className = 'add-user-modal-overlay';
    
    const modalTitle = isSelfEdit ? 'Update My Profile' : 'Edit ION User';
    const roleStatusDisplay = isSelfEdit ? `
        <div class="header-controls">
            <div class="role-display">
                <span class="user-role role-${userRole.toLowerCase()}">${userRole}</span>
            </div>
            <div class="status-display">
                                <span class="user-status status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
            </div>
        </div>
    ` : `
        <div class="header-controls">
            <div class="role-dropdown-container">
                <div class="user-role role-member clickable" onclick="toggleAddUserRoleDropdown()" id="add-user-role-display">
                    ${userRole}
                    <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6,9 12,15 18,9"></polyline>
                    </svg>
                </div>
                <div class="role-dropdown" id="add-user-role-dropdown" style="display: none;">
                    <div class="role-option" onclick="selectAddUserRole('Owner')">Owner</div>
                    <div class="role-option" onclick="selectAddUserRole('Admin')">Admin</div>
                    <div class="role-option" onclick="selectAddUserRole('Member')">Member</div>
                    <div class="role-option" onclick="selectAddUserRole('Viewer')">Viewer</div>
                    <div class="role-option" onclick="selectAddUserRole('Creator')">Creator</div>
                    <div class="role-option" onclick="selectAddUserRole('Guest')">Guest</div>
                </div>
            </div>
            <div class="status-container">
                <div class="user-status status-active clickable" onclick="toggleAddUserStatusDropdown()" id="add-user-status-display">
                    ${status.charAt(0).toUpperCase() + status.slice(1)}
                    <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6,9 12,15 18,9"></polyline>
                    </svg>
                </div>
                <div class="status-dropdown" id="add-user-status-dropdown" style="display: none;">
                    <div class="status-option" onclick="selectAddUserStatus('active')">Active</div>
                    <div class="status-option" onclick="selectAddUserStatus('inactive')">Inactive</div>
                    <div class="status-option" onclick="selectAddUserStatus('blocked')">Blocked</div>
                </div>
            </div>
        </div>
    `;

    modal.innerHTML = `
        <div class="add-user-modal">
            <div class="add-user-modal-header">
                <h2>${modalTitle}</h2>
                ${roleStatusDisplay}
                <button class="close-btn" onclick="closeAddUserDialog()">&times;</button>
            </div>
            <div class="add-user-modal-body">
                <form id="add-user-form">
                    <input type="hidden" id="edit_user_id" name="edit_user_id" value="${userId}">
                    <input type="hidden" id="is_self_edit" name="is_self_edit" value="${isSelfEdit ? '1' : '0'}">
                    <input type="hidden" id="user_role" name="user_role" value="${userRole}" required>
                    <input type="hidden" id="status" name="status" value="${status}" required>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="fullname">Full Name *</label>
                            <input type="text" id="fullname" name="fullname" value="${fullname}" required>
                        </div>
                        <div class="form-field">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="${email}" required ${isSelfEdit ? 'readonly' : ''}>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="profile_name">Profile Name</label>
                            <input type="text" id="profile_name" name="profile_name" value="${profileName}">
                        </div>
                        <div class="form-field">
                            <label for="handle">Profile Handle</label>
                            <div class="handle-input-container">
                                <span class="handle-prefix">@</span>
                                <input type="text" id="handle" name="handle" value="${handle}" placeholder="username" pattern="[a-zA-Z0-9._-]+" title="Only letters, numbers, dots, underscores, and hyphens allowed">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="${phone}">
                        </div>
                        <div class="form-field">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" value="${dob}" placeholder="Select date" max="${maxDateStr}" min="${minDateStr}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="location">Your Location (Public)</label>
                            <div class="location-search-container">
                                <input type="text" id="edit_locationSearch" class="form-input" placeholder="Search for your city..." autocomplete="off" value="${location || ''}">
                                <div id="edit_locationSearchResults" class="location-search-results"></div>
                            </div>
                            <input type="hidden" id="location" name="location" value="${location || ''}">
                        </div>
                        <div class="form-field">
                            <label for="user_url">Website URL</label>
                            <input type="url" id="user_url" name="user_url" value="${userUrl}" placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field" style="flex: 2;">
                            <label for="about">Bio</label>
                            <div class="bio-input-container">
                                <div class="bio-toolbar" id="bio-toolbar" style="display: none;">
                                    <button type="button" class="toolbar-btn" data-command="bold" title="Bold"><strong>B</strong></button>
                                    <button type="button" class="toolbar-btn" data-command="italic" title="Italic"><em>I</em></button>
                                    <button type="button" class="toolbar-btn" data-command="underline" title="Underline"><u>U</u></button>
                                    <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="Bullet List">•</button>
                                    <button type="button" class="toolbar-btn" data-command="insertOrderedList" title="Numbered List">1.</button>
                                    <button type="button" class="toolbar-btn" data-command="createLink" title="Insert Link">🔗</button>
                                    <button type="button" class="edit-btn" data-command="removeFormat" title="Clear Formatting">🗑️</button>
                                </div>
                                <div class="bio-editor-container">
                                    <div id="bio-editor" class="bio-editor" contenteditable="true" placeholder="Tell us about yourself..." data-placeholder="Tell us about yourself..."></div>
                                    <textarea id="about" name="about" style="display: none;"></textarea>
                                </div>
                                <div class="bio-edit-icon">✏️</div>
                            </div>
                        </div>
                        <div class="form-field" style="flex: 1;">
                            <label for="photo_option">Profile Photo</label>
                            <div class="photo-option-container">
                                <div class="photo-option">
                                    <input type="radio" id="photo_url_option" name="photo_option" value="url" checked>
                                    <label for="photo_url_option">Use URL</label>
                                    <input type="url" id="photo_url" name="photo_url" value="${photoUrl}" placeholder="https://example.com/photo.jpg" class="photo-input">
                                </div>
                                <div class="photo-option">
                                    <input type="radio" id="photo_upload_option" name="photo_option" value="upload">
                                    <label for="photo_upload_option">Upload File</label>
                                    <input type="file" id="photo_file" name="photo_file" accept="image/*" class="photo-input" style="display: none;">
                                    <div class="file-upload-area" id="file_upload_area" style="display: none;">
                                        <div class="upload-placeholder">
                                            <span>📁</span>
                                            <p>Click to select image or drag & drop</p>
                                            <small>Supports: JPG, PNG, GIF, WebP (Max 5MB)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="add-user-modal-footer">
                ${!isSelfEdit && (CURRENT_USER_ROLE === 'Owner' || CURRENT_USER_ROLE === 'Admin') ? `
                <button type="button" class="btn-delete" onclick="confirmDeleteUser('${userId}', '${fullname.replace(/'/g, "\\'")}')">Delete User</button>
                ` : ''}
                <button type="button" class="btn-secondary" onclick="closeAddUserDialog()">Cancel</button>
                <button type="button" class="btn-primary" onclick="submitEditUser()">${isSelfEdit ? 'Update My Profile' : 'Update User'}</button>
            </div>
        </div>
    `;
    
    // Add styles
    const modalStyles = document.createElement('style');
    modalStyles.textContent = `
        :root {
            --gold: 45 100% 51%;
            --gold-glow: 45 100% 61%;
        }
        
        .add-user-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
        }
        
        .add-user-modal {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
        }
        
        .add-user-modal-header {
            background: linear-gradient(135deg, #1e1e1e 0%, #2a2a2a 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #333;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            min-height: 4rem;
        }
        
        .add-user-modal-header h2 {
            color: #fff;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .add-user-modal-header .header-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-right: 3rem; /* Add space for close button */
            flex: 1;
            justify-content: center;
        }
        
        .add-user-modal-header .close-btn {
            background: none;
            border: none;
            color: #999;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
        }
        
        .add-user-modal-header .close-btn:hover {
            background: #333;
            color: #fff;
        }
        
        .add-user-modal-body {
            padding: 2rem;
        }
        
        .add-user-modal-footer {
            padding: 1rem;
            border-top: 1px solid #333;
            border-radius: 0 0 16px 16px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background: #1e1e1e;
        }
        
        .add-user-modal .btn-primary,
        .add-user-modal .btn-secondary {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }
        
        .add-user-modal .btn-primary {
            background: linear-gradient(135deg, hsl(var(--gold)) 0%, hsl(var(--gold-glow)) 100%);
            color: #000;
        }
        
        .add-user-modal .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px hsla(var(--gold), 0.4);
        }
        
        .add-user-modal .btn-secondary {
            background: #333;
            color: #fff;
        }
        
        .add-user-modal .btn-secondary:hover {
            background: #444;
        }
        
        .add-user-modal .btn-delete {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            background: rgba(220, 38, 38, 0.2);
            color: #fecaca;
            border: 1px solid rgba(220, 38, 38, 0.3);
            margin-right: auto;
        }
        
        .add-user-modal .btn-delete:hover {
            background: rgba(220, 38, 38, 0.3);
            border-color: rgba(220, 38, 38, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .add-user-modal .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .add-user-modal .form-row.full-width {
            grid-template-columns: 1fr;
        }
        
        .add-user-modal .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .add-user-modal .form-field[style*="flex: 2"] {
            flex: 2;
        }
        
        .add-user-modal .form-field[style*="flex: 1"] {
            flex: 1;
        }
        
        .add-user-modal .form-field label {
            color: #fff;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .add-user-modal .form-field input,
        .add-user-modal .edit-btn {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 0.75rem;
            color: #fff;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }
        
        .add-user-modal .form-field input:focus,
        .add-user-modal .edit-btn:focus {
            outline: none;
            border-color: hsl(var(--gold));
            box-shadow: 0 0 0 3px hsla(var(--gold), 0.1);
        }
        
        .add-user-modal .form-field input::placeholder {
            color: #666;
        }
        
        /* Date picker styling */
        .add-user-modal .form-field input[type="date"] {
            position: relative;
            color-scheme: dark;
        }
        
        .add-user-modal .form-field input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>') no-repeat center;
            background-size: 16px 16px;
            padding: 8px;
            filter: invert(1);
        }
        
        .add-user-modal .handle-input-container {
            display: flex;
            align-items: center;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .add-user-modal .handle-prefix {
            background: #333;
            color: #999;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        
        .add-user-modal .toolbar-btn {
            background: #333;
            border: 1px solid #555;
            color: #fff;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            min-width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-user-modal .toolbar-btn:hover {
            background: #444;
            border-color: #666;
        }
        
        .add-user-modal .toolbar-btn.active {
            background: hsl(var(--gold));
            color: #000;
            border-color: hsl(var(--gold));
        }
        
        .add-user-modal .bio-editor-container {
            position: relative;
        }
        
        /* Location Search Styles */
        .add-user-modal .location-search-container {
            position: relative;
        }
        
        .add-user-modal .location-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1a1a1a;
            border: 1px solid #444;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            margin-top: -1px;
        }
        
        .add-user-modal .location-search-results.show {
            display: block;
        }
        
        .add-user-modal .location-search-result-item {
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s ease;
            border-bottom: 1px solid #2a2a2a;
        }
        
        .add-user-modal .location-search-result-item:last-child {
            border-bottom: none;
        }
        
        .add-user-modal .location-search-result-item:hover {
            background: #2a2a2a;
        }
        
        .add-user-modal .location-result-name {
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .add-user-modal .location-result-details {
            color: #999;
            font-size: 12px;
        }
        
        .add-user-modal .location-search-empty {
            padding: 16px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }
        
        .add-user-modal .bio-editor {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 0.75rem;
            min-height: 120px;
            color: #fff;
            font-size: 0.875rem;
            line-height: 1.5;
            outline: none;
            transition: all 0.2s ease;
            position: relative;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .add-user-modal .bio-editor:empty:before {
            content: attr(data-placeholder);
            color: #666;
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            pointer-events: none;
            font-style: italic;
        }
        
        .add-user-modal .bio-editor:focus:empty:before {
            color: #999;
        }
        
        .add-user-modal .bio-editor:focus {
            border-color: hsl(var(--gold));
            box-shadow: 0 0 0 3px hsla(var(--gold), 0.1);
        }
        
        .add-user-modal .bio-editor strong { color: #fff; font-weight: 600; }
        .add-user-modal .bio-editor em { color: #a8b3c0; font-style: italic; }
        .add-user-modal .bio-editor u { text-decoration: underline; }
        .add-user-modal .bio-editor ul, .add-user-modal .edit-btn ol { margin: 8px 0; padding-left: 20px; }
        .add-user-modal .bio-editor li { margin: 4px 0; }
        .add-user-modal .bio-editor a { color: #60a5fa; text-decoration: none; }
        .add-user-modal .bio-editor a:hover { text-decoration: underline; }
        .add-user-modal .bio-editor:focus:empty:before { color: #999; }
        
        .add-user-modal .bio-edit-icon {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            color: #666;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .add-user-modal .bio-edit-icon:hover {
            color: #999;
        }
        
        .add-user-modal .photo-option-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .add-user-modal .photo-option {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            min-width: 200px;
        }
        
        .add-user-modal .photo-option input[type="radio"] {
            margin: 0;
        }
        
        .add-user-modal .photo-option label {
            color: #fff;
            font-weight: 500;
            margin: 0;
        }
        
        .add-user-modal .photo-input {
            width: 100%;
            margin: 0;
        }
        
        .add-user-modal .file-upload-area {
            border: 2px dashed #555;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #2a2a2a;
        }
        
        .add-user-modal .file-upload-area:hover {
            border-color: #666;
            background: #333;
        }
        
        .add-user-modal .file-upload-area.dragover {
            border-color: hsl(var(--gold));
            background: hsla(var(--gold), 0.1);
        }
        
        .add-user-modal .upload-placeholder {
            color: #999;
        }
        
        .add-user-modal .upload-placeholder span {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .add-user-modal .upload-placeholder p {
            margin: 0.5rem 0;
            font-size: 0.875rem;
        }
        
        .add-user-modal .upload-placeholder small {
            color: #666;
            font-size: 0.75rem;
        }
        
        .add-user-modal .file-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            .add-user-modal {
                width: 95%;
                margin: 1rem;
            }
            
            .add-user-modal .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .add-user-modal-footer {
                padding: 1rem;
                flex-direction: column;
            }
            
            .add-user-modal .btn-primary,
            .add-user-modal .btn-primary {
                width: 100%;
            }
            
            .add-user-modal-header .header-controls {
                flex-direction: column;
                gap: 0.5rem;
                margin-right: 2rem;
            }
            
            .add-user-modal-header .close-btn {
                top: 0.5rem;
                right: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .add-user-modal {
                width: 98%;
                margin: 0.5rem;
            }
            
            .add-user-modal-header h2 {
                font-size: 1.25rem;
            }
            
            .add-user-modal .form-field input,
            .add-user-modal .edit-btn {
                padding: 0.625rem;
                font-size: 0.875rem;
            }
            
            .add-user-modal .header-controls .user-role,
            .add-user-modal .header-controls .user-status {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
        }
    `;
    document.head.appendChild(modalStyles);
    
    document.body.appendChild(modal);
    
    // Prompt before closing modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = modal.querySelector('#add-user-form');
            const bioEditor = modal.querySelector('.bio-editor');
            
            // Check if any input has content
            const hasContent = Array.from(form.elements).some(el => {
                if (el.type === 'hidden') return false;
                if (el.value && el.value.trim() !== '') {
                    // Exclude placeholder-like values
                    if (el.value === 'https://example.com' || el.value === '@_username') return false;
                    return true;
                }
                return false;
            }) || (bioEditor && bioEditor.textContent.trim() !== '');
            
            if (hasContent) {
                if (confirm('You have unsaved changes. Are you sure you want to close this dialog?')) {
                    closeAddUserDialog();
                }
            } else {
                closeAddUserDialog();
            }
        }
    });
    
    // Prompt before closing modal with Escape key
    const escapeHandlerEdit = function(e) {
        if (e.key === 'Escape' && document.querySelector('.add-user-modal-overlay')) {
            e.preventDefault();
            e.stopPropagation();
            
            const modal = document.querySelector('.add-user-modal-overlay');
            const form = modal.querySelector('#add-user-form');
            const bioEditor = modal.querySelector('.bio-editor');
            
            // Check if any input has content
            const hasContent = Array.from(form.elements).some(el => {
                if (el.type === 'hidden') return false;
                if (el.value && el.value.trim() !== '') {
                    // Exclude placeholder-like values
                    if (el.value === 'https://example.com' || el.value === '@_username') return false;
                    return true;
                }
                return false;
            }) || (bioEditor && bioEditor.textContent.trim() !== '');
            
            if (hasContent) {
                if (confirm('You have unsaved changes. Are you sure you want to close this dialog?')) {
                    closeAddUserDialog();
                }
            } else {
                closeAddUserDialog();
            }
        }
    };
    document.addEventListener('keydown', escapeHandlerEdit);
    
    // Setup photo upload functionality
    setupPhotoUpload();
    // Setup dropdown functionality
    setupAddUserDropdowns();
    
    // Initialize location selector for Edit User dialog
    if (typeof IONLocationSelector !== 'undefined') {
        window.editUserLocationSelector = new IONLocationSelector('edit_locationSearch', 'edit_locationSearchResults', 'location');
        // Set existing location value if present
        if (location) {
            window.editUserLocationSelector.setLocation(location);
        }
    }
    
    // Populate bio editor with existing content and setup
    setTimeout(() => {
        const bioEditor = document.getElementById('bio-editor');
        const hiddenTextarea = document.getElementById('about');
        
        // Debug: Log the values being set
        console.log('Setting bio editor content:', { about, location, bioEditor: !!bioEditor, hiddenTextarea: !!hiddenTextarea });
        
        if (bioEditor && hiddenTextarea) {
            // Set bio editor content if about exists and is not empty
            if (about && about.trim() !== '') {
                bioEditor.innerHTML = about;
                hiddenTextarea.value = about;
                console.log('Bio content set:', about);
            } else {
                // Clear any existing content and show placeholder
                bioEditor.innerHTML = '';
                hiddenTextarea.value = '';
                console.log('Bio content cleared, showing placeholder');
            }
        }
        
        // Setup bio editor functionality after content is populated
        setupBioEditor();
        
        // Double-check that content is still set after setup
        setTimeout(() => {
            if (bioEditor && about && about.trim() !== '') {
                console.log('Verifying bio content after setup:', bioEditor.innerHTML);
                if (bioEditor.innerHTML !== about) {
                    console.log('Re-setting bio content after setup');
                    bioEditor.innerHTML = about;
                }
            }
        }, 50);
    }, 100);
    
    // Set photo URL if exists
    if (photoUrl) {
        document.getElementById('photo_url').value = photoUrl;
    }
    
    // Debug: Log all field values being set
    console.log('Edit dialog field values:', {
        userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status
    });
    

}

function openEditUserDialogFromCard(cardElement) {
    // Extract data from the card's data attributes
    const userId = cardElement.dataset.userId;
    const fullname = cardElement.dataset.fullname;
    const email = cardElement.dataset.email;
    const profileName = cardElement.dataset.profileName;
    const handle = cardElement.dataset.handle;
    const phone = cardElement.dataset.phone;
    const dob = cardElement.dataset.dob;
    const location = cardElement.dataset.location;
    const userUrl = cardElement.dataset.userUrl;
    const about = cardElement.dataset.about;
    const photoUrl = cardElement.dataset.photoUrl;
    const userRole = cardElement.dataset.userRole;
    const status = cardElement.dataset.status;
    
    // Debug: Log the extracted data from the card
    console.log('Card data extracted:', {
        userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status
    });
    

    
    // Call the main edit dialog function
    openEditUserDialog(userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status);
}

function toggleAddUserRoleDropdown() {
    const dropdown = document.getElementById('add-user-role-dropdown');
    const display = document.getElementById('add-user-role-display');
    const statusDropdown = document.getElementById('add-user-status-dropdown');
    const statusDisplay = document.getElementById('add-user-status-display');
    
    // Close status dropdown if open
    if (statusDropdown) statusDropdown.style.display = 'none';
    if (statusDisplay) statusDisplay.classList.remove('active');
    
    if (dropdown.style.display === 'none' || !dropdown.style.display) {
        dropdown.style.display = 'block';
        display.classList.add('active');
    } else {
        dropdown.style.display = 'none';
        display.classList.remove('active');
    }
}

function toggleAddUserStatusDropdown() {
    const dropdown = document.getElementById('add-user-status-dropdown');
    const display = document.getElementById('add-user-status-display');
    const roleDropdown = document.getElementById('add-user-role-dropdown');
    const roleDisplay = document.getElementById('add-user-role-display');
    
    // Close role dropdown if open
    if (roleDropdown) roleDropdown.style.display = 'none';
    if (roleDisplay) roleDisplay.classList.remove('active');
    
    if (dropdown.style.display === 'none' || !dropdown.style.display) {
        dropdown.style.display = 'block';
        display.classList.add('active');
    } else {
        dropdown.style.display = 'none';
        display.classList.remove('active');
    }
}

function selectAddUserRole(role) {
    const display = document.getElementById('add-user-role-display');
    const hiddenInput = document.getElementById('user_role');
    const dropdown = document.getElementById('add-user-role-dropdown');
    
    // Update display
    display.textContent = role;
    display.className = `user-role role-${role.toLowerCase()} clickable`;
    display.innerHTML = `
        ${role}
        <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6,9 12,15 18,9"></polyline>
        </svg>
    `;
    
    // Update hidden input
    hiddenInput.value = role;
    
    // Close dropdown
    dropdown.style.display = 'none';
    display.classList.remove('active');
}

function selectAddUserStatus(status) {
    const display = document.getElementById('add-user-status-display');
    const hiddenInput = document.getElementById('status');
    const dropdown = document.getElementById('add-user-status-dropdown');
    
    // Update display
    display.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    display.className = `user-status status-${status} clickable`;
    display.innerHTML = `
        ${status.charAt(0).toUpperCase() + status.slice(1)}
        <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6,9 12,15 18,9"></polyline>
        </svg>
    `;
    
    // Update hidden input
    hiddenInput.value = status;
    
    // Close dropdown
    dropdown.style.display = 'none';
    display.classList.remove('active');
}

function submitAddUser() {
    const form = document.getElementById('add-user-form');
    const submitBtn = document.querySelector('.add-user-modal-footer .btn-primary');
    const originalText = submitBtn.textContent;
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Additional validation for role and status
    const roleInput = document.getElementById('user_role');
    const statusInput = document.getElementById('status');
    
    if (!roleInput.value || roleInput.value === '') {
        showMessage('Please select a role for the user', 'error');
        return;
    }
    
    if (!statusInput.value || statusInput.value === '') {
        showMessage('Please select a status for the user', 'error');
        return;
    }
    
    // Check photo option and prepare data
    const photoOption = document.querySelector('input[name="photo_option"]:checked').value;
    
    // Ensure bio content is synced from the editor to the hidden textarea
    const bioEditor = document.getElementById('bio-editor');
    const hiddenTextarea = document.getElementById('about');
    if (bioEditor && hiddenTextarea) {
        hiddenTextarea.value = bioEditor.innerHTML;
    }
    
    const formData = new FormData(form);
    
    // Remove photo_option from FormData as it's not needed on the server
    formData.delete('photo_option');
    
    // Debug: Log what's being sent
    console.log('Form data being sent:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    // Handle photo data based on option
    if (photoOption === 'upload') {
        // photo_file is already in formData from the form
        // Just ensure photo_url is removed if it exists
        formData.delete('photo_url');
    } else {
        // photo_url is already in formData from the form
        // Just ensure photo_file is removed if it exists
        formData.delete('photo_file');
    }
    
    // Show loading state
    submitBtn.textContent = 'Adding User...';
    submitBtn.disabled = true;
    
    // Send request
    fetch('/app/add-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            showMessage('User added successfully!', 'success');
            // Close modal and refresh page
            setTimeout(() => {
                closeAddUserDialog();
                location.reload();
            }, 1500);
        } else {
            // Show error message
            showMessage(data.error || 'Failed to add user', 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error: ' + error.message, 'error');
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function submitEditUser() {
    const form = document.getElementById('add-user-form');
    const submitBtn = document.querySelector('.add-user-modal-footer .btn-primary');
    const originalText = submitBtn.textContent;
    const editUserId = document.getElementById('edit_user_id').value;
    const isSelfEdit = document.getElementById('is_self_edit').value === '1';
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // For self-edit mode, we don't need to validate role and status as they're read-only
    if (!isSelfEdit) {
        // Additional validation for role and status (admin mode only)
        const roleInput = document.getElementById('user_role');
        const statusInput = document.getElementById('status');
        
        if (!roleInput.value || roleInput.value === '') {
            showMessage('Please select a role for the user', 'error');
            return;
        }
        
        if (!statusInput.value || statusInput.value === '') {
            showMessage('Please select a status for the user', 'error');
            return;
        }
    }
    
    // Check photo option and prepare data
    const photoOption = document.querySelector('input[name="photo_option"]:checked').value;
    
    // Ensure bio content is synced from the editor to the hidden textarea
    const bioEditor = document.getElementById('bio-editor');
    const hiddenTextarea = document.getElementById('about');
    if (bioEditor && hiddenTextarea) {
        hiddenTextarea.value = bioEditor.innerHTML;
    }
    
    const formData = new FormData(form);
    
    // Remove photo_option from FormData as it's not needed on the server
    formData.delete('photo_option');
    
    // Add the edit user ID to the form data
    formData.append('edit_user_id', editUserId);
    
    // Add self-edit flag
    formData.append('is_self_edit', isSelfEdit ? '1' : '0');
    
    // Debug: Log what's being sent
    console.log('Edit form data being sent:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    // Handle photo data based on option
    if (photoOption === 'upload') {
        // photo_file is already in formData from the form
        // Just ensure photo_url is removed if it exists
        formData.delete('photo_url');
    } else {
        // photo_url is already in formData from the form
        // Just ensure photo_file is removed if it exists
        formData.delete('photo_file');
    }
    
    // Show loading state
    submitBtn.textContent = isSelfEdit ? 'Updating Profile...' : 'Updating User...';
    submitBtn.disabled = true;
    
    // Send request
    fetch('/app/edit-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            const successMsg = isSelfEdit ? 'Profile updated successfully!' : 'User updated successfully!';
            showMessage(successMsg, 'success');
            // Close modal and refresh page
            setTimeout(() => {
                closeAddUserDialog();
                location.reload();
            }, 1500);
        } else {
            // Show error message
            showMessage(data.error || 'Failed to update user', 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error: ' + error.message, 'error');
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function confirmDeleteUser(userId, fullname) {
    // Create confirmation dialog
    const confirmed = confirm(
        `⚠️ Are you sure you want to delete ${fullname} and all their content permanently?\n\n` +
        `This will:\n` +
        `• Delete their user profile\n` +
        `• Delete all their videos from IONLocalVideos\n` +
        `• Remove all associated data\n\n` +
        `This action CANNOT be undone!`
    );
    
    if (confirmed) {
        deleteUser(userId, fullname);
    }
}

function deleteUser(userId, fullname) {
    // Show loading message
    showMessage('Deleting user and all their content...', 'info');
    
    // Disable all modal buttons
    const buttons = document.querySelectorAll('.add-user-modal-footer button');
    buttons.forEach(btn => btn.disabled = true);
    
    // Send delete request
    fetch('/app/delete-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message with details
            const videoCount = data.videos_deleted || 0;
            const successMsg = `✅ User "${fullname}" has been permanently removed from the system.\n\n` +
                              `• Profile deleted\n` +
                              `• ${videoCount} video(s) deleted`;
            
            showMessage(successMsg, 'success');
            
            // Close modal and refresh page after 2 seconds
            setTimeout(() => {
                closeAddUserDialog();
                location.reload();
            }, 2000);
        } else {
            // Show error message
            showMessage('❌ ' + (data.error || 'Failed to delete user'), 'error');
            
            // Re-enable buttons
            buttons.forEach(btn => btn.disabled = false);
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
        showMessage('❌ Network error: ' + error.message, 'error');
        
        // Re-enable buttons
        buttons.forEach(btn => btn.disabled = false);
    });
}

function showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}-message`;
    messageDiv.textContent = message;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: #fff;
        font-weight: 500;
        z-index: 10001;
        animation: slideIn 0.3s ease;
    `;
    
    if (type === 'success') {
        messageDiv.style.background = '#10b981';
    } else if (type === 'info') {
        messageDiv.style.background = '#3b82f6';
    } else {
        messageDiv.style.background = '#ef4444';
    }
    
    document.body.appendChild(messageDiv);
    
    // Remove message after 5 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

function setupBioEditor() {
    const bioEditor = document.getElementById('bio-editor');
    const bioToolbar = document.getElementById('bio-toolbar');
    const hiddenTextarea = document.getElementById('about');
    

    
    if (!bioEditor || !bioToolbar || !hiddenTextarea) return;
    
    // Show toolbar on focus
    bioEditor.addEventListener('focus', function() {
        bioToolbar.style.display = 'flex';
    });
    
    // Hide toolbar on blur (with delay to allow button clicks)
    bioEditor.addEventListener('blur', function() {
        setTimeout(() => {
            if (!bioEditor.contains(document.activeElement) && !bioToolbar.contains(document.activeElement)) {
                bioToolbar.style.display = 'none';
            }
        }, 100);
    });
    
    // Handle toolbar button clicks
    bioToolbar.addEventListener('click', function(e) {
        if (e.target.classList.contains('toolbar-btn')) {
            e.preventDefault();
            const command = e.target.dataset.command;
            
            if (command === 'createLink') {
                const url = prompt('Enter URL:');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
            } else if (command === 'removeFormat') {
                document.execCommand('removeFormat', false);
            } else {
                document.execCommand(command, false);
            }
            
            // Update hidden textarea with HTML content
            updateHiddenTextarea();
            
            // Return focus to editor
            bioEditor.focus();
        }
    });
    
    // Handle content changes in the editor
    bioEditor.addEventListener('input', function() {
        updateHiddenTextarea();
    });
    
    // Handle paste events to clean HTML
    bioEditor.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = e.clipboardData.getData('text/html') || e.clipboardData.getData('text/plain');
        
        // Clean the pasted HTML
        const cleanHtml = cleanPastedHtml(text);
        document.execCommand('insertHTML', false, cleanHtml);
        
        // Update hidden textarea
        updateHiddenTextarea();
    });
    
    // Function to update the hidden textarea with HTML content
    function updateHiddenTextarea() {
        const htmlContent = bioEditor.innerHTML;
        // Only update if content is not just the placeholder
        if (htmlContent !== bioEditor.dataset.placeholder) {
            hiddenTextarea.value = htmlContent;
        }
    }
    
    // Function to clean pasted HTML
    function cleanPastedHtml(html) {
        // Create a temporary div to parse and clean HTML
        const temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Remove potentially dangerous tags and attributes
        const dangerousTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
        dangerousTags.forEach(tag => {
            const elements = temp.querySelectorAll(tag);
            elements.forEach(el => el.remove());
        });
        
        // Remove potentially dangerous attributes
        const dangerousAttrs = ['onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout', 'onfocus', 'onblur'];
        const allElements = temp.querySelectorAll('*');
        allElements.forEach(el => {
            dangerousAttrs.forEach(attr => {
                if (el.hasAttribute(attr)) {
                    el.removeAttribute(attr);
                }
            });
        });
        
        return temp.innerHTML;
    }
    
    // Note: Content is set by the calling function, don't override it here
}

// Add slide-in animation
const slideInStyle = document.createElement('style');
slideInStyle.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(slideInStyle);
</script>

<!-- Location Selector -->
<script src="/app/location-selector.js?v=<?php echo time(); ?>"></script>

</body>
</html>