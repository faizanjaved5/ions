<?php
/**
 * ION Categories Configuration
 * 
 * Centralized list of all ION video categories used across the platform.
 * This file is included in various places like video upload forms, search filters, etc.
 * 
 * Format: 'value' => 'Display Name'
 */

// Define all ION categories
$ion_categories = [
    'Beauty' => 'ION Beauty™ Network',
    'Business' => 'ION Business Network',
    'College' => 'ION College Network',
    'Comedy' => 'ION Comedy Network',
    'Events' => 'ION Events™ Network',
    'Family' => 'ION Family™ Network',
    'Fans' => 'ION Fans™ Network',
    'Health' => 'ION Health Network',
    'Home' => 'ION Home™ Network',
    'Kids' => 'ION Kids Network',
    'Local' => 'ION Local Network',
    'Music' => 'ION Music Network',
    'News' => 'ION News™ Network',
    'Nutrition' => 'ION Nutrition™ Network',
    'Pets' => 'ION Pets Network',
    'Sports' => 'ION Sports Network',
    'Travel' => 'ION Travel™ Network',
    'Education' => 'ION Education™ Network',
    'Entertainment' => 'ION Entertainment™ Network',
    'Technology' => 'ION Technology™ Network',
    'Hall of Fame Show' => 'Hall of Fame Show on ION',
    'Leagues on ION' => 'Leagues on ION',
    'National Bartender League' => 'National Bartender League (NBL) on ION',
    'National Pizza League' => 'National Pizza League (NPL) on ION',
    'Other' => 'Other'
];

/**
 * Get all ION categories
 * 
 * @return array Associative array of categories (value => display_name)
 */
function get_ion_categories() {
    global $ion_categories;
    return $ion_categories;
}

/**
 * Get category display name by value
 * 
 * @param string $value Category value
 * @return string Display name or the value itself if not found
 */
function get_ion_category_name($value) {
    global $ion_categories;
    return $ion_categories[$value] ?? $value;
}

/**
 * Generate HTML options for category select dropdown
 * 
 * @param string $selected_value Currently selected value
 * @param bool $include_all_option Whether to include "All Categories" option
 * @return string HTML option elements
 */
function generate_ion_category_options($selected_value = '', $include_all_option = true) {
    global $ion_categories;
    
    $options = '';
    
    if ($include_all_option) {
        $options .= '<option value="">All Categories</option>' . "\n";
    }
    
    foreach ($ion_categories as $value => $display_name) {
        $selected = ($selected_value === $value) ? 'selected' : '';
        $options .= sprintf(
            '<option value="%s" %s>%s</option>' . "\n",
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            $selected,
            htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8')
        );
    }
    
    return $options;
}

/**
 * Check if a category value is valid
 * 
 * @param string $value Category value to check
 * @return bool True if valid category, false otherwise
 */
function is_valid_ion_category($value) {
    global $ion_categories;
    return array_key_exists($value, $ion_categories);
}
?>
