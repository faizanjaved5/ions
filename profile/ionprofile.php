<?php
/**
 * ION Profile Page Renderer
 * Route: /@{handle}  → iondynamic.php?route=profile&handle={handle}
 *
 * Data:
 *  - Users:   IONEERS          (user_id PK, email, fullname, profile_name, handle, photo_url, created_at, user_role, status, preferences, login_count, location, slug, about, etc.)
 *  - Videos:  IONLocalVideos   (id, user_id, title, thumbnail, video_link, published_at, view_count, status, visibility, layout, source, etc.)
 *
 * Behavior:
 *  - Looks up user by handle (case-insensitive).
 *  - Renders profile header and a grid of the user's Public + Approved videos.
 *  - Uses view_count from IONLocalVideos for display (no schema change).
 *  - Safe fallbacks if optional fields are missing.
 */

declare(strict_types=1);

// Start session for authentication checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Bootstrap / Config ----------------------------------------------------
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Configuration missing." . $configPath;
    exit;
}
$config = require $configPath;

// Database connection using config keys (host, dbname, username, password)
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

// ---- Input -----------------------------------------------------------------
$handle = $_GET['handle'] ?? '';
$handle = trim($handle);

if ($handle === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $handle)) {
    http_response_code(404);
    echo "Profile not found.";
    exit;
}

// ---- Query: User -----------------------------------------------------------
// Note: Table name is uppercase IONEERS in production databases
$sqlUser = "SELECT user_id, email, fullname, profile_name, handle, photo_url, created_at, user_role, status, preferences, location, slug, about
            FROM IONEERS
            WHERE handle = :handle
            LIMIT 1";
$stmt = $pdo->prepare($sqlUser);
$stmt->execute([':handle' => $handle]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo "Profile not found.";
    exit;
}

// Optional derived values
$displayName = $user['profile_name'] ?: ($user['fullname'] ?: explode('@', $user['email'])[0]);
$joinedDate  = $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : '';
$avatar      = $user['photo_url'] ?: 'https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($displayName) . '&size=256';

// Get location and slug for linking
$location = $user['location'] ?? '';
$slug = $user['slug'] ?? '';

// Get about text (prefer the new about field, fallback to preferences bio)
$aboutText = '';
if (!empty($user['about'])) {
    $aboutText = $user['about'];
} elseif (!empty($user['preferences'])) {
    try {
        $prefs = json_decode($user['preferences'], true, 512, JSON_THROW_ON_ERROR);
        if (!empty($prefs['bio'])) {
            $aboutText = (string)$prefs['bio'];
        }
    } catch (Throwable $e) {}
}

// Fallback to default text if no about content
if (empty($aboutText)) {
    $aboutText = "Creator of thoughtful tutorials and deep-dives into modern web tooling.";
}


echo "<!-- All user fields: " . htmlspecialchars(print_r($user, true)) . " -->";

// ---- Query: Videos (Public + Approved) ------------------------------------
// Note: Table names may be case-sensitive on Linux servers
// Check if current viewer is logged in
$current_user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$is_viewer_logged_in = !empty($current_user_id);
$current_viewer = null;

// Fetch current viewer's profile data for menu
if ($is_viewer_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, handle, fullname, email, photo_url FROM IONEERS WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $current_viewer = $stmt->fetch(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log('Error fetching current viewer data: ' . $e->getMessage());
    }
}

$sqlVid = "SELECT v.id, v.slug, v.short_link, v.title, v.thumbnail, v.video_link, v.published_at, 
                  v.view_count, v.layout, v.source, v.status, v.visibility, v.videotype, v.video_id,
                  v.likes, v.dislikes,
                  " . ($is_viewer_logged_in ? "vl.action_type as user_reaction" : "NULL as user_reaction") . "
           FROM IONLocalVideos v
           " . ($is_viewer_logged_in ? "LEFT JOIN IONVideoLikes vl ON v.id = vl.video_id AND vl.user_id = :viewer_id" : "") . "
           WHERE v.user_id = :uid
             AND v.visibility = 'Public'
             AND v.status = 'Approved'
           ORDER BY COALESCE(v.published_at, v.date_added) DESC
           LIMIT 100";

// Debug logging
error_log("PROFILE SQL: " . $sqlVid);
error_log("PROFILE PARAMS: uid={$user['user_id']}, viewer_id=" . ($is_viewer_logged_in ? $current_user_id : 'NOT SET'));

$stmt = $pdo->prepare($sqlVid);
$params = [':uid' => (int)$user['user_id']]; // Ensure integer type
if ($is_viewer_logged_in) {
    $params[':viewer_id'] = (int)$current_user_id; // Ensure integer type
}
$stmt->execute($params);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log reactions found
foreach ($videos as $v) {
    $reaction_value = $v['user_reaction'] ?? 'NULL';
    error_log("VIDEO {$v['id']}: user_reaction='{$reaction_value}' (will output as data-user-action=\"" . htmlspecialchars($reaction_value ?: '') . "\")");
}

// Load Enhanced Share Manager
require_once $root . '/share/enhanced-share-manager.php';
require_once $root . '/config/database.php';

// Make $db available in this scope (created by database.php)
global $db;

// Initialize enhanced share manager
$enhanced_share_manager = new EnhancedIONShareManager($db);

// Simple helpers
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function views_str($n): string {
    $n = (int)$n;
    if ($n >= 1000000) return number_format($n/1000000, 1) . 'M views';
    if ($n >= 1000)    return number_format($n/1000, 1) . 'k views';
    return $n . ' views';
}

// Set default theme to dark
$theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? $_GET['theme'] ?? 'dark';

// Cache busting version
$version = '1.0.' . filemtime(__FILE__);

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<!-- Aggressive cache prevention for CSS/JS -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<title><?php echo '@' . h($user['handle']) . ' — ION'; ?></title>

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

<style>
    /* ION Profile Styles - Version: <?= $version ?> */
    /* Theme: <?= $theme ?> (initial) */
    
    /* Dark Mode Variables (default)) */
    :root,
    body[data-theme="dark"] { 
        --bg: #0f1216;     /* Very dark background */
        --panel: #151a21;  /* Dark panel - subtle contrast with bg */
        --muted: #8a94a6;  /* Muted gray-blue text */
        --text: #e9eef7;   /* Light text */
        --chip: #202733;   /* Dark blue chips */
        --ring: #2c3442;   /* Dark blue-gray borders */
    }
    
    /* Light Mode Variables - Override when data-theme="light" */
    body[data-theme="light"] { 
        --bg: #ffffff;     /* White background */
        --panel: #f8f9fa;  /* Very light gray panels */
        --muted: #64748b;  /* Medium gray muted text */
        --text: #0f172a;   /* Very dark text */
        --chip: #e9ecef;   /* Light gray chips */
        --ring: #dee2e6;   /* Light borders */
    }
    
    /* IMPORTANT: Scope resets to wrap only - DO NOT affect navbar/menu */
    .wrap, .wrap * {
        box-sizing: border-box;
    }
    
    body{
        margin:0;
        padding:0;
        font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;
        background:var(--bg);
        color:var(--text);
        border-top:none !important;
    }
    
    .wrap{max-width:1200px;margin:0 auto;padding:32px 20px;border-top:none !important;}
    
    /* Remove any white line under navbar */
    #ion-navbar-root,
    #ion-navbar-root *,
    #ion-navbar-root::after,
    #ion-navbar-root + * {
        border-top: none !important;
        border-bottom: none !important;
    }
    
    /* Default Layout (Stacked): Avatar + About in left column */
    .header{display:grid;grid-template-columns:300px 1fr;gap:40px;align-items:start;margin-top:10px}
    
    /* Three Column Layout: Avatar | Content | About */
    body[data-layout="three-column"] .header {
        grid-template-columns: 300px 1fr 280px;
        gap: 32px;
    }
    
    body[data-layout="three-column"] .left-column {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    
    body[data-layout="three-column"] .about {
        display: block !important; /* Override responsive hiding */
        position: static;
        order: 3; /* Move to third column */
    }
    
    body[data-layout="three-column"] .header > .about {
        /* When about is moved out, it becomes direct child of .header */
        grid-column: 3;
        grid-row: 1;
    }
    .avatar{width:300px;height:300px;border-radius:16px;object-fit:cover;border:1px solid var(--ring);background:var(--panel)}
    .name{font-size:28px;font-weight:700;color:var(--text)}
    .sub{font-size:13px;color:var(--muted);display:flex;gap:12px;align-items:center}
    .badge{display:inline-flex;padding:3px 8px;background:var(--chip);border:1px solid var(--ring);border-radius:999px;font-size:12px;color:var(--text)}
    .bio{margin-top:10px;color:var(--text);font-size:14px;line-height:1.5}
    .bio strong{color:var(--text);font-weight:600}
    .bio em{color:var(--muted);font-style:italic}
    .bio u{text-decoration:underline}
    .bio ul,.bio ol{margin:8px 0;padding-left:20px}
    .bio li{margin:4px 0}
    .bio a{color:#60a5fa;text-decoration:none}
    .bio a:hover{text-decoration:underline}
    .left-column{display:flex;flex-direction:column;gap:20px}
    .right-column{flex:1}
    .about{background:var(--panel);border:1px solid var(--ring);padding:14px 16px;border-radius:14px;min-width:260px}
    .about h3{margin:0 0 8px 0;font-size:14px;font-weight:700}
    .about .row{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-top:1px solid var(--ring)}
    .about .row:first-of-type{border-top:none;padding-top:0}
    .location-link{color:var(--text);text-decoration:none;transition:color 0.2s ease}
    .location-link:hover{color:#60a5fa}
    /* Video Grid - Responsive Multi-Column Layout */
    .grid{
        display:grid;
        gap:18px;
        margin-top:26px;
        /* Auto-fit columns with minimum 280px width, maximum 1fr */
        grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
    }
    
    /* Desktop: Force minimum 3 columns when space allows */
    @media (min-width: 1200px) {
        .grid{
            grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));
            max-width:100%;
        }
    }
    
    /* Large Desktop: Up to 4-5 columns */
    @media (min-width: 1600px) {
        .grid{
            grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));
        }
    }
    
    /* Tablet: 2-3 columns */
    @media (max-width: 1199px) and (min-width: 768px) {
        .grid{
            grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
        }
    }
    
    /* Mobile: Horizontal scroll layout */
    @media (max-width: 767px) {
        .scroll-hint{
            display:inline !important;
            animation:pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
        
        .videos-section{
            overflow-x:auto;
            -webkit-overflow-scrolling:touch;
            scrollbar-width:thin;
            scrollbar-color:var(--ring) transparent;
        }
        
        .videos-section::-webkit-scrollbar{
            height:8px;
        }
        
        .videos-section::-webkit-scrollbar-track{
            background:transparent;
        }
        
        .videos-section::-webkit-scrollbar-thumb{
            background:var(--ring);
            border-radius:4px;
        }
        
        .grid{
            display:flex;
            gap:16px;
            padding-bottom:10px;
            /* Enable horizontal scroll */
            overflow-x:auto;
            scroll-snap-type:x mandatory;
        }
        
        .card{
            flex:0 0 280px; /* Fixed width cards for horizontal scroll */
            scroll-snap-align:start;
        }
        
        /* Hide scroll hint after user starts scrolling */
        .grid.scrolled + h2 .scroll-hint{
            display:none !important;
        }
    }
    
    /* Extra small mobile */
    @media (max-width: 480px) {
        .grid .card{
            flex:0 0 260px; /* Slightly smaller cards */
        }
    }
    
    .card{background:var(--panel);border:1px solid var(--ring);border-radius:16px;overflow:hidden;transition:transform 0.2s ease, box-shadow 0.2s ease}
    .card:hover{transform:translateY(-2px)}
    .thumb{aspect-ratio:16/9;width:100%;object-fit:cover;background:var(--panel);display:block}
    .video-thumb-container{position:relative;overflow:hidden}
    .preview-iframe-container{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;pointer-events:none;transition:opacity 0.3s ease;z-index:2;background:var(--bg)}
    @media (max-width: 768px) {
        .preview-iframe-container { display: none !important; }
    }
    .meta{padding:10px 12px}
    .title{font-size:14px;line-height:1.35;margin:0 0 8px 0;color:var(--text) !important;font-weight:600 !important}
    a .title, a.video-thumb-container .title, .video-thumb-container .title {color:var(--text) !important;font-weight:600 !important}
    .row{display:flex;gap:12px;align-items:center;color:var(--muted);font-size:12px}
    .row .dot{width:4px;height:4px;border-radius:50%;background:var(--muted)}
    .ion-mark{height:80px;transition:opacity 0.3s ease}
    .ion-mark:hover{opacity:0.8}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
    
    /* Responsive header adjustments */
    @media (max-width:1024px){ 
        .about{display:none;}
        /* Force stacked layout on smaller screens */
        body[data-layout="three-column"] .header {
            grid-template-columns: 300px 1fr !important;
        }
        body[data-layout="three-column"] .about {
            display: none !important;
        }
    }
    @media (max-width:640px){ 
        .header{grid-template-columns:1fr;grid-auto-rows:auto} 
        .topbar{justify-content:flex-start} 
        .videos-section h2{margin:20px 0 8px 0;font-size:16px;}
    }
    a.card, a.title { text-decoration:none; }
    
    /* Light Mode Overrides for better shadows */
    body[data-theme="light"] .card {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    body[data-theme="light"] .card:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15) !important;
    }
    
    /* Dark Mode card hover - FROM ionprofile1.php (WORKING VERSION) */
    body[data-theme="dark"] .card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    
    /* Light Mode: Ensure proper text contrast and backgrounds */
    body[data-theme="light"] {
        background: #ffffff !important;
        color: #0f172a !important;
    }
    
    body[data-theme="light"] .name,
    body[data-theme="light"] h2,
    body[data-theme="light"] h3,
    body[data-theme="light"] .title {
        color: #0f172a !important;
    }
    
    body[data-theme="light"] .bio,
    body[data-theme="light"] .bio strong,
    body[data-theme="light"] p {
        color: #1e293b !important;
    }
    
    body[data-theme="light"] .sub,
    body[data-theme="light"] .muted {
        color: #64748b !important;
    }
    
    body[data-theme="light"] .badge {
        color: #475569 !important;
        background: #e9ecef !important;
        border-color: #dee2e6 !important;
    }
    
    body[data-theme="light"] .about {
        background: #f8f9fa !important;
        border-color: #dee2e6 !important;
        color: #0f172a !important;
    }
    
    body[data-theme="light"] .card {
        background: #ffffff !important;
        border-color: #e2e8f6 !important;
    }
    
    body[data-theme="light"] .meta,
    body[data-theme="light"] .row {
        color: #475569 !important;
    }
    
    /* Dark Mode: Ensure proper contrast - FROM ionprofile1.php */
    body[data-theme="dark"] {
        background: #0f1216 !important;
        color: #e9eef7 !important;
    }
    
    body[data-theme="dark"] .name,
    body[data-theme="dark"] h2,
    body[data-theme="dark"] h3,
    body[data-theme="dark"] .title {
        color: #e9eef7 !important;
    }
    
    body[data-theme="dark"] .bio,
    body[data-theme="dark"] .bio strong,
    body[data-theme="dark"] p {
        color: #e9eef7 !important;
    }
    
    body[data-theme="dark"] .sub,
    body[data-theme="dark"] .muted {
        color: #8a94a6 !important;
    }
    
    body[data-theme="dark"] .badge {
        color: #8a94a6 !important;
        background: #202733 !important;
        border-color: #2c3442 !important;
    }
    
    body[data-theme="dark"] .about {
        background: #151a21 !important;
        border-color: #2c3442 !important;
        color: #e9eef7 !important;
    }
    
    body[data-theme="dark"] .card {
        background: #151a21 !important;
        border-color: #2c3442 !important;
    }
    
    body[data-theme="dark"] .meta,
    body[data-theme="dark"] .row {
        color: #8a94a6 !important;
    }
    
    /* Hide layout toggle on screens where three-column doesn't work */
    @media (max-width: 1024px) {
        button[aria-label="Toggle layout"] {
            display: none !important;
        }
    }
</style>

<!-- Theme Switcher & Layout Toggle JavaScript -->
<script>
    // VERSION: <?= $version ?>
    console.log('🔄 ION Profile - Version: <?= $version ?>');
    console.log('🎨 Initial theme from PHP: <?= $theme ?>');
    
    // Initialize theme from session/cookie/query
    let currentTheme = '<?= $theme ?>';
    
    // Initialize layout from localStorage (default: stacked)
    let currentLayout = localStorage.getItem('profile-layout') || 'stacked';
    console.log('📐 Initial layout:', currentLayout);
    
    // Apply initial layout
    document.body.setAttribute('data-layout', currentLayout === 'three-column' ? 'three-column' : 'stacked');
    console.log('✅ Body data-theme attribute set to:', document.body.getAttribute('data-theme'));
    console.log('✅ Body data-layout attribute set to:', document.body.getAttribute('data-layout'));
    
    // Toggle theme function
    function toggleTheme() {
        console.log('🎨 toggleTheme() called');
        console.log('   Current theme BEFORE toggle:', currentTheme);
        
        // Toggle between light and dark
        currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
        console.log('   New theme AFTER toggle:', currentTheme);
        
        // Update body data-theme attribute
        document.body.setAttribute('data-theme', currentTheme);
        console.log('   Body data-theme attribute updated to:', document.body.getAttribute('data-theme'));
        
        // Debug: Check CSS variable values
        const bodyStyles = window.getComputedStyle(document.body);
        const bgColor = bodyStyles.getPropertyValue('--bg');
        const textColor = bodyStyles.getPropertyValue('--text');
        console.log('   CSS Variables after toggle:');
        console.log('     --bg:', bgColor);
        console.log('     --text:', textColor);
        console.log('     background-color:', bodyStyles.backgroundColor);
        
        // Update theme icon in navbar
        if (typeof window.updateThemeIcon === 'function') {
            window.updateThemeIcon();
        }
        
        // Save preference to cookie
        document.cookie = `theme=${currentTheme}; path=/; max-age=31536000`; // 1 year
        console.log('   Theme saved to cookie');
        
        // Optional: Save to session via AJAX
        fetch('/api/set-theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme: currentTheme })
        }).then(() => console.log('   Theme saved to server'))
        .catch(err => console.warn('   Theme save to server failed:', err));
    }
    
    // Toggle layout function
    function toggleLayout() {
        // Toggle between stacked and three-column
        currentLayout = currentLayout === 'stacked' ? 'three-column' : 'stacked';
        
        // Update body data-layout attribute
        document.body.setAttribute('data-layout', currentLayout);
        
        // Move about card to appropriate position
        const aboutCard = document.querySelector('.about');
        const header = document.querySelector('.header');
        const leftColumn = document.querySelector('.left-column');
        
        if (currentLayout === 'three-column') {
            // Move about card to be direct child of header (third column)
            if (aboutCard && header && aboutCard.parentElement !== header) {
                header.appendChild(aboutCard);
            }
        } else {
            // Move about card back to left column
            if (aboutCard && leftColumn && aboutCard.parentElement !== leftColumn) {
                leftColumn.appendChild(aboutCard);
            }
        }
        
        // Save preference to localStorage
        localStorage.setItem('profile-layout', currentLayout);
        
        console.log('Layout switched to:', currentLayout);
    }
    
    // Expose globally for navbar/other components
    window.toggleTheme = toggleTheme;
    window.IONToggleTheme = toggleTheme; // Alternative name
    window.switchTheme = toggleTheme; // Another alternative
    window.toggleLayout = toggleLayout;
    window.IONToggleLayout = toggleLayout;
    
    // Also expose on document for easy access
    document.toggleTheme = toggleTheme;
    document.toggleLayout = toggleLayout;
    
    // Debug helper function
    window.debugTheme = function() {
        console.log('=== THEME DEBUG INFO ===');
        console.log('Version: <?= $version ?>');
        console.log('Current theme:', currentTheme);
        console.log('Body data-theme:', document.body.getAttribute('data-theme'));
        console.log('Body data-layout:', document.body.getAttribute('data-layout'));
        
        const bodyStyles = window.getComputedStyle(document.body);
        console.log('CSS Variables:');
        console.log('  --bg:', bodyStyles.getPropertyValue('--bg'));
        console.log('  --panel:', bodyStyles.getPropertyValue('--panel'));
        console.log('  --text:', bodyStyles.getPropertyValue('--text'));
        console.log('  --muted:', bodyStyles.getPropertyValue('--muted'));
        console.log('Computed styles:');
        console.log('  background-color:', bodyStyles.backgroundColor);
        console.log('  color:', bodyStyles.color);
        
        // Check if CSS rules exist
        const styleSheets = Array.from(document.styleSheets);
        const hasLightMode = styleSheets.some(sheet => {
            try {
                const rules = Array.from(sheet.cssRules || []);
                return rules.some(rule => 
                    rule.selectorText && rule.selectorText.includes('data-theme="light"')
                );
            } catch(e) {
                return false;
            }
        });
        console.log('Light mode CSS rules found:', hasLightMode);
        console.log('======================');
    };
    
    // Initialize layout on page load (move about card if needed)
    document.addEventListener('DOMContentLoaded', function() {
        const aboutCard = document.querySelector('.about');
        const header = document.querySelector('.header');
        const leftColumn = document.querySelector('.left-column');
        
        if (currentLayout === 'three-column') {
            // Move about card to header (third column)
            if (aboutCard && header && aboutCard.parentElement !== header) {
                header.appendChild(aboutCard);
            }
        }
    });
    
    // Watch for theme changes made by other systems (like navbar)
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                const newTheme = document.body.getAttribute('data-theme');
                if (newTheme && newTheme !== currentTheme) {
                    currentTheme = newTheme;
                }
            }
            
            // Auto-correct if background doesn't match theme
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const bgColor = window.getComputedStyle(document.body).backgroundColor;
                const rgb = bgColor.match(/\d+/g);
                if (rgb) {
                    const brightness = (parseInt(rgb[0]) * 299 + parseInt(rgb[1]) * 587 + parseInt(rgb[2]) * 114) / 1000;
                    
                    if (brightness > 128 && currentTheme === 'dark') {
                        currentTheme = 'light';
                        document.body.setAttribute('data-theme', 'light');
                    } else if (brightness <= 128 && currentTheme === 'light') {
                        currentTheme = 'dark';
                        document.body.setAttribute('data-theme', 'dark');
                    }
                }
            }
        });
    });
    
    // Start observing
    observer.observe(document.body, { 
        attributes: true, 
        attributeFilter: ['data-theme', 'style', 'class'] 
    });
</script>
</head>
<body data-theme="<?= $theme ?>">

<!-- ION Navbar Embed: fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<link rel="preload" as="style" href="/menu/ion-navbar.css">

<!-- ION Navbar (React component loaded via JavaScript) -->
<div id="ion-navbar-root"></div>

<!-- ION Navbar Embed: script setup -->
<script>
    // Minimal globals expected by some libraries
    window.process = window.process || {
        env: {
            NODE_ENV: 'production'
        }
    };
    window.global = window.global || window;
</script>
<script src="/menu/ion-navbar.iife.js"></script>
<script>
    (function() {
        if (window.IONNavbar && typeof window.IONNavbar.mount === 'function') {
            window.IONNavbar.mount('#ion-navbar-root', {
                useShadowDom: true,
                cssHref: '/menu/ion-navbar.css'
            });
        }
        
        // WORKAROUND: Add theme & layout toggle buttons to navbar after it loads
        setTimeout(() => {
            const navbar = document.getElementById('ion-navbar-root');
            if (navbar) {
                // Remove white border line from navbar (check both shadow DOM and regular DOM)
                try {
                    // Try to access shadow root
                    if (navbar.shadowRoot) {
                        const style = document.createElement('style');
                        style.textContent = `
                            * { border-top: none !important; border-bottom: none !important; }
                            nav { border-top: none !important; border-bottom: none !important; }
                            header { border-top: none !important; border-bottom: none !important; }
                        `;
                        navbar.shadowRoot.appendChild(style);
                    }
                    // Also remove from regular elements
                    navbar.style.borderTop = 'none';
                    navbar.style.borderBottom = 'none';
                } catch (e) {
                    console.log('Could not remove navbar border:', e);
                }
                
                // Check if navbar already has theme toggle
                const existingToggle = navbar.querySelector('[aria-label*="theme" i], [onclick*="theme" i]');
                if (!existingToggle) {
                    // Create a theme toggle button
                    const themeBtn = document.createElement('button');
                    themeBtn.setAttribute('aria-label', 'Toggle theme');
                    themeBtn.innerHTML = `
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                    `;
                    themeBtn.onclick = window.toggleTheme;
                    themeBtn.style.cssText = 'position: fixed; top: 15px; right: 5px; z-index: 10001; background: transparent; border: none; color: #f59e0b; cursor: pointer; padding: 8px; display: flex; align-items: center; transition: transform 0.2s;';
                    themeBtn.onmouseover = () => themeBtn.style.transform = 'scale(1.1)';
                    themeBtn.onmouseout = () => themeBtn.style.transform = 'scale(1)';
                    themeBtn.title = 'Toggle light/dark theme';
                    document.body.appendChild(themeBtn);
                    
                    // Create layout toggle button
                    const layoutBtn = document.createElement('button');
                    layoutBtn.setAttribute('aria-label', 'Toggle layout');
                    layoutBtn.innerHTML = `
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="3" x2="9" y2="21"></line>
                            <line x1="15" y1="3" x2="15" y2="21"></line>
                        </svg>
                    `;
                    layoutBtn.onclick = window.toggleLayout;
                    layoutBtn.style.cssText = 'position: fixed; top: 15px; right: 45px; z-index: 10001; background: transparent; border: none; color: #3b82f6; cursor: pointer; padding: 8px; display: flex; align-items: center; transition: transform 0.2s;';
                    layoutBtn.onmouseover = () => layoutBtn.style.transform = 'scale(1.1)';
                    layoutBtn.onmouseout = () => layoutBtn.style.transform = 'scale(1)';
                    layoutBtn.title = 'Toggle profile layout (2 or 3 columns)';
                    document.body.appendChild(layoutBtn);
                }
            }
        }, 1000); // Wait for navbar to fully load
    })();
</script>

<div class="wrap">

    <section class="header">
        <div class="left-column">
            <img class="avatar" src="<?php echo h($avatar); ?>" alt="<?php echo h($displayName); ?>">
            <aside class="about">
                <h3>About @<?php echo h($user['handle']); ?></h3>
                <div class="row"><span>Full name</span><span><?php echo h($user['fullname'] ?: $displayName); ?></span></div>
                <div class="row">
                    <span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <?php if ($location && $slug): ?>
                            <a href="https://ions.com/<?php echo h($slug); ?>" class="location-link" target="_blank">
                                <?php echo h($location); ?>
                            </a>
                        <?php elseif ($location): ?>
                            <?php echo h($location); ?>
                        <?php else: ?>
                            Remote
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($joinedDate): ?>
                <div class="row"><span>Joined</span><span><?php echo h($joinedDate); ?></span></div>
                <?php endif; ?>
            </aside>
        </div>
        <div class="right-column">
            <div class="name"><?php echo h($displayName); ?></div>
            <div class="sub">
                <span>@<?php echo h($user['handle']); ?></span>
                <?php if ($user['user_role']): ?><span class="dot"></span><span class="badge"><?php echo h($user['user_role']); ?></span><?php endif; ?>
            </div>
            <div class="bio"><?php echo $aboutText; ?></div>
        </div>
    </section>

    <?php
    // Include and render ION Featured Videos carousel for this profile
    $carousel_path = $root . '/includes/featured-videos.php';
    if (file_exists($carousel_path)) {
        require_once $carousel_path;
        renderFeaturedVideosCarousel($pdo, 'profile', $user['user_id']);
    } else {
        error_log('ION Featured Videos: Component file not found at ' . $carousel_path);
    }
    ?>

    <div class="videos-section">
        <h2 style="margin:26px 0 8px 0;font-size:16px;">
            Videos
            <?php if (count($videos) > 1): ?>
                <span class="scroll-hint" style="display:none;font-size:12px;color:var(--muted);font-weight:400;margin-left:8px;">← Scroll to see more →</span>
            <?php endif; ?>
        </h2>

        <section class="grid">
        <?php if (!$videos): ?>
            <div class="card" style="padding:20px">
                <div style="color:var(--muted);font-size:14px;">No public videos yet.</div>
            </div>
        <?php else: foreach ($videos as $v):
            $thumb = $v['thumbnail'] ?: '/assets/placeholders/video-16x9.png';
            $pub   = $v['published_at'] ? date('Y-m-d', strtotime($v['published_at'])) : '';
            // Link to internal video detail page using short_link
            $link  = !empty($v['short_link']) ? '/v/' . $v['short_link'] : '#';
            
            // Determine video type and generate preview URL
            $videoType = strtolower($v['source'] ?? $v['videotype'] ?? 'local');
            $videoId = $v['video_id'] ?? '';
            $previewUrl = '';
            
            // Extract video ID from URL if not in database
            if (empty($videoId) && !empty($v['video_link'])) {
                if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $v['video_link'], $m)) {
                    $videoId = $m[1];
                    $videoType = 'youtube';
                } elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $v['video_link'], $m)) {
                    $videoId = $m[1];
                    $videoType = 'vimeo';
                }
            }
            
            // Generate preview URL for hover
            if ($videoType === 'youtube' && $videoId) {
                $previewUrl = 'https://www.youtube.com/embed/' . h($videoId) . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . h($videoId);
            } elseif ($videoType === 'vimeo' && $videoId) {
                $previewUrl = 'https://player.vimeo.com/video/' . h($videoId) . '?autoplay=1&muted=1&background=1';
            } elseif ($videoType === 'wistia' && $videoId) {
                $previewUrl = 'https://fast.wistia.net/embed/iframe/' . h($videoId) . '?autoplay=1&muted=1&controls=0';
            } elseif ($videoType === 'rumble' && $videoId) {
                $previewUrl = 'https://rumble.com/embed/v' . h($videoId) . '/?autoplay=1&muted=1';
            } elseif ($videoType === 'muvi' && $videoId) {
                $previewUrl = 'https://embed.muvi.com/embed/' . h($videoId) . '?autoplay=1&muted=1';
            } elseif ($videoType === 'loom' && $videoId) {
                $previewUrl = 'https://www.loom.com/embed/' . h($videoId) . '?autoplay=1&muted=1&hide_owner=true&hide_share=true&hide_title=true&hideEmbedTopBar=true';
            } elseif (($videoType === 'local' || $videoType === 'upload') && !empty($v['video_link'])) {
                // FIXED: Check for both 'local' and 'upload' (since source can be 'Upload')
                // For local/uploaded videos, prefix with "local:" for JS handling
                $previewUrl = 'local:' . h($v['video_link']);
            }
        ?>
        <div class="card" style="position:relative">
            <a href="<?php echo h($link); ?>" class="video-thumb-container" style="text-decoration:none;color:inherit;display:block" data-preview-url="<?php echo h($previewUrl); ?>">
                <img class="thumb" src="<?php echo h($thumb); ?>" alt="<?php echo h($v['title']); ?>">
                <div class="meta">
                    <h3 class="title"><?php echo h($v['title']); ?></h3>
                    <div class="row">
                        <span><?php echo h(views_str($v['view_count'] ?? 0)); ?></span>
                        <?php if ($pub): ?><span class="dot"></span><span><?php echo h($pub); ?></span><?php endif; ?>
                    </div>
                </div>
            </a>
            <div class="share-actions" style="padding:0 12px 12px 12px;display:flex;gap:8px;justify-content:space-between;align-items:center">
                <!-- Like/Dislike Reactions -->
                <div class="video-reactions compact" data-video-id="<?php echo $v['id']; ?>" data-user-action="<?php echo h($v['user_reaction'] ?? ''); ?>">
                    <?php if ($is_viewer_logged_in): ?>
                        <button class="reaction-btn like-btn" title="Like this video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                            </svg>
                            <span class="like-count"><?php echo ($v['likes'] ?? 0) > 0 ? number_format($v['likes']) : ''; ?></span>
                        </button>
                        <button class="reaction-btn dislike-btn" title="Dislike this video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                            </svg>
                            <span class="dislike-count"><?php echo ($v['dislikes'] ?? 0) > 0 ? number_format($v['dislikes']) : ''; ?></span>
                        </button>
                    <?php else: ?>
                        <button class="reaction-btn like-btn" title="Login to like this video" onclick="showLoginModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                            </svg>
                            <span class="like-count"><?php echo ($v['likes'] ?? 0) > 0 ? number_format($v['likes']) : ''; ?></span>
                        </button>
                        <button class="reaction-btn dislike-btn" title="Login to dislike this video" onclick="showLoginModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                            </svg>
                            <span class="dislike-count"><?php echo ($v['dislikes'] ?? 0) > 0 ? number_format($v['dislikes']) : ''; ?></span>
                        </button>
                    <?php endif; ?>
                    <div class="reaction-feedback"></div>
                </div>
                
                <!-- Share Button -->
                <?php echo $enhanced_share_manager->renderShareButton($v['id'], [
                    'size' => 'small',
                    'style' => 'icon',
                    'platforms' => ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'],
                    'show_embed' => true
                ]); ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
        </section>
    </div>
</div>

<!-- Enhanced Share System JS -->
<script src="/share/enhanced-ion-share.js"></script>

<!-- Video Reactions System JS -->
    <script src="/login/modal.js"></script>
    <script src="/app/video-reactions.js"></script>

<!-- Debug: Comprehensive reaction button state verification -->
<script>
    // Debug: Comprehensive reaction button state verification
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            console.log('\n========== REACTION BUTTON DEBUG REPORT ==========');
            
            // Check all reaction containers
            const containers = document.querySelectorAll('.video-reactions[data-video-id]');
            console.log(`📦 Found ${containers.length} reaction container(s)`);
            
            containers.forEach((container, index) => {
                const videoId = container.dataset.videoId;
                const userAction = container.dataset.userAction;
                console.log(`\n🎬 Video #${index + 1} - ID: ${videoId}`);
                console.log(`   data-user-action: "${userAction}" (length: ${userAction ? userAction.length : 0})`);
                
                const likeBtn = container.querySelector('.like-btn');
                const dislikeBtn = container.querySelector('.dislike-btn');
                
                if (likeBtn) {
                    const likeActive = likeBtn.classList.contains('active');
                    const likeStyle = window.getComputedStyle(likeBtn);
                    console.log(`   👍 LIKE:`, {
                        hasActive: likeActive,
                        classes: likeBtn.className,
                        color: likeStyle.color,
                        background: likeStyle.backgroundColor,
                        border: likeStyle.borderColor
                    });
                }
                
                if (dislikeBtn) {
                    const dislikeActive = dislikeBtn.classList.contains('active');
                    const dislikeStyle = window.getComputedStyle(dislikeBtn);
                    console.log(`   👎 DISLIKE:`, {
                        hasActive: dislikeActive,
                        classes: dislikeBtn.className,
                        color: dislikeStyle.color,
                        background: dislikeStyle.backgroundColor,
                        border: dislikeStyle.borderColor
                    });
                }
            });
            
            // CSS Test
            const testDiv = document.createElement('div');
            testDiv.className = 'reaction-btn like-btn active';
            testDiv.style.display = 'none';
            document.body.appendChild(testDiv);
            const testStyle = window.getComputedStyle(testDiv);
            console.log(`\n🧪 CSS TEST (should show green):`, {
                color: testStyle.color,
                background: testStyle.backgroundColor
            });
            document.body.removeChild(testDiv);
            
            console.log('================================================\n');
        }, 1500);
    });
</script>

<script>
// Enhanced mobile scroll experience
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.querySelector('.grid');
    const scrollHint = document.querySelector('.scroll-hint');
    
    if (grid && scrollHint && window.innerWidth <= 767) {
        let hasScrolled = false;
        
        // Hide scroll hint after user scrolls
        grid.addEventListener('scroll', function() {
            if (!hasScrolled) {
                hasScrolled = true;
                scrollHint.style.display = 'none';
            }
        }, { once: true });
        
        // Auto-hide scroll hint after 5 seconds
        setTimeout(() => {
            if (scrollHint && !hasScrolled) {
                scrollHint.style.opacity = '0';
                scrollHint.style.transition = 'opacity 0.5s ease';
            }
        }, 5000);
    }
    
    // Initialize hover preview for video thumbnails
    initializeVideoHoverPreviews();
});

// Video hover preview functionality
function initializeVideoHoverPreviews() {
    const videoThumbs = document.querySelectorAll('.video-thumb-container');
    
    videoThumbs.forEach(thumb => {
        const previewUrl = thumb.getAttribute('data-preview-url');
        if (!previewUrl) return; // Skip if no preview URL
        
        let previewContainer = null;
        let previewLoaded = false;
        let hoverTimeout = null;
        
        // Load preview on hover (with delay)
        thumb.addEventListener('mouseenter', function(e) {
            // Don't load preview on mobile
            if (window.innerWidth <= 768) return;
            
            hoverTimeout = setTimeout(() => {
                // CRITICAL FIX: Check if iframe/video was stopped and needs to be restarted
                if (previewContainer) {
                    const existingIframe = previewContainer.querySelector('iframe');
                    if (existingIframe && existingIframe.dataset.originalSrc && existingIframe.src.includes('about:blank')) {
                        // Restart the stopped iframe
                        existingIframe.src = existingIframe.dataset.originalSrc;
                        console.log('🔄 Restarting stopped iframe on hover');
                        delete existingIframe.dataset.originalSrc; // Clear the flag
                        previewContainer.style.opacity = '1';
                        return;
                    }
                    
                    // Also check for stopped video elements
                    const videoElement = previewContainer.querySelector('video');
                    if (videoElement && videoElement.paused) {
                        videoElement.play().catch(err => console.log('⚠️ Video restart failed:', err));
                        previewContainer.style.opacity = '1';
                        return;
                    }
                }
                
                if (!previewLoaded) {
                    // Create preview container if it doesn't exist
                    if (!previewContainer) {
                        previewContainer = document.createElement('div');
                        previewContainer.className = 'preview-iframe-container';
                        thumb.appendChild(previewContainer);
                        
                        // Check if this is a local video (starts with "local:")
                        if (previewUrl.startsWith('local:')) {
                            // Create HTML5 video element for local videos
                            const videoUrl = previewUrl.substring(6); // Remove "local:" prefix
                            const video = document.createElement('video');
                            video.src = videoUrl;
                            video.muted = true;
                            video.autoplay = true;
                            video.loop = true;
                            video.playsInline = true;
                            video.style.cssText = 'width: 100%; height: 100%; object-fit: cover; border-radius: inherit;';
                            
                            // Attempt to play
                            video.play().catch(err => {
                                console.log('⚠️ Local video autoplay failed:', err);
                            });
                            
                            previewContainer.appendChild(video);
                        } else {
                            // Load iframe for external platforms
                            const iframe = document.createElement('iframe');
                            iframe.src = previewUrl;
                            iframe.frameBorder = '0';
                            iframe.allow = 'autoplay; encrypted-media';
                            iframe.allowFullscreen = true;
                            iframe.style.cssText = 'width: 100%; height: 100%; border: none; border-radius: inherit;';
                            previewContainer.appendChild(iframe);
                        }
                    }
                    previewLoaded = true;
                }
                
                // Show the preview
                if (previewContainer) {
                    previewContainer.style.opacity = '1';
                }
                
                // Hide play button overlay when video is playing
                const playIcon = thumb.querySelector('.play-icon-overlay');
                if (playIcon) {
                    playIcon.style.opacity = '0';
                    playIcon.style.pointerEvents = 'none';
                }
            }, 300); // 300ms delay
        });
        
        // Hide preview on mouse leave and STOP playback
        thumb.addEventListener('mouseleave', function() {
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
            
            // Show play button overlay again when video stops
            const playIcon = thumb.querySelector('.play-icon-overlay');
            if (playIcon) {
                playIcon.style.opacity = '1';
                playIcon.style.pointerEvents = 'auto';
            }
            
            if (previewContainer) {
                previewContainer.style.opacity = '0';
                
                // CRITICAL FIX: Stop video/iframe playback to prevent audio from continuing
                setTimeout(() => {
                    // Only stop if still hidden (user didn't hover back within 500ms)
                    if (previewContainer.style.opacity === '0') {
                        // Stop video elements
                        const videoElement = previewContainer.querySelector('video');
                        if (videoElement) {
                            videoElement.pause();
                            videoElement.currentTime = 0;
                        }
                        
                        // Stop iframe by clearing src (most reliable way to stop iframe audio)
                        const iframeElement = previewContainer.querySelector('iframe');
                        if (iframeElement) {
                            const originalSrc = iframeElement.src;
                            iframeElement.src = 'about:blank';  // Stop playback immediately
                            iframeElement.dataset.originalSrc = originalSrc;
                        }
                    }
                }, 500); // 500ms delay to avoid stopping if user quickly hovers back
            }
        });
    });
}
</script>

<!-- Pollybot Chatbot Widget -->
<script 
  src="https://pollybot.app/widget-packages.min.js"
  data-chatbot-id="cmgo2cfvr0003np2yvvozxdfb"
  data-widget-id="cmgo2cfvx0005np2ymynim8ad"
></script>

</body>
</html>