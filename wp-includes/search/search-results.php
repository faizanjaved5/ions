<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - ION Network</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="search.css" rel="stylesheet">
    <?php
    // Helper functions
    function extractDomain($url) {
        if (empty($url)) return 'ions.com';
        $parsed = parse_url($url);
        return isset($parsed['host']) ? $parsed['host'] : 'ions.com';
    }

    function getRelativeDate($date) {
        if (empty($date)) return '';
        
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 86400) { // Less than 1 day
            return 'Today';
        } elseif ($diff < 172800) { // Less than 2 days
            return 'Yesterday';
        } elseif ($diff < 604800) { // Less than 1 week
            return 'This week';
        } elseif ($diff < 1209600) { // Less than 2 weeks
            return 'Last week';
        } elseif ($diff < 2629746) { // Less than 1 month
            return 'Last month';
        } else {
            return 'Over 1 month ago';
        }
    }
    ?>
</head>
<body>
    <!-- Navigation Header -->
    <nav class="nav-header">
        <div class="nav-container">
            <a href="/">
                <img src="https://iblog.bz/assets/ion-logo.png" alt="ION Network" class="logo">
            </a>
            
            <div class="nav-links">
                <a href="/local">ION LOCAL</a>
                <a href="/networks">ION NETWORKS</a>
                <a href="/initiatives">ION INITIATIVES</a>
                <a href="/connect">CONNECT.IONS</a>
            </div>
            
            <div class="nav-actions">
                <a href="/upload" class="btn btn-upload">UPLOAD</a>
                <a href="/signin" class="btn btn-signin">SIGN ION</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-container">
        <!-- Search Header -->
        <div class="search-header">
            <h1>ION Network Search</h1>
            <p>Search across all ION local channels and content</p>
        </div>
        
        <!-- Search Form -->
        <div class="search-form">
            <form method="GET" action="">
                <div class="search-input-group">
                    <input 
                        type="text" 
                        name="q" 
                        class="search-input" 
                        placeholder="Search videos, channels, content..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        autofocus
                    >
                    <button type="submit" class="search-button">Search</button>
                </div>
            </form>
        </div>
        
        <!-- Results Section -->
        <?php if (isset($_GET['q']) && !empty($_GET['q'])): ?>
            <?php
            // Get search query
            $query = trim($_GET['q']);
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $per_page = 20;
            $offset = ($page - 1) * $per_page;

            // Initialize variables
            $results = [];
            $total_results = 0;
            $error = false;

            try {
                // Load database connection
                require_once __DIR__ . '/../config/database.php';
                global $db;
                
                if (!$db->isConnected()) {
                    throw new Exception('Database connection failed');
                }
                
                $search_term = '%' . $query . '%';
                $all_results = [];
                
                // Search IONLocalVideos (check if table exists first)
                $video_table_exists = $db->get_var("SHOW TABLES LIKE 'IONLocalVideos'");
                if ($video_table_exists) {
                    $video_sql = "
                        SELECT 
                            video_id as id,
                            title,
                            description,
                            thumbnail,
                            video_link as link,
                            published_at as date,
                            category,
                            channel_title,
                            'video' as type
                        FROM IONLocalVideos
                        WHERE 
                            title LIKE %s
                            OR description LIKE %s
                            OR tags LIKE %s
                            OR channel_title LIKE %s
                            OR category LIKE %s
                        ORDER BY published_at DESC
                        LIMIT 50
                    ";
                    
                    $video_results = $db->get_results($video_sql, $search_term, $search_term, $search_term, $search_term, $search_term);
                } else {
                    $video_results = [];
                }
                
                foreach ($video_results as $result_obj) {
                    $row = (array)$result_obj;
                    // Fix broken URLs
                    if (preg_match('/^https?:\/\/\?(?:p|page_id)=(\d+)/', $row['link'], $matches)) {
                        $row['link'] = 'https://ions.com/content/' . $matches[1];
                    }
                    
                    $row['location'] = 'ION Local Network';
                    $row['excerpt'] = substr(strip_tags($row['description']), 0, 150) . '...';
                    $row['thumbnail'] = $row['thumbnail'] ?: 'https://iblog.bz/assets/ionthumbnail.png';
                    
                    // Calculate relevance score
                    $score = 0;
                    $title_lower = strtolower($row['title']);
                    $query_lower = strtolower($query);
                    
                    if (strpos($title_lower, $query_lower) !== false) {
                        $score += 100;
                    }
                    
                    $words = explode(' ', $query_lower);
                    foreach ($words as $word) {
                        if (strlen($word) > 2 && strpos($title_lower, $word) !== false) {
                            $score += 50;
                        }
                    }
                    
                    $row['_score'] = $score;
                    $all_results[] = $row;
                }
                
                // Search IONLocalNetwork channels
                $channel_sql = "
                    SELECT 
                        id,
                        channel_name as title,
                        description,
                        image_path as thumbnail,
                        CONCAT('/', slug, '/') as link,
                        city_name,
                        state_name,
                        country_name,
                        'channel' as type,
                        NULL as date,
                        'Channel' as category
                    FROM IONLocalNetwork
                    WHERE 
                        channel_name LIKE %s
                        OR city_name LIKE %s
                        OR state_name LIKE %s
                        OR description LIKE %s
                    LIMIT 20
                ";
                
                $channel_results = $db->get_results($channel_sql, $search_term, $search_term, $search_term, $search_term);
                
                foreach ($channel_results as $result_obj) {
                    $row = (array)$result_obj;
                    $row['location'] = $row['city_name'] . ', ' . $row['state_name'];
                    $row['excerpt'] = substr(strip_tags($row['description'] ?? ''), 0, 150) . '...';
                    $row['thumbnail'] = $row['thumbnail'] ?: 'https://iblog.bz/assets/ionthumbnail.png';
                    
                    // Calculate relevance score
                    $score = 0;
                    $title_lower = strtolower($row['title']);
                    $query_lower = strtolower($query);
                    
                    if (strpos($title_lower, $query_lower) !== false) {
                        $score += 100;
                    }
                    
                    $row['_score'] = $score;
                    $all_results[] = $row;
                }
                
                // Search WordPress posts tables
                $wp_posts_sql = "
                    SELECT 
                        ID as id,
                        post_title as title,
                        post_content as content,
                        post_excerpt as excerpt,
                        post_date as date,
                        post_type,
                        post_status,
                        guid as link,
                        'post' as type
                    FROM wp_posts
                    WHERE 
                        post_status = 'publish'
                        AND (
                            post_title LIKE %s
                            OR post_content LIKE %s
                            OR post_excerpt LIKE %s
                        )
                    ORDER BY post_date DESC
                    LIMIT 50
                ";
                
                $wp_posts_results = $db->get_results($wp_posts_sql, $search_term, $search_term, $search_term);
                
                foreach ($wp_posts_results as $result_obj) {
                    $row = (array)$result_obj;
                    
                    // Fix broken URLs
                    if (preg_match('/^https?:\/\/\?(?:p|page_id)=(\d+)/', $row['link'], $matches)) {
                        $row['link'] = 'https://ions.com/content/' . $matches[1];
                    }
                    
                    $row['location'] = 'ION Network';
                    $row['excerpt'] = $row['excerpt'] ?: substr(strip_tags($row['content']), 0, 150) . '...';
                    $row['thumbnail'] = 'https://iblog.bz/assets/ionthumbnail.png';
                    
                    // Calculate relevance score
                    $score = 0;
                    $title_lower = strtolower($row['title']);
                    $query_lower = strtolower($query);
                    
                    if (strpos($title_lower, $query_lower) !== false) {
                        $score += 100;
                    }
                    
                    $words = explode(' ', $query_lower);
                    foreach ($words as $word) {
                        if (strlen($word) > 2 && strpos($title_lower, $word) !== false) {
                            $score += 50;
                        }
                    }
                    
                    $row['_score'] = $score;
                    $all_results[] = $row;
                }
                
                // Search additional WordPress multisite tables
                $wp_site_tables = $db->get_col("SHOW TABLES LIKE 'wp_%_posts'");
                foreach ($wp_site_tables as $wp_table) {
                    if ($wp_table === 'wp_posts') continue; // Already searched
                    
                    try {
                        $wp_site_results = $db->get_results("
                            SELECT 
                                ID as id,
                                post_title as title,
                                post_content as content,
                                post_excerpt as excerpt,
                                post_date as date,
                                guid as link,
                                'post' as type
                            FROM `$wp_table`
                            WHERE 
                                post_status = 'publish'
                                AND (
                                    post_title LIKE %s
                                    OR post_content LIKE %s
                                    OR post_excerpt LIKE %s
                                )
                            ORDER BY post_date DESC
                            LIMIT 20
                        ", $search_term, $search_term, $search_term);
                        
                        foreach ($wp_site_results as $result_obj) {
                            $row = (array)$result_obj;
                            
                            $row['location'] = 'ION Network';
                            $row['excerpt'] = $row['excerpt'] ?: substr(strip_tags($row['content']), 0, 150) . '...';
                            $row['thumbnail'] = 'https://iblog.bz/assets/ionthumbnail.png';
                            
                            // Calculate relevance score
                            $score = 0;
                            $title_lower = strtolower($row['title']);
                            $query_lower = strtolower($query);
                            
                            if (strpos($title_lower, $query_lower) !== false) {
                                $score += 80; // Slightly lower than main tables
                            }
                            
                            $row['_score'] = $score;
                            $all_results[] = $row;
                        }
                    } catch (Exception $e) {
                        // Skip problematic tables
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
                    
                    // Ensure all required fields exist with new defaults
                    $result['thumbnail'] = $result['thumbnail'] ?: 'https://ions.com/assets/logos/ionlogo-thumb.png';
                    $result['excerpt'  ] = $result['excerpt'  ] ?: '';
                    $result['location' ] = $result['location' ] ?: 'ION Network';
                    $result['date'     ] = $result['date'     ] ?: null;
                    $result['category' ] = $result['category' ] ?: 'Content';
                    $result['source_domain'] = extractDomain($result['link'] ?? '');
                    $result['relative_date'] = getRelativeDate($result['date']);
                }
                
                // Apply pagination
                $total_results = count($all_results);
                $results = array_slice($all_results, $offset, $per_page);
                
            } catch (Exception $e) {
                error_log("Search error: " . $e->getMessage());
                $error = true;
            }
            ?>
            
            <?php if ($error): ?>
                <!-- Error State -->
                <div class="error-container">
                    <h2>Search Error</h2>
                    <p>Search service temporarily unavailable. Please try again later.</p>
                    <a href="/" class="back-link">← Back to Home</a>
                </div>
            <?php elseif (empty($results)): ?>
                <!-- No Results -->
                <div class="no-results">
                    <svg class="no-results-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <h2>No Results Found</h2>
                    <p>We couldn't find any content matching "<?= $query ?>". Try different keywords or browse our channels.</p>
                    <a href="/" class="back-link">← Back to Home</a>
                </div>
            <?php else: ?>
                <!-- Results Found -->
                <div class="results-header">
                    <h2>Search Results for "<?= $query ?>"</h2>
                    <span class="results-count"><?= count($results) ?> results found</span>
                </div>
                
                <div class="results-grid">
                    <?php foreach ($results as $result): ?>
                        <div class="result-card" onclick="window.open('<?= htmlspecialchars($result['link']) ?>', '_blank')">
                        <div class="result-thumbnail">
                                <?php if (!empty($result['thumbnail']) && $result['thumbnail'] !== 'https://ions.com/assets/logos/ionlogo-thumb.png'): ?>
                                    <img src="<?= htmlspecialchars($result['thumbnail']) ?>" 
                                         alt="<?= htmlspecialchars($result['title']) ?>"
                                         onerror="this.onerror=null; this.src='https://ions.com/assets/logos/ionlogo-thumb.png'; this.className='default-logo';">
                                <?php else: ?>
                                    <img src="https://ions.com/assets/logos/ionlogo-thumb.png" 
                                         alt="ION Network" 
                                         class="default-logo">
                                <?php endif; ?>
                                <span class="result-type"><?= htmlspecialchars($result['type']) ?></span>
                                <?php if ($result['type'] === 'video'): ?>
                            <div class="play-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"></path>
                                </svg>
                            </div>
                                <?php endif; ?>
                        </div>
                        <div class="result-content">
                                <h3 class="result-title"><?= htmlspecialchars($result['title']) ?></h3>
                                <?php if (!empty($result['excerpt'])): ?>
                                    <p class="result-description"><?= htmlspecialchars($result['excerpt']) ?></p>
                                <?php endif; ?>
                            <div class="result-meta">
                                    <span class="result-source"><?= htmlspecialchars($result['source_domain']) ?></span>
                                    <?php if (!empty($result['relative_date'])): ?>
                                        <span class="result-date"><?= htmlspecialchars($result['relative_date']) ?></span>
                                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_results > $per_page): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?= urlencode($query) ?>&page=<?= $page - 1 ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $total_pages = ceil($total_results / $per_page);
                        for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?q=<?= urlencode($query) ?>&page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?q=<?= urlencode($query) ?>&page=<?= $page + 1 ?>">Next →</a>
                        <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>