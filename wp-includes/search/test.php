<?php
/**
 * ION Network Search API - Completely Independent
 * No WordPress dependencies - searches any tables that exist
 */

// Start output buffering
ob_start();

try {
    // Set headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
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
    
    // Load config
    $config_path = __DIR__ . '/../config/config.php';
    if (!file_exists($config_path)) {
        ob_clean();
        echo json_encode(['error' => 'Config file not found', 'results' => []]);
        exit;
    }
    
    $config = require $config_path;
    
    // Database connection
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Initialize results
    $all_results = [];
    $sources_searched = [];
    $search_term = '%' . $query . '%';
    
    // Get all tables in database
    $tables_stmt = $pdo->query("SHOW TABLES");
    $all_tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define search patterns for different content types
    $search_patterns = [
        // ION specific tables
        'IONLocalVideos' => [
            'fields' => ['title', 'description', 'tags', 'channel_title'],
            'select' => 'id, title, description, thumbnail, video_link, published_at, category, channel_title',
            'type' => 'video',
            'date_field' => 'published_at'
        ],
        'IONLocalNetwork' => [
            'fields' => ['city_name', 'state_name', 'channel_name', 'description'],
            'select' => 'id, city_name, state_name, country_name, channel_name, description, slug, image_path',
            'type' => 'channel',
            'date_field' => null
        ],
        // Generic patterns for any posts tables
        '_posts' => [
            'fields' => ['post_title', 'post_content', 'post_excerpt'],
            'select' => 'ID as id, post_title as title, post_content as content, post_excerpt as excerpt, post_date as date, post_type, post_status, guid',
            'where' => "post_status = 'publish'",
            'type' => 'post',
            'date_field' => 'post_date'
        ],
        // Generic patterns for content tables
        '_content' => [
            'fields' => ['title', 'content', 'description'],
            'select' => '*',
            'type' => 'content',
            'date_field' => 'created_at'
        ],
        '_articles' => [
            'fields' => ['title', 'content', 'summary'],
            'select' => '*',
            'type' => 'article',
            'date_field' => 'published_date'
        ],
        '_news' => [
            'fields' => ['headline', 'story', 'summary'],
            'select' => '*',
            'type' => 'news',
            'date_field' => 'date'
        ],
        '_events' => [
            'fields' => ['event_name', 'description', 'location'],
            'select' => '*',
            'type' => 'event',
            'date_field' => 'event_date'
        ]
    ];
    
    // Search each table
    foreach ($all_tables as $table) {
        try {
            // Skip system tables
            if (strpos($table, '_log') !== false || 
                strpos($table, '_cache') !== false ||
                strpos($table, '_temp') !== false ||
                strpos($table, '_backup') !== false) {
                continue;
            }
            
            // Get table columns
            $cols_stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $cols_stmt->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'Field');
            
            // Determine search pattern
            $pattern_used = null;
            $search_config = null;
            
            // Check exact table matches first
            if (isset($search_patterns[$table])) {
                $pattern_used = $table;
                $search_config = $search_patterns[$table];
            } else {
                // Check pattern matches
                foreach ($search_patterns as $pattern => $config) {
                    if (strpos($pattern, '_') === 0 && strpos($table, substr($pattern, 1)) !== false) {
                        $pattern_used = $pattern;
                        $search_config = $config;
                        break;
                    }
                }
            }
            
            // If no pattern matches, create generic search
            if (!$search_config) {
                $searchable_fields = [];
                foreach ($columns as $col) {
                    if (stripos($col['Type'], 'char') !== false || stripos($col['Type'], 'text') !== false) {
                        $searchable_fields[] = $col['Field'];
                    }
                }
                
                if (empty($searchable_fields)) {
                    continue;
                }
                
                $search_config = [
                    'fields' => array_slice($searchable_fields, 0, 5), // Limit to first 5 text fields
                    'select' => '*',
                    'type' => 'content',
                    'date_field' => null
                ];
            }
            
            // Build search query
            $where_conditions = [];
            foreach ($search_config['fields'] as $field) {
                if (in_array($field, $column_names)) {
                    $where_conditions[] = "`$field` LIKE :search";
                }
            }
            
            if (empty($where_conditions)) {
                continue;
            }
            
            $where_clause = implode(' OR ', $where_conditions);
            if (isset($search_config['where'])) {
                $where_clause = "($where_clause) AND {$search_config['where']}";
            }
            
            $sql = "SELECT {$search_config['select']} FROM `$table` WHERE $where_clause LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':search' => $search_term]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Format result based on table type
                $result = [
                    'id' => $row['id'] ?? $row['ID'] ?? null,
                    'title' => '',
                    'excerpt' => '',
                    'link' => '#',
                    'thumbnail' => 'https://iblog.bz/assets/ionthumbnail.png',
                    'type' => $search_config['type'],
                    'source' => $table,
                    'date' => null
                ];
                
                // Extract title
                if (isset($row['title'])) {
                    $result['title'] = $row['title'];
                } elseif (isset($row['post_title'])) {
                    $result['title'] = $row['post_title'];
                } elseif (isset($row['headline'])) {
                    $result['title'] = $row['headline'];
                } elseif (isset($row['event_name'])) {
                    $result['title'] = $row['event_name'];
                } elseif (isset($row['channel_name'])) {
                    $result['title'] = $row['channel_name'];
                } elseif (isset($row['city_name'])) {
                    $result['title'] = $row['city_name'] . ' Channel';
                }
                
                // Extract excerpt
                $content = $row['content'] ?? $row['description'] ?? $row['excerpt'] ?? $row['summary'] ?? '';
                $result['excerpt'] = substr(strip_tags($content), 0, 150) . '...';
                
                // Extract link
                if (isset($row['guid'])) {
                    $result['link'] = $row['guid'];
                } elseif (isset($row['video_link'])) {
                    $result['link'] = $row['video_link'];
                } elseif (isset($row['slug'])) {
                    $result['link'] = '/' . $row['slug'] . '/';
                } elseif (isset($row['url'])) {
                    $result['link'] = $row['url'];
                }
                
                // Extract thumbnail
                if (isset($row['thumbnail'])) {
                    $result['thumbnail'] = $row['thumbnail'];
                } elseif (isset($row['image_path'])) {
                    $result['thumbnail'] = $row['image_path'];
                } elseif (isset($row['featured_image'])) {
                    $result['thumbnail'] = $row['featured_image'];
                }
                
                // Extract date
                if ($search_config['date_field'] && isset($row[$search_config['date_field']])) {
                    $result['date'] = $row[$search_config['date_field']];
                } elseif (isset($row['date'])) {
                    $result['date'] = $row['date'];
                } elseif (isset($row['created_at'])) {
                    $result['date'] = $row['created_at'];
                }
                
                // Add additional fields based on type
                if ($table == 'IONLocalVideos') {
                    $result['category'] = $row['category'] ?? null;
                    $result['channel'] = $row['channel_title'] ?? null;
                } elseif ($table == 'IONLocalNetwork') {
                    $result['location'] = $row['city_name'] . ', ' . $row['state_name'] . ', ' . $row['country_name'];
                }
                
                // Only add if we have a title
                if (!empty($result['title'])) {
                    $all_results[] = $result;
                }
            }
            
            if ($stmt->rowCount() > 0) {
                $sources_searched[] = $table;
            }
            
        } catch (Exception $e) {
            // Skip problematic tables
            continue;
        }
    }
    
    // Sort results by relevance
    $query_lower = strtolower($query);
    foreach ($all_results as &$result) {
        $score = 0;
        $title_lower = strtolower($result['title']);
        $excerpt_lower = strtolower($result['excerpt']);
        
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
        
        // Match in excerpt = 10 points
        if (strpos($excerpt_lower, $query_lower) !== false) {
            $score += 10;
        }
        
        $result['_score'] = $score;
    }
    
    // Sort by score (highest first)
    usort($all_results, function($a, $b) {
        return $b['_score'] - $a['_score'];
    });
    
    // Remove scores from output
    foreach ($all_results as &$result) {
        unset($result['_score']);
    }
    
    // Apply limit
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
        'tables_checked' => count($all_tables)
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