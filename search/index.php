<?php

/**
 * ION Network Search Implementation (fixed)
 * - Avoids undefined array key notices for city/state by safely building `location`
 * - Keeps original behavior otherwise
 * - Enhanced with Like/Dislike reactions and Share buttons
 */

// Start session for authentication checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Load Enhanced Share Manager
require_once __DIR__ . '/../share/enhanced-share-manager.php';

// Load Video Preview Helper
require_once __DIR__ . '/../includes/video-preview-helper.php';

// Initialize enhanced share manager
global $db;
$enhanced_share_manager = new EnhancedIONShareManager($db);

// Check if current viewer is logged in
$current_user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$is_viewer_logged_in = !empty($current_user_id);

// CORS: allow cross-origin AJAX requests and handle preflight
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
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

/**
 * Log search query to database for analytics
 */
function logSearch($db, $search_query, $user_id, $results_count, $sort_type, $is_creator_search, $has_keywords = false)
{
    try {
        // Get user handle if logged in
        $user_handle = null;
        if ($user_id) {
            $user_handle = $db->get_var("SELECT handle FROM IONEERS WHERE user_id = " . (int)$user_id);
        }
        
        // Get IP address (handle proxy/load balancers)
        $ip_address = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Get referer
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Get session ID
        $session_id = session_id();
        
        // Determine search type
        if ($is_creator_search) {
            $search_type = $has_keywords ? 'creator' : 'creator'; // Both use 'creator' for now
        } else {
            $search_type = 'general';
        }
        
        // Insert log
        $db->query(
            "INSERT INTO IONSearchLogs 
            (search_query, user_id, user_handle, ip_address, user_agent, results_count, sort_type, search_type, referer, session_id, search_date) 
            VALUES (%s, %d, %s, %s, %s, %d, %s, %s, %s, %s, NOW())",
            $search_query,
            $user_id ?? 0,
            $user_handle,
            $ip_address,
            $user_agent,
            $results_count,
            $sort_type,
            $search_type,
            $referer,
            $session_id
        );
        
        error_log("Search logged: '{$search_query}' by user {$user_id} ({$user_handle}) - {$results_count} results");
    } catch (Exception $e) {
        // Don't break search if logging fails
        error_log("Failed to log search: " . $e->getMessage());
    }
}

// Get search query and sort parameter
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default: newest first
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Check if searching by creator handle (@someone)
$is_creator_search = false;
$creator_handle = '';
$has_keywords = false;
$keywords_query = '';

if (!empty($query) && substr($query, 0, 1) === '@') {
    $is_creator_search = true;
    
    // Split the query to separate creator from keywords
    $parts = preg_split('/\s+/', $query, 2); // Split on first space
    $creator_handle = substr($parts[0], 1); // Remove @ symbol from first part
    
    // Check if there are additional keywords after the creator handle
    if (isset($parts[1]) && !empty(trim($parts[1]))) {
        $has_keywords = true;
        $keywords_query = trim($parts[1]);
    }
}

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

        // Convert query to individual search terms for better multi-word matching
        $query_terms = preg_split('/\s+/', trim($query));
        $search_terms = [];
        foreach ($query_terms as $term) {
            $search_terms[] = '%' . trim($term) . '%';
        }
        
        // Create a combined search term for exact phrase matching (higher relevance)
        $search_term_exact = '%' . $query . '%';
        
        $all_results = [];

        // Search IONLocalVideos (check if table exists first)
        $video_table_exists = $db->get_var("SHOW TABLES LIKE 'IONLocalVideos'");
        if ($video_table_exists) {
            // Build WHERE clause based on search type
            $where_conditions = [];
            $query_params = [];
            
            if ($is_creator_search) {
                // Search by creator handle from IONEERS table
                $creator_search_term = '%' . $creator_handle . '%';
                
                if ($has_keywords) {
                    // Creator search WITH keywords: filter by creator AND search keywords in content
                    // Creator filter (must match)
                    $where_conditions[] = "(ioneer.handle LIKE %s OR ioneer.profile_name LIKE %s OR ioneer.fullname LIKE %s)";
                    $query_params = [$creator_search_term, $creator_search_term, $creator_search_term];
                    
                    // Keyword search terms for the creator's videos
                    $keyword_terms = preg_split('/\s+/', trim($keywords_query));
                    foreach ($keyword_terms as $term) {
                        $keyword_search = '%' . trim($term) . '%';
                        $where_conditions[] = "(v.title LIKE %s OR v.description LIKE %s OR v.tags LIKE %s)";
                        $query_params = array_merge($query_params, [$keyword_search, $keyword_search, $keyword_search]);
                    }
                } else {
                    // Creator-only search: just find all videos by this creator
                    $where_conditions[] = "(ioneer.handle LIKE %s OR ioneer.profile_name LIKE %s OR ioneer.fullname LIKE %s)";
                    $query_params = [$creator_search_term, $creator_search_term, $creator_search_term];
                }
            } else {
                // Normal search - multiple terms
                foreach ($search_terms as $term) {
                    $where_conditions[] = "(v.title LIKE %s OR v.description LIKE %s OR v.tags LIKE %s OR v.channel_title LIKE %s OR v.ion_category LIKE %s OR ioneer.handle LIKE %s OR ioneer.profile_name LIKE %s)";
                    // Add 7 parameters for each term (including creator fields from IONEERS)
                    $query_params = array_merge($query_params, [$term, $term, $term, $term, $term, $term, $term]);
                }
            }
            
            // Determine sort order - use COALESCE to fallback to date_added when published_at is NULL
            $order_clause = ($sort === 'oldest') ? 'COALESCE(v.published_at, v.date_added) ASC' : 'COALESCE(v.published_at, v.date_added) DESC';
            
            $video_sql = "
                SELECT 
                    v.id,
                    v.video_id,
                    v.slug,
                    v.short_link,
                    v.title,
                    v.description,
                    v.thumbnail,
                    v.video_link as link,
                    v.video_link,
                    v.optimized_url,
                    v.hls_manifest_url,
                    COALESCE(v.published_at, v.date_added) as date,
                    v.ion_category as category,
                    v.channel_title,
                    ioneer.handle as creator_handle,
                    ioneer.profile_name as creator_name,
                    ioneer.photo_url as creator_photo,
                    v.view_count,
                    v.source,
                    v.videotype,
                    v.likes,
                    v.dislikes,
                    'video' as type
                    " . ($is_viewer_logged_in ? ",vl.action_type as user_reaction" : ",NULL as user_reaction") . "
                FROM IONLocalVideos v
                LEFT JOIN IONEERS ioneer ON v.user_id = ioneer.user_id
                " . ($is_viewer_logged_in ? "LEFT JOIN IONVideoLikes vl ON v.id = vl.video_id AND vl.user_id = " . (int)$current_user_id : "") . "
                WHERE 
                    (" . implode(' OR ', $where_conditions) . ")
                    AND (
                        (v.status = 'Approved' AND v.visibility = 'Public')
                        " . ($is_viewer_logged_in ? "OR (v.user_id = " . (int)$current_user_id . ")" : "") . "
                    )
                ORDER BY " . $order_clause . "
                LIMIT 50
            ";

            $video_results = $db->get_results($video_sql, ...$query_params);
            
            // Debug: Log creator search details
            if ($is_creator_search) {
                error_log("CREATOR SEARCH DEBUG:");
                error_log("  Query: {$query}");
                error_log("  Creator: {$creator_handle}");
                error_log("  Has keywords: " . ($has_keywords ? 'YES' : 'NO'));
                error_log("  Keywords: {$keywords_query}");
                error_log("  Results count: " . count($video_results));
                if (count($video_results) > 0) {
                    error_log("  First 3 videos:");
                    foreach (array_slice($video_results, 0, 3) as $idx => $v) {
                        error_log("    {$idx}: {$v->title} | Date: {$v->date}");
                    }
                }
            }
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

            // Calculate relevance score with advanced weighting for IONLocalVideos
            $score = 0;
            $title_lower = strtolower($row['title'] ?? '');
            $desc_lower = strtolower($desc);
            $tags_lower = strtolower($row['tags'] ?? '');
            
            // For creator-only searches, skip relevance scoring (will sort by date only)
            if ($is_creator_search && !$has_keywords) {
                $score = 0; // No relevance score - sort by date
            } else {
                // Use keywords if present, otherwise use full query
                $search_text = ($is_creator_search && $has_keywords) ? $keywords_query : $query;
                $query_lower = strtolower($search_text);

                // EXACT TITLE MATCH (highest priority) - 1000 points
                if ($title_lower === $query_lower) {
                    $score += 1000;
                }
                // Exact phrase in title - 500 points
                elseif ($title_lower !== '' && strpos($title_lower, $query_lower) !== false) {
                    $score += 500;
                }
                
                // Title starts with query - 300 points
                if ($title_lower !== '' && strpos($title_lower, $query_lower) === 0) {
                    $score += 300;
                }
                
                // Exact phrase in description - 200 points
                if ($desc_lower !== '' && strpos($desc_lower, $query_lower) !== false) {
                    $score += 200;
                }
                
                // Exact phrase in tags - 150 points
                if ($tags_lower !== '' && strpos($tags_lower, $query_lower) !== false) {
                    $score += 150;
                }

                // Individual word matching (secondary)
                $words = preg_split('/\s+/', $query_lower);
                foreach ($words as $word) {
                    if (strlen($word) > 2) {
                        // Word in title - 100 points per word
                        if ($title_lower !== '' && strpos($title_lower, $word) !== false) {
                            $score += 100;
                        }
                        // Word in description - 50 points per word
                        if ($desc_lower !== '' && strpos($desc_lower, $word) !== false) {
                            $score += 50;
                        }
                        // Word in tags - 30 points per word
                        if ($tags_lower !== '' && strpos($tags_lower, $word) !== false) {
                            $score += 30;
                        }
                    }
                }
            }

            $row['_score'] = $score;
            $all_results[] = $row;
        }

        // Search IONLocalNetwork channels
        // Build WHERE clause for channels with OR conditions for each search term
        $channel_where_conditions = [];
        $channel_query_params = [];
        
        foreach ($search_terms as $term) {
            $channel_where_conditions[] = "(channel_name LIKE %s OR city_name LIKE %s OR state_name LIKE %s OR description LIKE %s)";
            $channel_query_params = array_merge($channel_query_params, [$term, $term, $term, $term]);
        }
        
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
                " . implode(' OR ', $channel_where_conditions) . "
            LIMIT 20
        ";

        $channel_results = $db->get_results($channel_sql, ...$channel_query_params);

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

            // Calculate relevance score for channels
            $score = 0;
            $title_lower = strtolower($row['title'] ?? '');
            $desc_lower = strtolower($desc);
            $query_lower = strtolower($query);

            // Exact title match - 1000 points
            if ($title_lower === $query_lower) {
                $score += 1000;
            }
            // Exact phrase in title - 500 points
            elseif ($title_lower !== '' && strpos($title_lower, $query_lower) !== false) {
                $score += 500;
            }
            
            // Exact phrase in description - 200 points
            if ($desc_lower !== '' && strpos($desc_lower, $query_lower) !== false) {
                $score += 200;
            }
            
            // Individual words
            $words = preg_split('/\s+/', $query_lower);
            foreach ($words as $word) {
                if (strlen($word) > 2) {
                    if ($title_lower !== '' && strpos($title_lower, $word) !== false) {
                        $score += 100;
                    }
                    if ($desc_lower !== '' && strpos($desc_lower, $word) !== false) {
                        $score += 50;
                    }
                }
            }

            $row['_score'] = $score;
            $all_results[] = $row;
        }

        // Search WordPress posts tables
        // Build WHERE clause for WordPress posts with OR conditions for each search term
        $wp_where_conditions = [];
        $wp_query_params = [];
        
        foreach ($search_terms as $term) {
            $wp_where_conditions[] = "(post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s)";
            $wp_query_params = array_merge($wp_query_params, [$term, $term, $term]);
        }
        
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
                    " . implode(' OR ', $wp_where_conditions) . "
                )
            ORDER BY post_date DESC
            LIMIT 50
        ";

        $wp_posts_results = $db->get_results($wp_posts_sql, ...$wp_query_params);

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
                // Build dynamic query for multisite tables
                $multisite_where_conditions = [];
                $multisite_query_params = [];
                
                foreach ($search_terms as $term) {
                    $multisite_where_conditions[] = "(post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s)";
                    $multisite_query_params = array_merge($multisite_query_params, [$term, $term, $term]);
                }
                
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
                            " . implode(' OR ', $multisite_where_conditions) . "
                        )
                    ORDER BY post_date DESC
                    LIMIT 20
                ", ...$multisite_query_params);

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

        // Separate videos from posts/channels for proper sorting
        $videos = [];
        $posts_and_channels = [];
        
        foreach ($all_results as $result) {
            if ($result['type'] === 'video') {
                $videos[] = $result;
            } else {
                $posts_and_channels[] = $result;
            }
        }
        
        // Sort videos by RELEVANCE first, then by date
        usort($videos, function ($a, $b) use ($sort, $is_creator_search, $has_keywords) {
            // Primary sort: Relevance score (highest first)
            $score_diff = ($b['_score'] ?? 0) - ($a['_score'] ?? 0);
            
            // Debug for creator searches
            if ($is_creator_search && !$has_keywords && $score_diff === 0) {
                // Both have score 0, should sort by date
                $date_a = strtotime($a['date'] ?? '1970-01-01');
                $date_b = strtotime($b['date'] ?? '1970-01-01');
                // error_log("Comparing: {$a['title']} ({$a['date']}) vs {$b['title']} ({$b['date']})");
            }
            
            if ($score_diff !== 0) {
                return $score_diff;
            }
            
            // Secondary sort: Date (tiebreaker)
            $date_a = strtotime($a['date'] ?? '1970-01-01');
            $date_b = strtotime($b['date'] ?? '1970-01-01');
            
            if ($sort === 'oldest') {
                return $date_a - $date_b; // Oldest first
            } else {
                return $date_b - $date_a; // Newest first (default)
            }
        });
        
        // Debug: Log final order for creator searches
        if ($is_creator_search && !$has_keywords && count($videos) > 0) {
            error_log("AFTER PHP SORT - First 3 videos:");
            foreach (array_slice($videos, 0, 3) as $idx => $v) {
                error_log("  {$idx}: {$v['title']} | Score: {$v['_score']} | Date: {$v['date']}");
            }
        }
        
        // Sort posts/channels by RELEVANCE first, then by date
        usort($posts_and_channels, function ($a, $b) use ($sort) {
            // Primary sort: Relevance score (highest first)
            $score_diff = ($b['_score'] ?? 0) - ($a['_score'] ?? 0);
            if ($score_diff !== 0) {
                return $score_diff;
            }
            
            // Secondary sort: Date (tiebreaker)
            $date_a = strtotime($a['date'] ?? '1970-01-01');
            $date_b = strtotime($b['date'] ?? '1970-01-01');
            
            if ($sort === 'oldest') {
                return $date_a - $date_b; // Oldest first
            } else {
                return $date_b - $date_a; // Newest first (default)
            }
        });
        
        // Combine: Videos first, then posts/channels
        $all_results = array_merge($videos, $posts_and_channels);
        
        // Remove scores and ensure fields exist
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
        
        // Log this search for analytics
        logSearch($db, $query, $current_user_id, $total_results, $sort, $is_creator_search, $has_keywords);
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
        'sort' => $sort,
        'is_creator_search' => $is_creator_search,
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
    <title><?= !empty($query) ? 'Search: ' . htmlspecialchars($query) . ' - ' : '' ?>Search ION Network</title>
    
    <!-- Set default theme to dark -->
    <script>
        // Get theme from session, cookie, or default to 'dark'
        const theme = '<?= $_SESSION['theme'] ?? $_COOKIE['theme'] ?? 'dark' ?>';
        document.documentElement.setAttribute('data-theme', theme);
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="search.css" rel="stylesheet">
    
    <!-- Enhanced Share System -->
    <link href="/share/enhanced-ion-share.css" rel="stylesheet">
    
    <!-- Video Reactions System -->
    <link href="/app/video-reactions.css?v=<?= time() ?>" rel="stylesheet">
    <link href="/login/modal.css?v=<?= time() ?>" rel="stylesheet">
    
    <style>
    /* CRITICAL: Inline styles to ensure active states work (overrides any conflicts) */
    .reaction-btn.like-btn.active {
        color: #10b981 !important;
        background: rgba(16, 185, 129, 0.15) !important;
        border-color: rgba(16, 185, 129, 0.4) !important;
    }
    
    .reaction-btn.like-btn.active svg {
        stroke: #10b981 !important;
        fill: none !important;
    }
    
    .reaction-btn.like-btn.active .like-count {
        color: #10b981 !important;
    }
    
    .reaction-btn.dislike-btn.active {
        color: #ef4444 !important;
        background: rgba(239, 68, 68, 0.15) !important;
        border-color: rgba(239, 68, 68, 0.4) !important;
    }
    
    .reaction-btn.dislike-btn.active svg {
        stroke: #ef4444 !important;
        fill: none !important;
    }
    
    .reaction-btn.dislike-btn.active .dislike-count {
        color: #ef4444 !important;
    }
    </style>
</head>

<body<?= !empty($results) ? ' class="has-results"' : '' ?>>
    <!-- ION Navbar -->
    <?php $ION_NAVBAR_BASE_URL = '/menu/'; ?>
    <?php require_once __DIR__ . '/../menu/ion-navbar-embed.php'; ?>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Search Header -->
        <div class="search-header">
            <h1>Search <span style="color: #b28254;">ION</span> Network</h1>
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
                    <div class="results-title-row">
                        <div>
                            <h2><?= $is_creator_search ? 'Content by @' . htmlspecialchars($creator_handle) : 'Search Results for "' . htmlspecialchars($query) . '"' ?></h2>
                            <span class="results-count"><?= $total_results ?> results found</span>
                        </div>
                        <div class="sort-toggle">
                            <label for="sort-select">Sort by:</label>
                            <select id="sort-select" onchange="updateSort(this.value)">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="results-grid">
                    <?php foreach ($results as $result): 
                        // Generate video link
                        $link = '#';
                        if ($result['type'] === 'video' && !empty($result['short_link'])) {
                            $link = '/v/' . $result['short_link'];
                        } elseif (!empty($result['link'])) {
                            $link = $result['link'];
                        }
                        
                        // Get thumbnail - use ION logo for posts, gradient for videos when missing
                        $has_thumbnail = !empty($result['thumbnail']) && 
                                         $result['thumbnail'] !== 'https://ions.com/assets/logos/ionlogo-thumb.png' &&
                                         $result['thumbnail'] !== 'https://iblog.bz/assets/ionthumbnail.png';
                        
                        if (!$has_thumbnail) {
                            // For posts, use the ION logo; for videos, use gradient
                            if ($result['type'] !== 'video') {
                                $thumb = 'https://ions.com/assets/logos/ionlogo-thumb.png';
                            } else {
                                $thumb = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9InVybCgjZ3JhZGllbnQwX2xpbmVhcl8xXzEpIi8+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkaWVudDBfbGluZWFyXzFfMSIgeDE9IjAiIHkxPSIwIiB4Mj0iMTYiIHkyPSIxNiIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiPjxzdG9wIHN0b3AtY29sb3I9IiNCMDgyNTQiLz48c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiNDNDlBNkYiLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48L3N2Zz4=';
                            }
                        } else {
                            $thumb = $result['thumbnail'];
                        }
                        
                        // Generate preview URL for video hover (only for videos)
                        $previewUrl = '';
                        if ($result['type'] === 'video') {
                            $videoType = strtolower($result['source'] ?? $result['videotype'] ?? 'local');
                            $videoId = $result['video_id'] ?? '';
                            
                            // Extract video ID if not in database
                            if (empty($videoId) && !empty($result['link'])) {
                                $videoId = extractVideoIdFromUrl($result['link']);
                                if (empty($videoType) || $videoType === 'local') {
                                    $videoType = detectVideoType($result['link']);
                                }
                            }
                            
                            // Generate preview URL using helper function (supports all platforms)
                            $previewUrl = generateVideoPreviewUrl(
                                $videoType,
                                $videoId,
                                $result['video_link'] ?? $result['link'] ?? '',
                                $result['optimized_url'] ?? '',
                                $result['hls_manifest_url'] ?? ''
                            );
                        }
                    ?>
                        <div class="result-card" style="position:relative">
                            <?php if ($result['type'] === 'video'): 
                                // Determine video type and ID for modal
                                $modalVideoType = strtolower($result['source'] ?? $result['videotype'] ?? 'local');
                                $modalVideoId = $result['video_id'] ?? '';
                                
                                // Extract video ID if not in database
                                if (empty($modalVideoId) && !empty($result['link'])) {
                                    if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $result['link'], $m)) {
                                        $modalVideoId = $m[1];
                                        $modalVideoType = 'youtube';
                                    } elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $result['link'], $m)) {
                                        $modalVideoId = $m[1];
                                        $modalVideoType = 'vimeo';
                                    }
                                }
                                
                                // For modal: determine correct video URL
                                // For platform videos (YouTube, Vimeo, etc.), use the actual platform URL from video_link
                                // For local videos, use optimized_url or video_link
                                $modalUrl = '';
                                if (in_array($modalVideoType, ['youtube', 'vimeo', 'wistia', 'rumble', 'loom', 'muvi'])) {
                                    // Platform videos: use the platform URL from database
                                    $modalUrl = $result['link']; // This contains video_link from database
                                } else {
                                    // Local videos: use the direct file URL
                                    if (!empty($result['optimized_url'])) {
                                        $modalUrl = $result['optimized_url'];
                                    } elseif (!empty($result['video_link'])) {
                                        $modalUrl = $result['video_link'];
                                    } elseif (!empty($result['link'])) {
                                        $modalUrl = $result['link'];
                                    }
                                }
                                ?>
                                <!-- Video Card: Thumbnail opens player, title goes to /v/shortlink -->
                                <a href="<?= htmlspecialchars($link) ?>" 
                                   class="video-thumb-container video-thumb" 
                                   style="text-decoration:none;color:inherit;display:block" 
                                   data-preview-url="<?= htmlspecialchars($previewUrl) ?>" 
                                   data-video-type="<?= htmlspecialchars($modalVideoType) ?>"
                                   data-video-id="<?= htmlspecialchars($modalVideoId) ?>"
                                   data-video-url="<?= htmlspecialchars($modalUrl) ?>"
                                   data-video-title="<?= htmlspecialchars($result['title']) ?>"
                                   data-source="<?= htmlspecialchars($result['source'] ?? '') ?>"
                                   data-videotype="<?= htmlspecialchars($result['videotype'] ?? '') ?>"
                                   rel="noopener">
                                    <img class="thumb" src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($result['title']) ?>" loading="lazy">
                                    <?php if (strpos($thumb, 'data:image/svg') !== false): ?>
                                    <div class="no-image-overlay">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom: 8px;">
                                            <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                                            <circle cx="12" cy="13" r="3"></circle>
                                        </svg>
                                        <span>No Image</span>
                                    </div>
                                    <?php endif; ?>
                                    <span class="result-type"><?= htmlspecialchars($result['type']) ?></span>
                                    <div class="play-icon">
                                        <svg width="64" height="64" viewBox="0 0 24 24" fill="rgba(255,255,255,0.9)">
                                            <circle cx="12" cy="12" r="10" fill="rgba(0,0,0,0.6)"></circle>
                                            <polygon points="10 8 16 12 10 16 10 8" fill="white"></polygon>
                                        </svg>
                                    </div>
                                    <div class="meta">
                                        <a href="<?= htmlspecialchars($link) ?>" class="title-link" onclick="event.stopPropagation(); window.location.href=this.href; return false;">
                                            <h3 class="title"><?= htmlspecialchars($result['title']) ?></h3>
                                        </a>
                                        <div class="breadcrumb">
                                            <?php if (!empty($result['category'])): ?>
                                                <span><?= htmlspecialchars($result['category']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($result['channel_title'])): ?>
                                                <?php if (!empty($result['category'])): ?><span class="dot">•</span><?php endif; ?>
                                                <span><?= htmlspecialchars($result['channel_title']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php else: ?>
                                <!-- Non-Video Card: Unified link behavior -->
                                <a href="<?= htmlspecialchars($link) ?>" class="video-thumb-container" style="text-decoration:none;color:inherit;display:block" target="_blank">
                                    <img class="thumb" src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($result['title']) ?>" loading="lazy">
                                    <span class="result-type"><?= htmlspecialchars($result['type']) ?></span>
                                    <div class="meta">
                                        <h3 class="title"><?= htmlspecialchars($result['title']) ?></h3>
                                    </div>
                                </a>
                                
                                <!-- Share Action for Non-Video Content -->
                                <div class="share-actions">
                                    <div style="opacity:0.3;font-size:0.75rem;color:#6b7280;">Content from <?= htmlspecialchars(ucfirst($result['type'])) ?></div>
                                    <button class="ion-share-button simple-share-button" 
                                            onclick="openSimpleShareModal('<?= htmlspecialchars($link, ENT_QUOTES) ?>', '<?= htmlspecialchars($result['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($result['excerpt'] ?? '', ENT_QUOTES) ?>')"
                                            style="background: none; border: none; cursor: pointer; padding: 6px 8px; color: #3b82f6; border-radius: 6px; transition: all 0.2s; display: flex; align-items: center; gap: 4px; font-size: 12px;"
                                            onmouseover="this.style.backgroundColor='rgba(59, 130, 246, 0.1)'" 
                                            onmouseout="this.style.backgroundColor='transparent'">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                                        </svg>
                                        <span>Share</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($result['type'] === 'video' && isset($result['id'])): ?>
                            <!-- Like/Dislike and Share Actions -->
                            <div class="share-actions">
                                <!-- Like/Dislike Reactions -->
                                <div class="video-reactions compact" data-video-id="<?= $result['id'] ?>" data-user-action="<?= htmlspecialchars($result['user_reaction'] ?? '') ?>">
                                    <?php if ($is_viewer_logged_in): ?>
                                        <button class="reaction-btn like-btn" title="Like this video">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                            </svg>
                                            <span class="like-count"><?= ($result['likes'] ?? 0) > 0 ? number_format($result['likes']) : '' ?></span>
                                        </button>
                                        <button class="reaction-btn dislike-btn" title="Dislike this video">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                            </svg>
                                            <span class="dislike-count"><?= ($result['dislikes'] ?? 0) > 0 ? number_format($result['dislikes']) : '' ?></span>
                                        </button>
                                    <?php else: ?>
                                        <button class="reaction-btn like-btn" title="Login to like this video" onclick="showLoginModal()">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                            </svg>
                                            <span class="like-count"><?= ($result['likes'] ?? 0) > 0 ? number_format($result['likes']) : '' ?></span>
                                        </button>
                                        <button class="reaction-btn dislike-btn" title="Login to dislike this video" onclick="showLoginModal()">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                            </svg>
                                            <span class="dislike-count"><?= ($result['dislikes'] ?? 0) > 0 ? number_format($result['dislikes']) : '' ?></span>
                                        </button>
                                    <?php endif; ?>
                                    <div class="reaction-feedback"></div>
                                </div>
                                
                                <!-- Share Button -->
                                <?php echo $enhanced_share_manager->renderShareButton($result['id'], [
                                    'size' => 'small',
                                    'style' => 'icon',
                                    'platforms' => ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'],
                                    'show_embed' => true
                                ]); ?>
                            </div>
                            <?php endif; ?>
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

<!-- Video Modal HTML Structure -->
<?php require_once __DIR__ . '/../includes/video-modal.php'; ?>

<!-- Video Modal System JS -->
<script src="/includes/video-modal.js"></script>

<!-- Enhanced Share System JS -->
<script src="/share/enhanced-ion-share.js"></script>

<!-- Video Reactions System JS -->
<script src="/login/modal.js"></script>
<script src="/app/video-reactions.js"></script>

<!-- Video Hover Preview System -->
<script src="/includes/video-hover-preview.js"></script>

<!-- Video Modal and Hover Preview Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Search page: Initializing video system');
    
    // Enable debug mode for hover previews to diagnose R2 video issues
    if (typeof VideoHoverPreview !== 'undefined') {
        VideoHoverPreview.setDebug(true);
        console.log('🔍 Debug mode enabled for video hover previews');
    }
    
    // Initialize Video Modal if not already initialized
    if (typeof VideoModal !== 'undefined' && VideoModal.init) {
        console.log('🎥 Initializing VideoModal');
        VideoModal.init();
    } else if (typeof initModal === 'function') {
        console.log('🎥 Calling initModal directly');
        initModal();
    } else {
        console.warn('⚠️ VideoModal not found, trying legacy initialization');
    }
    
    // Set up video click handlers immediately (no delay needed)
    console.log('🎬 Setting up video click handlers');
    setupVideoClickHandlers();
    
    function setupVideoClickHandlers() {
    // Video Modal Click Handler (enhanced for all video types)
    const thumbs = document.querySelectorAll(".video-thumb");
    console.log(`🎯 Found ${thumbs.length} video thumbnails to attach handlers`);
    
    thumbs.forEach(function(thumb, index) {
        // Use capture phase to intercept before navigation
        thumb.addEventListener("click", function(e) {
            console.log(`🖱️ CLICK DETECTED on video ${index + 1}`, e.target);
            
            // Check if click is on title link - if so, allow navigation
            if (e.target.closest('.title-link')) {
                console.log('🔗 Click on title link - allowing navigation to video page');
                return true; // Allow default navigation
            }
            
            console.log('🎬 Click on video thumbnail - opening modal');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            let videoType = this.getAttribute("data-video-type");
            let videoId = this.getAttribute("data-video-id");
            let videoUrl = this.getAttribute("data-video-url");
            const videoFormat = this.getAttribute("data-video-format") || 'mp4';
            
            console.log('🎬 Video clicked:', { videoType, videoId, videoUrl });
            
            // If videoType is not set or is generic, try to detect from URL
            if (!videoType || videoType === 'local' || videoType === 'Youtube') {
                // Normalize type names
                if (videoType === 'Youtube') videoType = 'youtube';
                
                // Auto-detect type from URL if needed
                if (videoUrl) {
                    if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
                        videoType = 'youtube';
                        console.log('🔍 Detected YouTube from URL');
                    } else if (videoUrl.includes('vimeo.com')) {
                        videoType = 'vimeo';
                        console.log('🔍 Detected Vimeo from URL');
                    } else if (videoUrl.includes('wistia.com') || videoUrl.includes('wi.st')) {
                        videoType = 'wistia';
                        console.log('🔍 Detected Wistia from URL');
                    } else if (videoUrl.includes('rumble.com')) {
                        videoType = 'rumble';
                        console.log('🔍 Detected Rumble from URL');
                    } else if (videoUrl.includes('loom.com')) {
                        videoType = 'loom';
                        console.log('🔍 Detected Loom from URL');
                    } else if (videoUrl.includes('muvi.com')) {
                        videoType = 'muvi';
                        console.log('🔍 Detected Muvi from URL');
                    }
                }
            }
            
            console.log('🎬 Opening modal with:', { videoType, videoId, videoUrl, videoFormat });
            
            if (typeof openVideoModal === 'function') {
                openVideoModal(videoType, videoId, videoUrl, videoFormat);
            } else {
                console.error('❌ openVideoModal function not found - VideoModal may not be initialized');
                // Try to initialize and retry
                if (typeof VideoModal !== 'undefined' && VideoModal.init) {
                    VideoModal.init();
                    setTimeout(() => {
                        if (typeof openVideoModal === 'function') {
                            openVideoModal(videoType, videoId, videoUrl, videoFormat);
                        }
                    }, 100);
                }
            }
            
            return false;
        }, true); // Use capture phase to intercept before default link behavior
    });
    } // End setupVideoClickHandlers
    
    // Note: Video hover previews are now initialized automatically by video-hover-preview.js
    // No need to call initializeVideoHoverPreviews() here
    
    // Simple Share Modal for Non-Video Content (Posts/Pages)
    window.openSimpleShareModal = function(url, title, description) {
        const encodedUrl = encodeURIComponent(url);
        const encodedTitle = encodeURIComponent(title);
        const encodedDesc = encodeURIComponent(description);
        
        // Create modal HTML
        const modalHTML = `
            <div id="simpleShareModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; display: flex; align-items: center; justify-content: center;">
                <div style="background: #1f2937; border-radius: 16px; padding: 24px; max-width: 500px; width: 90%; border: 1px solid #374151;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; color: #f9fafb; font-size: 1.25rem;">Share Content</h3>
                        <button onclick="document.getElementById('simpleShareModal').remove()" style="background: none; border: none; color: #9ca3af; font-size: 24px; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                    </div>
                    <div style="color: #d1d5db; margin-bottom: 16px; font-size: 0.875rem;">${title}</div>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}" target="_blank" rel="noopener" style="display: flex; flex-direction: column; align-items: center; padding: 12px; background: #374151; border-radius: 8px; text-decoration: none; color: #60a5fa; transition: background 0.2s;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            <span style="margin-top: 4px; font-size: 0.75rem;">Facebook</span>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}" target="_blank" rel="noopener" style="display: flex; flex-direction: column; align-items: center; padding: 12px; background: #374151; border-radius: 8px; text-decoration: none; color: #60a5fa; transition: background 0.2s;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                            <span style="margin-top: 4px; font-size: 0.75rem;">Twitter</span>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}" target="_blank" rel="noopener" style="display: flex; flex-direction: column; align-items: center; padding: 12px; background: #374151; border-radius: 8px; text-decoration: none; color: #60a5fa; transition: background 0.2s;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            <span style="margin-top: 4px; font-size: 0.75rem;">LinkedIn</span>
                        </a>
                        <button onclick="navigator.clipboard.writeText('${url}'); alert('Link copied to clipboard!')" style="display: flex; flex-direction: column; align-items: center; padding: 12px; background: #374151; border: none; border-radius: 8px; color: #60a5fa; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            <span style="margin-top: 4px; font-size: 0.75rem;">Copy Link</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Inject and show modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Close on background click
        document.getElementById('simpleShareModal').addEventListener('click', function(e) {
            if (e.target.id === 'simpleShareModal') {
                this.remove();
            }
        });
    };
});

// Update sort parameter and reload page
function updateSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    url.searchParams.set('page', '1'); // Reset to first page when sorting changes
    window.location.href = url.toString();
}
</script>
</body>

</html>