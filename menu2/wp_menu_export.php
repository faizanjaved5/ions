<?php
/**
 * WordPress Menu Exporter
 * Extracts all menu information including labels, URLs, and parent-child relationships
 * Outputs structured JSON
 */
declare(strict_types=1);

// Database Configuration - Hardcoded
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u185424179_WtONJ');
define('DB_USER', 'u185424179_S4e4w');
define('DB_PASS', '04JE8wHMrl');

define('WP_TABLE_PREFIX', 'wp_7_'); // adjust if you have a custom prefix

/**
 * Get PDO connection to WordPress database
 */
function get_wp_pdo(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    
    try {
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Fetch all menus and their items from the database
 */
function fetch_all_menus(PDO $pdo): array {
    $prefix = WP_TABLE_PREFIX;
    
    // Query to get all menus with their items and metadata
    $sql = "
        SELECT 
            t.term_id AS menu_id,
            t.name AS menu_name,
            t.slug AS menu_slug,
            p.ID AS item_id,
            p.post_title AS item_title,
            p.menu_order AS item_order,
            pm_parent.meta_value AS parent_id,
            pm_type.meta_value AS item_type,
            pm_object.meta_value AS object_type,
            pm_object_id.meta_value AS object_id,
            pm_url.meta_value AS custom_url,
            pm_target.meta_value AS target,
            pm_classes.meta_value AS css_classes,
            pm_xfn.meta_value AS xfn
        FROM {$prefix}terms t
        INNER JOIN {$prefix}term_taxonomy tt 
            ON t.term_id = tt.term_id
        INNER JOIN {$prefix}term_relationships tr 
            ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$prefix}posts p 
            ON tr.object_id = p.ID
        LEFT JOIN {$prefix}postmeta pm_parent 
            ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_menu_item_menu_item_parent'
        LEFT JOIN {$prefix}postmeta pm_type 
            ON p.ID = pm_type.post_id AND pm_type.meta_key = '_menu_item_type'
        LEFT JOIN {$prefix}postmeta pm_object 
            ON p.ID = pm_object.post_id AND pm_object.meta_key = '_menu_item_object'
        LEFT JOIN {$prefix}postmeta pm_object_id 
            ON p.ID = pm_object_id.post_id AND pm_object_id.meta_key = '_menu_item_object_id'
        LEFT JOIN {$prefix}postmeta pm_url 
            ON p.ID = pm_url.post_id AND pm_url.meta_key = '_menu_item_url'
        LEFT JOIN {$prefix}postmeta pm_target 
            ON p.ID = pm_target.post_id AND pm_target.meta_key = '_menu_item_target'
        LEFT JOIN {$prefix}postmeta pm_classes 
            ON p.ID = pm_classes.post_id AND pm_classes.meta_key = '_menu_item_classes'
        LEFT JOIN {$prefix}postmeta pm_xfn 
            ON p.ID = pm_xfn.post_id AND pm_xfn.meta_key = '_menu_item_xfn'
        WHERE 
            tt.taxonomy = 'nav_menu'
            AND p.post_type = 'nav_menu_item'
            AND p.post_status = 'publish'
        ORDER BY 
            t.term_id, 
            p.menu_order, 
            p.ID
    ";
    echo $sql;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    return $rows;
}

/**
 * Get the actual URL for a menu item
 */
function get_menu_item_url(PDO $pdo, array $item): string {
    $prefix = WP_TABLE_PREFIX;
    
    // If it's a custom link, return the custom URL
    if ($item['item_type'] === 'custom') {
        return $item['custom_url'] ?: '#';
    }
    
    // For post types (pages, posts, etc.)
    if ($item['item_type'] === 'post_type' && $item['object_id']) {
        $stmt = $pdo->prepare("
            SELECT guid, post_name 
            FROM {$prefix}posts 
            WHERE ID = :id
        ");
        $stmt->execute(['id' => $item['object_id']]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Try to build a proper permalink
            return $post['guid'];
        }
    }
    
    // For taxonomies (categories, tags, etc.)
    if ($item['item_type'] === 'taxonomy' && $item['object_id']) {
        $stmt = $pdo->prepare("
            SELECT t.slug 
            FROM {$prefix}terms t
            WHERE t.term_id = :id
        ");
        $stmt->execute(['id' => $item['object_id']]);
        $term = $stmt->fetch();
        
        if ($term) {
            // Return a relative URL
            return '/' . $item['object_type'] . '/' . $term['slug'];
        }
    }
    
    return $item['custom_url'] ?: '#';
}

/**
 * Build hierarchical menu structure
 */
function build_menu_hierarchy(PDO $pdo, array $items): array {
    $menus = [];
    $itemsById = [];
    
    // Group items by menu and index by item_id
    foreach ($items as $item) {
        $menuId = $item['menu_id'];
        
        if (!isset($menus[$menuId])) {
            $menus[$menuId] = [
                'menu_id' => $item['menu_id'],
                'menu_name' => $item['menu_name'],
                'menu_slug' => $item['menu_slug'],
                'items' => []
            ];
        }
        
        $itemData = [
            'item_id' => (int)$item['item_id'],
            'label' => $item['item_title'],
            'url' => get_menu_item_url($pdo, $item),
            'order' => (int)$item['item_order'],
            'parent_id' => (int)($item['parent_id'] ?: 0),
            'type' => $item['item_type'],
            'object_type' => $item['object_type'],
            'target' => $item['target'] ?: '_self',
            'css_classes' => $item['css_classes'] ? unserialize($item['css_classes']) : [],
            'children' => []
        ];
        
        $itemsById[$item['item_id']] = $itemData;
        $menus[$menuId]['items'][] = $itemData;
    }
    
    // Build parent-child relationships for each menu
    foreach ($menus as &$menu) {
        $hierarchical = [];
        $indexed = [];
        
        // First pass: index all items
        foreach ($menu['items'] as $item) {
            $indexed[$item['item_id']] = $item;
        }
        
        // Second pass: build hierarchy
        foreach ($indexed as $itemId => $item) {
            if ($item['parent_id'] == 0) {
                // Top-level item
                $hierarchical[] = &$indexed[$itemId];
            } else {
                // Child item - add to parent's children array
                if (isset($indexed[$item['parent_id']])) {
                    $indexed[$item['parent_id']]['children'][] = &$indexed[$itemId];
                } else {
                    // Orphaned item (parent doesn't exist), add to top level
                    $hierarchical[] = &$indexed[$itemId];
                }
            }
        }
        
        $menu['items'] = $hierarchical;
    }
    
    return array_values($menus);
}

/**
 * Get menu locations (theme menu assignments)
 */
function get_menu_locations(PDO $pdo): array {
    $prefix = WP_TABLE_PREFIX;
    
    // Get theme mod for nav menu locations
    $stmt = $pdo->prepare("
        SELECT option_value 
        FROM {$prefix}options 
        WHERE option_name = 'theme_mods_' 
           OR option_name LIKE 'theme_mods_%'
        ORDER BY option_id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result && $result['option_value']) {
        $themeMods = unserialize($result['option_value']);
        if (isset($themeMods['nav_menu_locations'])) {
            return $themeMods['nav_menu_locations'];
        }
    }
    
    return [];
}

// Main execution
try {
    header('Content-Type: application/json; charset=utf-8');
    
    $pdo = get_wp_pdo();
    $items = fetch_all_menus($pdo);
    $menus = build_menu_hierarchy($pdo, $items);
    $locations = get_menu_locations($pdo);
    
    // Add location information to menus
    foreach ($menus as &$menu) {
        $menu['assigned_locations'] = [];
        foreach ($locations as $location => $menuId) {
            if ($menuId == $menu['menu_id']) {
                $menu['assigned_locations'][] = $location;
            }
        }
    }
    
    $output = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_menus' => count($menus),
        'menus' => $menus
    ];
    
    // Pretty print JSON
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

