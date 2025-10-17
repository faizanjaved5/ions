<?php
/*
 * Shared Header Component for ION Application
 */

// Ensure required variables exist
if (!isset($user_preferences)) {
    $user_preferences = ['IONLogo' => '/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png'];
}
if (!isset($user_role)) {
    $user_role = 'Guest';
}
if (!isset($user_fullname)) {
    $user_fullname = '';
}

// Variables needed by mobile_filters.php (set safe defaults)
if (!isset($view)) $view = 'grid';
if (!isset($sort)) $sort = 'city_name';
if (!isset($status_filter)) $status_filter = '';
if (!isset($country_filter)) $country_filter = '';
if (!isset($state_filter)) $state_filter = '';
if (!isset($search)) $search = '';
if (!isset($countries)) $countries = [];
if (!isset($status_counts)) $status_counts = [
    'live' => 0, 'preview' => 0, 'static' => 0, 'draft' => 0, 
    'error' => 0, 'cf-active' => 0, 'cf-missing' => 0
];
if (!isset($wpdb)) $wpdb = null;
if (!isset($table)) $table = '';
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('get_flag_emoji')) {
    function get_flag_emoji($country_code) {
        if (strlen($country_code ?? '') !== 2) {
            return '🌍'; // fallback emoji
        }
        
        // Convert country code to flag emoji
        $country_code = strtoupper($country_code);
        $flag = '';
        
        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(127397 + ord($country_code[$i]), 'UTF-8');
        }
        
        return $flag;
    }
}

// Include role-based access control
require_once '../login/roles.php';

// Set defaults if not provided
$title                  = $header_config['title'                 ] ?? 'ION Directory';
$search_placeholder     = $header_config['search_placeholder'    ] ?? 'Search';
$search_value           = $header_config['search_value'          ] ?? '';
$active_tab             = $header_config['active_tab'            ] ?? 'IONS';
$button_text            = $header_config['button_text'           ] ?? '+ Add Item';
$button_id              = $header_config['button_id'             ] ?? 'add-btn';
$button_onclick         = $header_config['button_onclick'        ] ?? '';
$button_class           = $header_config['button_class'          ] ?? '';
$show_button            = $header_config['show_button'           ] ?? true;
$additional_form_fields = $header_config['additional_form_fields'] ?? '';
$mobile_button_text     = $header_config['mobile_button_text'    ] ?? $button_text;

// Determine the correct home link based on current page
$current_page = basename($_SERVER['PHP_SELF']);
$logo_link = 'directory.php'; // Default

switch($current_page) {
    case 'directory.php':
        $logo_link = 'directory.php';
        break;
    case 'ioneers.php':
        $logo_link = 'ioneers.php';
        break;
    case 'creators.php':
        $logo_link = 'creators.php';
        break;
    case 'ionsights.php':
        $logo_link = 'ionsights.php';
        break;
    default:
        // For other pages, link to directory.php
        $logo_link = 'directory.php';
        break;
}
?>

<div class="header">
    <div class="header-content">
        <a href="<?= $logo_link ?>" class="logo-link">
            <img src="<?= htmlspecialchars($user_preferences['IONLogo']) ?>" alt="ION Logo" class="header-logo">
        </a>
        <div class="header-main">
            <h1><?= htmlspecialchars($title) ?></h1>
            <p>The Network of Champions Directory</p>
            <form class="search-bar" id="search-form" action="" method="get">
                <input type="text" name="q" id="search-input" placeholder="<?= htmlspecialchars($search_placeholder) ?>" value="<?= h($search_value) ?>">
                <button type="submit">Search</button>
                <?= $additional_form_fields ?>
                <!-- Preserve current filters when searching (exclude draft status, case-insensitive) -->
                <?php if (isset($_GET['status']) && $_GET['status'] && strtolower($_GET['status']) !== 'draft'): ?>
                    <input type="hidden" name="status" value="<?= h($_GET['status']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['uploader']) && $_GET['uploader']): ?>
                    <input type="hidden" name="uploader" value="<?= h($_GET['uploader']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['type']) && $_GET['type']): ?>
                    <input type="hidden" name="type" value="<?= h($_GET['type']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['sort']) && $_GET['sort']): ?>
                    <input type="hidden" name="sort" value="<?= h($_GET['sort']) ?>">
                <?php endif; ?>
            </form>
        </div>
        <div class="header-right">
            <div class="header-top-row">
                            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <?php if (IONRoles::showNavigation($user_role, 'IONS')): ?>
                    <a href="directory.php" class="nav-tab <?= $active_tab === 'IONS' ? 'active' : '' ?>">IONS</a>
                <?php endif; ?>
                <?php if (IONRoles::showNavigation($user_role, 'IONEERS')): ?>
                    <a href="ioneers.php" class="nav-tab <?= $active_tab === 'IONEERS' ? 'active' : '' ?>">IONEERS</a>
                <?php endif; ?>
                <?php if (IONRoles::showNavigation($user_role, 'ION_VIDS')): ?>
                    <a href="creators.php" class="nav-tab <?= $active_tab === 'ION_VIDS' ? 'active' : '' ?>">ION VIDS</a>
                <?php endif; ?>
                <?php if (IONRoles::showNavigation($user_role, 'IONSIGHTS')): ?>
                    <a href="#" class="nav-tab <?= $active_tab === 'IONSIGHTS' ? 'active' : '' ?>" style="opacity: 0.5;">IONSIGHTS</a>
                <?php endif; ?>
            </div>
                <?php include 'usermenu.php'; ?>
            </div>
            <?php if ($show_button && IONRoles::canPerformAction($user_role, 'ION_VIDS', 'upload')): ?>
                <div class="header-actions">
                    <button id="<?= htmlspecialchars($button_id) ?>"
                            class="<?= htmlspecialchars($button_class) ?>"
                            <?= !empty($button_onclick) ? 'onclick="' . htmlspecialchars($button_onclick) . '"' : '' ?>>
                        <?= htmlspecialchars($button_text) ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="hamburger-menu" id="hamburger-menu">
        <div></div>
        <div></div>
        <div></div>
    </div>
    </div>
    <div class="mobile-menu" id="mobile-menu" onclick="closeMobileMenu()">
        <div class="mobile-menu-content" onclick="event.stopPropagation()">
            <!-- Mobile Navigation Tabs -->
            <div class="mobile-nav-tabs">
                <?php if (IONRoles::showNavigation($user_role, 'IONS')): ?>
                    <a href="directory.php" class="mobile-nav-tab <?= $active_tab === 'IONS' ? 'active' : '' ?>">IONS</a>
                <?php endif; ?>
                <?php if (IONRoles::showNavigation($user_role, 'IONEERS')): ?>
                    <a href="ioneers.php" class="mobile-nav-tab <?= $active_tab === 'IONEERS' ? 'active' : '' ?>">IONEERS</a>
                <?php endif; ?>
                <?php if (IONRoles::showNavigation($user_role, 'ION_VIDS') && $user_role !== 'Creator'): ?>
                    <a href="creators.php" class="mobile-nav-tab <?= $active_tab === 'ION_VIDS' ? 'active' : '' ?>">ION VIDS</a>
                <?php endif; ?>
                <?php if (IONRoles::showNavigation($user_role, 'IONSIGHTS')): ?>
                    <a href="#" class="mobile-nav-tab <?= $active_tab === 'IONSIGHTS' ? 'active' : '' ?>" style="opacity: 0.5;">IONSIGHTS</a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Action Button -->
            <?php if ($show_button): ?>
                <div class="mobile-action-section">
                    <button id="mobile-<?= htmlspecialchars($button_id) ?>" 
                            class="mobile-action-btn"
                            <?= !empty($button_onclick) ? 'onclick="' . htmlspecialchars($button_onclick) . '"' : '' ?>>
                        <?= htmlspecialchars($mobile_button_text) ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="mobile-user-menu">
                <div class="mobile-user-info">
                    <?php if (!empty($user_fullname)): ?>
                        <p class="mobile-user-fullname"><?= htmlspecialchars($user_fullname) ?></p>
                    <?php endif; ?>
                    <p class="mobile-user-email"><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                </div>
                <a href="/login/logout.php" class="mobile-menu-link">Log Out</a>
                <a href="?refresh_countries=true" class="mobile-menu-link">Clear Cache</a>
            </div>
            
            <?php if ($active_tab === 'IONS'): ?>
                <hr style="border-color: rgba(255, 255, 255, 0.2); margin: 1rem 0;">
                <h3 style="color: white; margin: 0 0 1rem 0;">Filter & Sort</h3>
                <?php include 'mobile_filters.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Ultra-simple mobile menu - maximum compatibility
var mobileMenuActive = false;

function toggleMobileMenu() {
    var mobileMenu = document.getElementById('mobile-menu');
    var hamburger = document.getElementById('hamburger-menu');
    
    if (!mobileMenu || !hamburger) {
        console.error('Mobile menu elements not found!');
        return;
    }
    
    if (mobileMenuActive) {
        mobileMenu.style.display = 'none';
        hamburger.innerHTML = '<div></div><div></div><div></div>';
        mobileMenuActive = false;
    } else {
        mobileMenu.style.display = 'block';
        hamburger.innerHTML = '<div style="transform: rotate(45deg) translate(6px, 6px);"></div><div style="opacity: 0;"></div><div style="transform: rotate(-45deg) translate(6px, -6px);"></div>';
        mobileMenuActive = true;
    }
}

// Close menu when clicking anywhere on the menu overlay
function closeMobileMenu() {
    var mobileMenu = document.getElementById('mobile-menu');
    var hamburger = document.getElementById('hamburger-menu');
    
    if (mobileMenu && hamburger) {
        mobileMenu.style.display = 'none';
        hamburger.innerHTML = '<div></div><div></div><div></div>';
        mobileMenuActive = false;
    }
}

// Initialize mobile menu
setTimeout(function() {
    var hamburger = document.getElementById('hamburger-menu');
    
    if (hamburger) {
        hamburger.onclick = function(e) {
            e.preventDefault();
            toggleMobileMenu();
        };
        hamburger.ontouchend = function(e) {
            e.preventDefault();
            toggleMobileMenu();
        };
        console.log('Mobile menu ready!');
    } else {
        console.error('Hamburger element not found!');
    }
}, 500);
</script>
<script src="location-selector.js"></script>
<script src="profile-dialog.js"></script>