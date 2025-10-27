<?php
/**
 * ION Networks Configuration
 * 
 * Centralized list of all ION Networks (Shows & Branded Content) used across the platform.
 * Networks are hierarchical (can have parent-child relationships up to 2 levels deep).
 * This is separate from Categories which are high-level content types (Business, Sports, etc.)
 * 
 * Data is now dynamically loaded from the IONNetworks database table.
 */

// Global variable to cache loaded networks
$ion_networks = null;
$ion_networks_flat = null;

/**
 * Load networks from database
 * 
 * @return array Hierarchical networks array
 */
function load_ion_networks_from_db() {
    global $ion_networks, $ion_networks_flat;
    
    // Return cached version if already loaded
    if ($ion_networks !== null) {
        return $ion_networks;
    }
    
    // Initialize database connection
    try {
        // Try to use existing connection first
        if (isset($GLOBALS['wpdb']) && $GLOBALS['wpdb'] instanceof IONDatabase) {
            $db = $GLOBALS['wpdb'];
        } else {
            // Create new connection
            require_once __DIR__ . '/../config/database.php';
            $db = new IONDatabase();
        }
        
        // Fetch all networks ordered by level and name
        $query = "SELECT id, network_key, network_name, slug, level, parent_id, description, icon 
                  FROM IONNetworks 
                  ORDER BY level ASC, network_name ASC";
        $results = $db->get_results($query);
        
        if (!$results) {
            error_log('⚠️ No networks found in IONNetworks table');
            return [];
        }
        
        // Build networks array indexed by network_key
        $networks_by_key = [];
        $networks_by_id = [];
        
        foreach ($results as $row) {
            $key = $row->network_key;
            $networks_by_key[$key] = [
                'id' => (int)$row->id,
                'name' => $row->network_name,
                'slug' => $row->slug,
                'level' => (int)$row->level,
                'parent' => null, // Will be filled in next step
                'parent_id' => $row->parent_id ? (int)$row->parent_id : null,
                'description' => $row->description,
                'icon' => $row->icon,
                'children' => []
            ];
            $networks_by_id[(int)$row->id] = $key;
        }
        
        // Second pass: Set parent keys and build hierarchy
        $root_networks = [];
        foreach ($networks_by_key as $key => &$network) {
            if ($network['parent_id']) {
                // Find parent key by parent_id
                $parent_key = $networks_by_id[$network['parent_id']] ?? null;
                if ($parent_key) {
                    $network['parent'] = $parent_key;
                    // Add to parent's children
                    $networks_by_key[$parent_key]['children'][$key] = &$network;
                }
            } else {
                // Root level network
                $root_networks[$key] = &$network;
            }
        }
        unset($network); // Break reference
        
        // Cache both hierarchical and flat versions
        $ion_networks = $root_networks;
        $ion_networks_flat = $networks_by_key;
        
        return $root_networks;
        
    } catch (Exception $e) {
        error_log('❌ Error loading networks from database: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all ION networks (flattened)
 * 
 * @return array Associative array of networks (key => network_data)
 */
function get_ion_networks() {
    global $ion_networks_flat;
    
    // Load from database if not already loaded
    if ($ion_networks_flat === null) {
        load_ion_networks_from_db();
    }
    
    return $ion_networks_flat ?? [];
}

/**
 * Flatten hierarchical networks array into a single-level array
 * 
 * @param array $networks Hierarchical networks array
 * @param array $result Accumulated result (used in recursion)
 * @return array Flattened array
 */
function flatten_networks($networks, &$result = []) {
    foreach ($networks as $key => $network) {
        $children = $network['children'] ?? [];
        unset($network['children']); // Don't include children in the flattened version
        $result[$key] = $network;
        
        if (!empty($children)) {
            flatten_networks($children, $result);
        }
    }
    return $result;
}

/**
 * Get network data by key
 * 
 * @param string $key Network key
 * @return array|null Network data or null if not found
 */
function get_ion_network($key) {
    $networks = get_ion_networks();
    return $networks[$key] ?? null;
}

/**
 * Get network display name by key
 * 
 * @param string $key Network key
 * @return string Display name or the key itself if not found
 */
function get_ion_network_name($key) {
    $network = get_ion_network($key);
    return $network['name'] ?? $key;
}

/**
 * Get all root-level networks (level 0)
 * 
 * @return array Array of root networks
 */
function get_root_ion_networks() {
    global $ion_networks;
    
    // Load from database if not already loaded
    if ($ion_networks === null) {
        load_ion_networks_from_db();
    }
    
    return $ion_networks ?? [];
}

/**
 * Get child networks of a specific parent
 * 
 * @param string $parent_key Parent network key
 * @return array Array of child networks
 */
function get_child_ion_networks($parent_key) {
    global $ion_networks;
    return $ion_networks[$parent_key]['children'] ?? [];
}

/**
 * Generate HTML options for network select dropdown (hierarchical)
 * 
 * @param array $selected_keys Array of currently selected network keys
 * @param bool $include_none_option Whether to include "None" option
 * @param string $indent Indentation for hierarchical display (used in recursion)
 * @param array $networks Networks array to process (default: root networks)
 * @return string HTML option elements
 */
function generate_ion_network_options($selected_keys = [], $include_none_option = true, $indent = '', $networks = null) {
    if ($networks === null) {
        global $ion_networks;
        $networks = $ion_networks;
    }
    
    if (!is_array($selected_keys)) {
        $selected_keys = empty($selected_keys) ? [] : [$selected_keys];
    }
    
    $options = '';
    
    // Add "None" option only at the top level
    if ($include_none_option && $indent === '') {
        $options .= '<option value="">None</option>' . "\n";
    }
    
    foreach ($networks as $key => $network) {
        $selected = in_array($key, $selected_keys) ? 'selected' : '';
        $display_name = $indent . ($network['icon'] ?? '') . ' ' . $network['name'];
        
        $options .= sprintf(
            '<option value="%s" %s>%s</option>' . "\n",
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
            $selected,
            htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8')
        );
        
        // Recursively add children with indentation
        if (!empty($network['children'])) {
            $options .= generate_ion_network_options(
                $selected_keys,
                false,
                $indent . '&nbsp;&nbsp;&nbsp;&nbsp;',
                $network['children']
            );
        }
    }
    
    return $options;
}

/**
 * Generate HTML checkboxes for network selection (hierarchical)
 * 
 * @param array $selected_keys Array of currently selected network keys
 * @param string $name Form input name (will use array notation)
 * @param string $indent Indentation for hierarchical display (used in recursion)
 * @param array $networks Networks array to process (default: root networks)
 * @return string HTML checkbox elements
 */
function generate_ion_network_checkboxes($selected_keys = [], $name = 'networks', $indent = '', $networks = null) {
    if ($networks === null) {
        global $ion_networks;
        $networks = $ion_networks;
    }
    
    if (!is_array($selected_keys)) {
        $selected_keys = empty($selected_keys) ? [] : [$selected_keys];
    }
    
    $html = '';
    
    foreach ($networks as $key => $network) {
        $checked = in_array($key, $selected_keys) ? 'checked' : '';
        $display_name = $indent . ($network['icon'] ?? '') . ' ' . $network['name'];
        $indent_style = str_repeat('&nbsp;', strlen($indent) * 2);
        
        $html .= sprintf(
            '<div style="margin-left: %spx;"><label style="display: flex; align-items: center; gap: 8px; padding: 4px 0; cursor: pointer;">' .
            '<input type="checkbox" name="%s[]" value="%s" %s style="cursor: pointer;">' .
            '<span>%s</span>' .
            '</label></div>' . "\n",
            (strlen($indent) * 10),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
            $checked,
            htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8')
        );
        
        // Recursively add children with indentation
        if (!empty($network['children'])) {
            $html .= generate_ion_network_checkboxes(
                $selected_keys,
                $name,
                $indent . '──',
                $network['children']
            );
        }
    }
    
    return $html;
}

/**
 * Check if a network key is valid
 * 
 * @param string $key Network key to check
 * @return bool True if valid network, false otherwise
 */
function is_valid_ion_network($key) {
    $networks = get_ion_networks();
    return array_key_exists($key, $networks);
}

/**
 * Get network hierarchy path (breadcrumb)
 * 
 * @param string $key Network key
 * @return array Array of network keys from root to current
 */
function get_ion_network_path($key) {
    $network = get_ion_network($key);
    if (!$network) {
        return [];
    }
    
    $path = [$key];
    $current_key = $key;
    
    // Walk up the parent chain
    while ($network && !empty($network['parent'])) {
        $parent_key = $network['parent'];
        array_unshift($path, $parent_key);
        $network = get_ion_network($parent_key);
        $current_key = $parent_key;
    }
    
    return $path;
}

/**
 * Get network hierarchy path as display names
 * 
 * @param string $key Network key
 * @param string $separator Separator between names
 * @return string Formatted hierarchy path
 */
function get_ion_network_path_display($key, $separator = ' > ') {
    $path = get_ion_network_path($key);
    $names = array_map('get_ion_network_name', $path);
    return implode($separator, $names);
}

// Auto-load networks from database when this file is included
load_ion_networks_from_db();

?>

