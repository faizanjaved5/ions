<?php
/**
 * ION Network Optimized Search API
 * This version only searches the most relevant tables for much faster performance
 */

// Start output buffering
ob_start();

try {
    // Set headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: max-age=300'); // Cache for 5 minutes
    
    // Get parameters
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Validate query
    if (empty($query)) {
        ob_clean();
        echo json_encode(['error' => 'No search query provided', 'results' => []]);
        exit;
    }
    
    // Load database connection
    require_once __DIR__ . '/../config/database.php';
    global $db;
    
    if (!$db->isConnected()) {
        ob_clean();
        echo json_encode(['error' => 'Database connection failed', 'results' => []]);
        exit;
    }
    
    // Initialize results
    $all_results = [];
    $sources_searched = [];
    $search_term = '%' . $query . '%';
    
    // Define only the most important tables to search
    $tables_to_search = [
        'IONLocalVideos' => [
            'type' => 'video',
            'query' => "
                SELECT 
                    video_id as id,
                    title,
                    description,
                    thumbnail,
                    video_link,
                    published_at,
                    category,
                    channel_title,
                    slug
                FROM IONLocalVideos
                WHERE 
                    title LIKE :search
                    OR description LIKE :search
                    OR tags LIKE :search
                    OR channel_title LIKE :search
                ORDER BY published_at DESC
                LIMIT 30
            "
        ],
        'IONLocalNetwork' => [
            'type' => 'channel',
            'query' => "
                SELECT 
                    id,
                    channel_name as title,
                    description,
                    image_path as thumbnail,
                    slug,
                    city_name,
                    state_name,
                    country_name
                FROM IONLocalNetwork
                WHERE 
                    channel_name LIKE :search
                    OR city_name LIKE :search
                    OR state_name LIKE :search
                    OR description LIKE :search
                LIMIT 10
            "
        ],
        'wp_posts' => [
            'type' => 'post',
            'query' => "
                SELECT 
                    ID as id,
                    post_title as title,
                    post_content as content,
                    post_excerpt as excerpt,
                    post_date as date,
                    post_type,
                    post_status,
                    guid
                FROM wp_posts
                WHERE 
                    post_status = 'publish'
                    AND (
                        post_title LIKE :search
                        OR post_content LIKE :search
                        OR post_excerpt LIKE :search
                    )
                ORDER BY post_date DESC
                LIMIT 20
            "
        ]
    ];
    
    // Search each defined table
    foreach ($tables_to_search as $table => $config) {
        try {
            // Check if table exists
            $table_exists = $db->get_var("SHOW TABLES LIKE '$table'");
            if (!$table_exists) {
                continue;
            }
            
            // Convert PDO-style placeholders to IONDatabase-style
            $query_sql = str_replace(':search', '%s', $config['query']);
            $results = $db->get_results($query_sql, $search_term);
            
            foreach ($results as $result_obj) {
                $row = (array)$result_obj;
                $result = [
                    'id' => $row['id'],
                    'title' => $row['title'] ?? '',
                    'type' => $config['type'],
                    'source' => $table,
                    'thumbnail' => 'https://iblog.bz/assets/ionthumbnail.png',
                    'excerpt' => '',
                    'link' => '#',
                    'date' => null
                ];
                
                // Process based on type
                switch ($config['type']) {
                    case 'video':
                        $result['thumbnail'] = $row['thumbnail'] ?: $result['thumbnail'];
                        $result['link'] = $row['video_link'] ?? '#';
                        $result['excerpt'] = substr(strip_tags($row['description'] ?? ''), 0, 150) . '...';
                        $result['date'] = $row['published_at'];
                        $result['category'] = $row['category'] ?? null;
                        $result['channel'] = $row['channel_title'] ?? null;
                        
                        // Fix broken URLs
                        if (preg_match('/^https?:\/\/\?(?:p|page_id)=(\d+)/', $result['link'], $matches)) {
                            $result['link'] = 'https://ions.com/content/' . $matches[1];
                        }
                        break;
                        
                    case 'channel':
                        $result['thumbnail'] = $row['thumbnail'] ?: $result['thumbnail'];
                        $result['link'] = '/' . $row['slug'] . '/';
                        $result['excerpt'] = substr(strip_tags($row['description'] ?? ''), 0, 150) . '...';
                        $result['location'] = $row['city_name'] . ', ' . $row['state_name'] . ', ' . $row['country_name'];
                        break;
                        
                    case 'post':
                        $result['link'] = $row['guid'] ?? '#';
                        $result['excerpt'] = $row['excerpt'] ?: substr(strip_tags($row['content'] ?? ''), 0, 150) . '...';
                        $result['date'] = $row['date'];
                        break;
                }
                
                // Calculate relevance score
                $score = 0;
                $title_lower = strtolower($result['title']);
                $query_lower = strtolower($query);
                
                // Exact match in title = 100 points
                if (strpos($title_lower, $query_lower) !== false) {
                    $score += 100;
                }
                
                // Each word match in title = 50 points
                $words = explode(' ', $query_lower);
                foreach ($words as $word) {
                    if (strlen($word) > 2 && strpos($title_lower, $word) !== false) {
                        $score += 50;
                    }
                }
                
                // Boost videos slightly
                if ($config['type'] === 'video') {
                    $score += 10;
                }
                
                $result['_score'] = $score;
                $all_results[] = $result;
            }
            
            if (!empty($results)) {
                $sources_searched[] = $table;
            }
            
        } catch (Exception $e) {
            // Log error but continue with other tables
            error_log("Search error for table $table: " . $e->getMessage());
            continue;
        }
    }
    
    // Check additional WordPress tables if they exist
    $wp_tables = $db->get_col("SHOW TABLES LIKE 'wp_%_posts'");
    foreach ($wp_tables as $wp_table) {
        if ($wp_table === 'wp_posts') continue; // Already searched
        
        try {
            $wp_results = $db->get_results("
                SELECT 
                    ID as id,
                    post_title as title,
                    post_content as content,
                    post_excerpt as excerpt,
                    post_date as date,
                    guid
                FROM `$wp_table`
                WHERE 
                    post_status = 'publish'
                    AND (
                        post_title LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_date DESC
                LIMIT 10
            ", $search_term, $search_term);
            
            foreach ($wp_results as $result_obj) {
                $row = (array)$result_obj;
                $result = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'excerpt' => substr(strip_tags($row['content'] ?? $row['excerpt'] ?? ''), 0, 150) . '...',
                    'link' => $row['guid'] ?? '#',
                    'thumbnail' => 'https://iblog.bz/assets/ionthumbnail.png',
                    'type' => 'post',
                    'source' => $wp_table,
                    'date' => $row['date']
                ];
                
                // Calculate score
                $score = 0;
                if (stripos($result['title'], $query) !== false) {
                    $score += 80; // Slightly lower than main tables
                }
                $result['_score'] = $score;
                
                $all_results[] = $result;
            }
            
            if (!empty($wp_results)) {
                $sources_searched[] = $wp_table;
            }
            
        } catch (Exception $e) {
            continue;
        }
    }
    
    // Sort by relevance score
    usort($all_results, function($a, $b) {
        return $b['_score'] - $a['_score'];
    });
    
    // Remove scores from output
    foreach ($all_results as &$result) {
        unset($result['_score']);
    }
    
    // Apply pagination
    $total_found = count($all_results);
    $all_results = array_slice($all_results, $offset, $limit);
    
    // Output results
    ob_clean();
    echo json_encode([
        'query' => $query,
        'results' => $all_results,
        'total' => count($all_results),
        'total_found' => $total_found,
        'offset' => $offset,
        'limit' => $limit,
        'sources_searched' => array_unique($sources_searched),
        'execution_time' => round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3) . 's'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'error' => 'Search failed',
        'message' => $e->getMessage(),
        'results' => []
    ]);
}
?>