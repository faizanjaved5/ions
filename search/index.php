<?php

/**
 * ION Network Search Implementation (fixed)
 * - Avoids undefined array key notices for city/state by safely building `location`
 * - Keeps original behavior otherwise
 */

// CORS: allow cross-origin AJAX requests and handle preflight
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Start session for menu authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function extractDomain($url)
{
    if (empty($url)) return 'ions.com';
    $parsed = parse_url($url);
    return isset($parsed['host']) ? $parsed['host'] : 'ions.com';
}

function getRelativeDate($date)
{
    if (empty($date)) return '';

    $timestamp = strtotime($date);
    if ($timestamp === false) return '';
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

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Initialize variables
$results = [];
$total_results = 0;
$error_message = null;

// Only search if we have a query
if (!empty($query)) {
    try {
        // Use the ION database connection
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

        foreach ($video_results as $row) {
            $row = (array)$row; // Convert object to array
            // Fix broken URLs
            if (!empty($row['link']) && preg_match('/^https?:\/\/\?(?:p|page_id)=(\d+)/', $row['link'], $matches)) {
                $row['link'] = 'https://ions.com/content/' . $matches[1];
            }

            // Build location safely. Videos typically DO NOT have city/state in this query.
            $city  = $row['city_name']  ?? '';
            $state = $row['state_name'] ?? '';
            if ($city !== '' || $state !== '') {
                $row['location'] = trim($city . (($city !== '' && $state !== '') ? ', ' : '') . $state);
            } else {
                // Fall back to channel_title or a network default
                $row['location'] = $row['channel_title'] ?? 'ION Network';
            }

            $desc = $row['description'] ?? '';
            $row['excerpt'] = ($desc !== '') ? (mb_substr(strip_tags($desc), 0, 150) . '...') : '';

            // Calculate relevance score
            $score = 0;
            $title_lower = strtolower($row['title'] ?? '');
            $query_lower = strtolower($query);

            if ($title_lower !== '' && strpos($title_lower, $query_lower) !== false) {
                $score += 100;
            }

            $words = preg_split('/\s+/', $query_lower);
            foreach ($words as $word) {
                if (strlen($word) > 2 && $title_lower !== '' && strpos($title_lower, $word) !== false) {
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

        foreach ($channel_results as $row) {
            $row = (array)$row; // Convert object to array

            $city  = $row['city_name']  ?? '';
            $state = $row['state_name'] ?? '';
            if ($city !== '' || $state !== '') {
                $row['location'] = trim($city . (($city !== '' && $state !== '') ? ', ' : '') . $state);
            } else {
                $row['location'] = 'ION Network';
            }

            $desc = $row['description'] ?? '';
            $row['excerpt'] = ($desc !== '') ? (mb_substr(strip_tags($desc), 0, 150) . '...') : '';

            // Calculate relevance score
            $score = 0;
            $title_lower = strtolower($row['title'] ?? '');
            $query_lower = strtolower($query);

            if ($title_lower !== '' && strpos($title_lower, $query_lower) !== false) {
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

        foreach ($wp_posts_results as $row) {
            $row = (array)$row;

            // Fix broken URLs
            if (!empty($row['link']) && preg_match('/^https?:\/\/\?(?:p|page_id)=(\d+)/', $row['link'], $matches)) {
                $row['link'] = 'https://ions.com/content/' . $matches[1];
            }

            $row['location'] = 'ION Network';
            $content = $row['content'] ?? '';
            $excerpt = $row['excerpt'] ?? '';
            $row['excerpt'] = $excerpt !== '' ? $excerpt : (($content !== '') ? (mb_substr(strip_tags($content), 0, 150) . '...') : '');
            $row['thumbnail'] = $row['thumbnail'] ?? 'https://iblog.bz/assets/ionthumbnail.png';

            // Calculate relevance score
            $score = 0;
            $title_lower = strtolower($row['title'] ?? '');
            $query_lower = strtolower($query);

            if ($title_lower !== '' && strpos($title_lower, $query_lower) !== false) {
                $score += 100;
            }

            $words = preg_split('/\s+/', $query_lower);
            foreach ($words as $word) {
                if (strlen($word) > 2 && $title_lower !== '' && strpos($title_lower, $word) !== false) {
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

                foreach ($wp_site_results as $row) {
                    $row = (array)$row;

                    $row['location'] = 'ION Network';
                    $content = $row['content'] ?? '';
                    $excerpt = $row['excerpt'] ?? '';
                    $row['excerpt'] = $excerpt !== '' ? $excerpt : (($content !== '') ? (mb_substr(strip_tags($content), 0, 150) . '...') : '');
                    $row['thumbnail'] = $row['thumbnail'] ?? 'https://iblog.bz/assets/ionthumbnail.png';

                    // Calculate relevance score
                    $score = 0;
                    $title_lower = strtolower($row['title'] ?? '');
                    $query_lower = strtolower($query);

                    if ($title_lower !== '' && strpos($title_lower, $query_lower) !== false) {
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
        usort($all_results, function ($a, $b) {
            return ($b['_score'] ?? 0) - ($a['_score'] ?? 0);
        });

        // Remove scores from output
        foreach ($all_results as &$result) {
            unset($result['_score']);

            // Ensure all required fields exist with new defaults
            $result['thumbnail'] = !empty($result['thumbnail']) ? $result['thumbnail'] : 'https://ions.com/assets/logos/ionlogo-thumb.png';
            $result['excerpt'] = $result['excerpt'] ?? '';
            $result['location'] = !empty($result['location']) ? $result['location'] : 'ION Network';
            $result['date'] = $result['date'] ?? null;
            $result['category'] = $result['category'] ?? 'Content';
            $result['source_domain'] = extractDomain($result['link'] ?? '');
            $result['relative_date'] = getRelativeDate($result['date']);
        }
        unset($result);

        // Apply pagination
        $total_results = count($all_results);
        $results = array_slice($all_results, $offset, $per_page);
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $error_message = "Search service temporarily unavailable. Please try again later.";
    }
}

// If this is an AJAX request, return JSON
if (isset($_GET['ajax']) || isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'query' => $query,
        'results' => $results,
        'total' => $total_results,
        'page' => $page,
        'per_page' => $per_page,
        'error' => $error_message
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($query) ? 'Search: ' . htmlspecialchars($query) . ' - ' : '' ?>ION Network Search</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="search.css" rel="stylesheet">
</head>

<body>
    <!-- ION Navbar -->
    <?php $ION_NAVBAR_BASE_URL = '/menu/'; ?>
    <?php require_once __DIR__ . '/../menu/ion-navbar-embed.php'; ?>

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
                        value="<?= htmlspecialchars($query) ?>"
                        autofocus>
                    <button type="submit" class="search-button">Search</button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <?php if (!empty($query)): ?>
            <?php if ($error_message): ?>
                <!-- Error State -->
                <div class="error-container">
                    <h2>Search Error</h2>
                    <p><?= htmlspecialchars($error_message) ?></p>
                    <a href="/" class="back-link">← Back to Home</a>
                </div>
            <?php elseif (empty($results)): ?>
                <!-- No Results -->
                <div class="no-results">
                    <svg class="no-results-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <h2>No Results Found</h2>
                    <p>We couldn't find any content matching "<?= htmlspecialchars($query) ?>". Try different keywords or browse our channels.</p>
                    <a href="/" class="back-link">← Back to Home</a>
                </div>
            <?php else: ?>
                <!-- Results Found -->
                <div class="results-header">
                    <h2>Search Results for "<?= htmlspecialchars($query) ?>"</h2>
                    <span class="results-count"><?= $total_results ?> results found</span>
                </div>

                <div class="results-grid">
                    <?php foreach ($results as $result): ?>
                        <div class="result-card">
                            <a href="<?= htmlspecialchars($result['link']) ?>"
                                class="result-link"
                                target="_blank"
                                rel="noopener">
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
                            </a>
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