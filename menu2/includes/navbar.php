<?php
// Enable error reporting for debugging (but suppress Simple HTML DOM library warnings)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_WARNING);
/**
 * Simple Menu Display Script
 * Fetches menu items from ion_menus and IONmenu tables
 * Uses view template for presentation
 */

// Load configuration
require_once __DIR__ . '/../config.php';


// Database connection
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Fetch all parent menus from ion_menus (excluding empty names)
function getParentMenus($pdo) {
    $stmt = $pdo->query("SELECT * FROM ion_menus WHERE name IS NOT NULL AND TRIM(name) != '' ORDER BY id");
    return $stmt->fetchAll();
}

// Fetch menu items for a specific menu (excluding empty labels)
function getMenuItems($pdo, $menuId, $parentId = 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM IONmenu 
        WHERE menu_id = :menu_id 
        AND parent = :parent_id 
        AND label IS NOT NULL 
        AND TRIM(label) != ''
        ORDER BY position ASC, id ASC
    ");
    $stmt->execute([
        'menu_id' => $menuId,
        'parent_id' => $parentId
    ]);
    return $stmt->fetchAll();
}

// Recursively build menu tree with children
function buildMenuTree($pdo, $menuId, $parentId = 0) {
    $items = getMenuItems($pdo, $menuId, $parentId);
    $menuTree = [];
    
    foreach ($items as $item) {
        // Skip items with empty labels (double validation)
        $label = trim($item['label'] ?? '');
        if (empty($label)) {
            continue;
        }
        
        $menuItem = [
            'id' => $item['id'],
            'label' => $label,
            'url' => $item['url'] ?? '#',
            'target' => $item['target'] ?? '_self',
            'classes' => $item['classes'] ?? '',
            'children' => []
        ];
        
        // Recursively get children
        $children = getMenuItems($pdo, $menuId, $item['id']);
        if (!empty($children)) {
            $menuItem['children'] = buildMenuTree($pdo, $menuId, $item['id']);
        }
        
        $menuTree[] = $menuItem;
    }
    
    return $menuTree;
}

// Render menu children recursively (helper for view)
function renderMenuChildren($children, $level = 1) {
    if (empty($children)) {
        return '';
    }
    
    $html = '';
    foreach ($children as $child) {
        $html .= '<div class="child-menu-item">';
        $html .= '<a href="' . htmlspecialchars($child['url']) . '" ';
        $html .= 'class="menu-item-link" ';
        $html .= 'target="' . htmlspecialchars($child['target']) . '">';
        $html .= htmlspecialchars($child['label']);
        $html .= '</a>';
        
        if (!empty($child['children'])) {
            $html .= '<div class="child-menu-items">';
            $html .= renderMenuChildren($child['children'], $level + 1);
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

// Render mobile menu items recursively
function renderMobileMenuItems($items, $level = 0, $parentId = '') {
    if (empty($items)) {
        return '';
    }
    
    $html = '';
    foreach ($items as $index => $item) {
        $hasChildren = !empty($item['children']);
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $item['label']);
        $itemId = 'mobile-' . $parentId . $safeName . '_' . $index;
        
        $html .= '<div class="ion-mobile-menu-item">';
        
        if ($hasChildren) {
            $html .= '<div class="ion-mobile-menu-link" onclick="toggleMobileSubmenu(\'' . htmlspecialchars($itemId, ENT_QUOTES) . '\')">';
        } else {
            $html .= '<a href="' . htmlspecialchars($item['url']) . '" class="ion-mobile-menu-link" target="' . htmlspecialchars($item['target']) . '">';
        }
        
        // Highlight ION text in labels
        $label = htmlspecialchars($item['label']);
        $label = preg_replace('/ION/', '<span class="ion-text">ION</span>', $label);
        $html .= $label;
        
        if ($hasChildren) {
            $html .= '<svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>';
            $html .= '</svg>';
            $html .= '</div>'; // close ion-mobile-menu-link div
            
            $html .= '<div id="' . htmlspecialchars($itemId) . '" class="ion-mobile-submenu">';
            $html .= renderMobileMenuItems($item['children'], $level + 1, $safeName . '_');
            $html .= '</div>';
        } else {
            $html .= '</a>'; // close anchor tag
        }
        
        $html .= '</div>'; // close ion-mobile-menu-item
    }
    
    return $html;
}

// Get database connection
$pdo = getDbConnection();

// Fetch all parent menus
$parentMenus = getParentMenus($pdo);

// Build menu data structure for the view (excluding empty menus)
$menus = [];
foreach ($parentMenus as $parentMenu) {
    // Skip parent menus with empty names (double validation)
    $menuName = trim($parentMenu['name'] ?? '');
    if (empty($menuName)) {
        continue;
    }
    
    $menuItems = buildMenuTree($pdo, $parentMenu['id']);
    
    // Only add menu if it has items (optional - remove this if you want to show empty parent menus)
    // if (empty($menuItems)) {
    //     continue;
    // }
    
    $menus[] = [
        'id' => $parentMenu['id'],
        'name' => $menuName,
        'items' => $menuItems
    ];
}

// Prepare data for view
$pageTitle = 'ION Menu System';
$logoUrl = '/ion/omar/menu/ion-logo-gold.png';

// Load the view

// Load the view
require_once __DIR__ . '/../views/menu_view.php';