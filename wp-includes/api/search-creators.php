<?php
/**
 * Creator Search API
 * 
 * Provides dynamic search functionality for finding creators/users
 * who have uploaded videos. Used for filtering videos by creator.
 */

session_start();

// Security check - only allow Admin/Owner access
if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_role = $_SESSION['user_role'];
if (!in_array($user_role, ['Owner', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin/Owner privileges required.']);
    exit();
}

require_once '../config/database.php';

// Initialize database connection
global $wpdb, $db;
if (!isset($wpdb)) {
    $wpdb = $db;
}

// Get search query
$search = $_GET['q'] ?? '';
$search = trim($search);

// Return empty if search query is too short
if (strlen($search) < 2) {
    echo json_encode(['creators' => []]);
    exit();
}

try {
    // Search for creators who have uploaded videos
    // Join IONEERS with IONLocalVideos to only show creators with videos
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    
    $query = "
        SELECT DISTINCT 
            i.user_id,
            i.fullname,
            i.email,
            i.handle,
            COUNT(v.id) as video_count
        FROM IONEERS i
        INNER JOIN IONLocalVideos v ON i.user_id = v.user_id
        WHERE (
            i.fullname LIKE %s 
            OR i.email LIKE %s 
            OR i.handle LIKE %s
        )
        AND v.id IS NOT NULL
        GROUP BY i.user_id, i.fullname, i.email, i.handle
        HAVING video_count > 0
        ORDER BY 
            CASE 
                WHEN i.fullname LIKE %s THEN 1
                WHEN i.email LIKE %s THEN 2
                WHEN i.handle LIKE %s THEN 3
                ELSE 4
            END,
            i.fullname ASC
        LIMIT 10
    ";
    
    $results = $wpdb->get_results($wpdb->prepare(
        $query,
        $search_like, $search_like, $search_like, // WHERE clause
        $search_like, $search_like, $search_like  // ORDER BY clause
    ));
    
    $creators = [];
    foreach ($results as $creator) {
        $display_name = $creator->fullname;
        $secondary_info = [];
        
        // Add handle if available
        if (!empty($creator->handle)) {
            $secondary_info[] = '@' . $creator->handle;
        }
        
        // Add email for additional context
        $secondary_info[] = $creator->email;
        
        // Add video count
        $secondary_info[] = $creator->video_count . ' video' . ($creator->video_count != 1 ? 's' : '');
        
        $creators[] = [
            'user_id' => $creator->user_id,
            'name' => $display_name,
            'email' => $creator->email,
            'handle' => $creator->handle,
            'video_count' => (int)$creator->video_count,
            'display_text' => $display_name,
            'secondary_text' => implode(' • ', $secondary_info)
        ];
    }
    
    echo json_encode([
        'creators' => $creators,
        'total_found' => count($creators),
        'search_query' => $search
    ]);
    
} catch (Exception $e) {
    error_log('Creator search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed. Please try again.']);
}
?>
