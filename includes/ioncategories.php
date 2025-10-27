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
// NOTE: Categories are high-level content types (Business, Sports, News, etc.)
// For Shows & Branded Content, see ionnetworks.php instead
$ion_categories = [
    'ION Beauty Network'	     =>   'ION Beauty™ Network',
    'ION Business Network'	     =>   'ION Business™ Network',
    'ION College Network'	     =>   'ION Campus™ Network',
    'ION Comedy Network'	     =>   'ION Comedy™ Network',
    'ION Crime Network'	         =>   'ION Crime™ Network',
    'ION Education Network'	     =>   'ION Education™ Network',
    'ION Entertainment Network'	 =>   'ION Entertainment™ Network',
    'ION Events Network'	     =>   'ION Events™ Network',
    'ION Faith Network'	         =>   'ION Faith™ Network',
    'ION Family Network'	     =>   'ION Family™ Network',
    'ION Fans Network'	         =>   'ION Fans™ Network',
    'ION Fitness Network'	     =>   'ION Fitness™ Network',
    'ION Games Network'	         =>   'ION Games™ Network',
    'ION Government Network'	 =>   'ION Government™ Network',
    'ION Health Network'	     =>   'ION Health™ Network',
    'ION Home Network'	         =>   'ION Home™ Network',
    'ION Kids Network'       	 =>   'ION Kids™ Network',
    'ION Law Network'	         =>   'ION Law™ Network',
    'ION Local Network'    	     =>   'ION Local™ Network',
    'ION Military Network'	     =>   'ION Military™ Network',
    'ION Movies Network'	     =>   'ION Movies™ Network',
    'ION Music Network'	         =>   'ION Music™ Network',
    'ION News Network'	         =>   'ION News™ Network',
    'ION Nutrition Network'	     =>   'ION Nutrition™ Network',
    'ION Pets Network'	         =>   'ION Pets™ Network',
    'ION Real Estate Network'	 =>   'ION Real Estate™ Network',
    'ION Senior Network'	     =>   'ION Senior™ Network',
    'ION Sports Network'	     =>   'ION Sports™ Network',
    'ION Technology Network'	 =>   'ION Technology™ Network',
    'ION Television Network'	 =>   'ION Television™ Network',
    'ION Travel Network'	     =>   'ION Travel™ Network',
    'General'                    =>   'ION General'
];

$ion_category_slugs = [
    'Leagues ON ION'              => 	'leagues-on-ion',
    'ION Awards™ Network'         => 	'ion-awards-network',
    'ION Baby™ Network'           => 	'ion-baby-network',
    'ION Beauty™ Network'         => 	'ion-beauty-network',
    'ION Business™ Network'       => 	'ion-business-network',
    'ION Campus™ Network'         => 	'ion-campus-network',
    'ION Comedy™ Network'         => 	'ion-comedy-network',
    'ION Crime™ Network'          => 	'ion-crime-network',
    'ION Education™ Network'      => 	'ion-education-network',
    'ION Entertainment™ Network'  => 	'ion-entertainment-network',
    'ION Events™ Network'         => 	'ion-events-network',
    'ION Faith™ Network'          => 	'ion-faith-network',
    'ION Family™ Network'         => 	'ion-family-network',
    'ION Fans™ Network'           => 	'ion-fans-network',
    'ION Fitness™ Network'        => 	'ion-fitness-network',
    'ION Games™ Network'          => 	'ion-games-network',
    'ION Government™ Network'     => 	'ion-government-network',
    'ION Health™ Network'         => 	'ion-health-network',
    'ION Home™ Network'           => 	'ion-home-network',
    'ION Kids™ Network'           => 	'ion-kids-network',
    'ION Law™ Network'            => 	'ion-law-network',
    'ION Local™ Network'          => 	'ion-local-network',
    'ION Military™ Network'       => 	'ion-military-network',
    'ION Movies™ Network'         => 	'ion-movies-network',
    'ION Music™ Network'          => 	'ion-music-network',
    'ION News™ Network'           => 	'ion-news-network',
    'ION Nutrition™ Network'      => 	'ion-nutrition-network',
    'ION Pets™ Network'           => 	'ion-pets-network',
    'ION Real Estate™ Network'    => 	'ion-real-estate-network',
    'ION Senior™ Network'         => 	'ion-senior-network',
    'ION Sports™ Network'         => 	'ion-sports-network',
    'ION Technology™ Network'     => 	'ion-technology-network',
    'ION Television™ Network'     => 	'ion-television-network',
    'ION Travel™ Network'         => 	'ion-travel-network',
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

/**
 * Get category slug from category name
 * Uses the ion_category_slugs lookup array with fuzzy matching
 * 
 * @param string $categoryName Category name (e.g., "ION Local", "ION Local™ Network")
 * @return string|null Slug if found, null if not found
 */
function get_ion_category_slug($categoryName) {
    global $ion_category_slugs;
    
    // Try exact match first
    if (isset($ion_category_slugs[$categoryName])) {
        return $ion_category_slugs[$categoryName];
    }
    
    // Try with variations
    $variations = [
        $categoryName . '™ Network',  // ION Local → ION Local™ Network
        $categoryName . ' Network',    // ION Local → ION Local Network
        $categoryName . '™',           // ION Local → ION Local™
    ];
    
    foreach ($variations as $variation) {
        if (isset($ion_category_slugs[$variation])) {
            return $ion_category_slugs[$variation];
        }
    }
    
    // Not found
    return null;
}

/**
 * Get all category slugs
 * 
 * @return array Associative array of category names => slugs
 */
function get_ion_category_slugs() {
    global $ion_category_slugs;
    return $ion_category_slugs;
}

/**
 * Get category display name from slug (reverse lookup)
 * 
 * @param string $slug Category slug (e.g., "ion-local-network")
 * @return string|null Display name if found, null if not found
 */
function get_ion_category_name_from_slug($slug) {
    global $ion_category_slugs;
    
    // Create reverse lookup (slug => name)
    static $reverse_lookup = null;
    if ($reverse_lookup === null) {
        $reverse_lookup = array_flip($ion_category_slugs);
    }
    
    // Normalize slug to lowercase
    $slug = strtolower($slug);
    
    return $reverse_lookup[$slug] ?? null;
}
?>
