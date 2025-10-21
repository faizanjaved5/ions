<?php
/**
 * ION Search Configuration
 * 
 * Defines search contexts, limits, and validation for the comprehensive search system
 */

class SearchConfig {
    // Search contexts
    const CONTEXT_END_USER = 'end_user';
    const CONTEXT_INTERNAL = 'internal';
    const CONTEXT_ADMIN = 'admin';
    
    // Default limits for each context
    const DEFAULT_LIMITS = [
        self::CONTEXT_END_USER => [
            'channels' => 50,
            'videos' => 30,
            'per_page' => 20,
            'max_radius' => 50
        ],
        self::CONTEXT_INTERNAL => [
            'channels' => 500,
            'videos' => 200,
            'per_page' => 100,
            'max_radius' => 100
        ],
        self::CONTEXT_ADMIN => [
            'channels' => 1000,
            'videos' => 500,
            'per_page' => 200,
            'max_radius' => 200
        ]
    ];
    
    // Search type configurations
    const SEARCH_TYPES = [
        'zip_code' => [
            'min_radius' => 5,
            'max_radius' => 200,
            'default_radius' => 30,
            'supports_custom_radius' => true
        ],
        'city_name' => [
            'min_radius' => 10,
            'max_radius' => 100,
            'default_radius' => 30,
            'supports_custom_radius' => true
        ],
        'domain' => [
            'min_radius' => 0,
            'max_radius' => 0,
            'default_radius' => 0,
            'supports_custom_radius' => false
        ],
        'text' => [
            'min_radius' => 0,
            'max_radius' => 0,
            'default_radius' => 0,
            'supports_custom_radius' => false
        ]
    ];
    
    // Status filter options
    const STATUS_FILTERS = [
        'live' => 'Live Channel',
        'preview' => 'Preview Page',
        'static' => 'Static WP',
        'draft' => 'Draft Pages',
        'error' => 'Has errors',
        'cf-active' => 'Cloudflare Active',
        'cf-missing' => 'Cloudflare Missing',
        'cf-inactive' => 'Cloudflare Inactive',
        'cf-pending' => 'Cloudflare Pending',
        'domain-linked' => 'Domain Linked',
        'domain-missing' => 'Domain Missing'
    ];
    
    // Sort options
    const SORT_OPTIONS = [
        'city_name' => 'Sort by City [A-Z]',
        'state_name' => 'Sort by State',
        'country_name' => 'Sort by Country',
        'population' => 'Sort by Population',
        'custom_domain' => 'Sort by Domain',
        'status' => 'Sort by Status',
        'distance' => 'Sort by Distance',
        'relevance' => 'Sort by Relevance'
    ];
    
    // View options
    const VIEW_OPTIONS = [
        'grid' => 'Grid View',
        'list' => 'List View',
        'map' => 'Map View'
    ];
    
    /**
     * Get limits for a specific context
     */
    public static function getLimits($context) {
        return self::DEFAULT_LIMITS[$context] ?? self::DEFAULT_LIMITS[self::CONTEXT_END_USER];
    }
    
    /**
     * Get configuration for a search type
     */
    public static function getSearchTypeConfig($type) {
        return self::SEARCH_TYPES[$type] ?? self::SEARCH_TYPES['text'];
    }
    
    /**
     * Validate search parameters
     */
    public static function validateSearchParams($params) {
        $context = $params['context'] ?? self::CONTEXT_END_USER;
        $limits = self::getLimits($context);
        
        // Validate limits
        if (isset($params['limit']) && $params['limit'] > $limits['channels']) {
            $params['limit'] = $limits['channels'];
        }
        
        if (isset($params['per_page']) && $params['per_page'] > $limits['per_page']) {
            $params['per_page'] = $limits['per_page'];
        }
        
        // Validate radius
        if (isset($params['radius'])) {
            $search_type = $params['search_type'] ?? 'text';
            $type_config = self::getSearchTypeConfig($search_type);
            
            if ($params['radius'] < $type_config['min_radius']) {
                $params['radius'] = $type_config['min_radius'];
            }
            
            if ($params['radius'] > $type_config['max_radius']) {
                $params['radius'] = $type_config['max_radius'];
            }
        }
        
        // Validate sort option
        if (isset($params['sort']) && !array_key_exists($params['sort'], self::SORT_OPTIONS)) {
            $params['sort'] = 'relevance';
        }
        
        // Validate status filter
        if (isset($params['status']) && !array_key_exists($params['status'], self::STATUS_FILTERS)) {
            $params['status'] = '';
        }
        
        // Validate view option
        if (isset($params['view']) && !array_key_exists($params['view'], self::VIEW_OPTIONS)) {
            $params['view'] = 'grid';
        }
        
        return $params;
    }
    
    /**
     * Get context from user role or request
     */
    public static function getContextFromUser($user_role = null) {
        switch ($user_role) {
            case 'admin':
            case 'super_admin':
            case 'Admin':
            case 'Owner':
                return self::CONTEXT_ADMIN;
            case 'internal':
            case 'staff':
            case 'Staff':
                return self::CONTEXT_INTERNAL;
            default:
                return self::CONTEXT_END_USER;
        }
    }
    
    /**
     * Get all available status filters
     */
    public static function getStatusFilters() {
        return self::STATUS_FILTERS;
    }
    
    /**
     * Get all available sort options
     */
    public static function getSortOptions() {
        return self::SORT_OPTIONS;
    }
    
    /**
     * Get all available view options
     */
    public static function getViewOptions() {
        return self::VIEW_OPTIONS;
    }
    
    /**
     * Get search type configuration
     */
    public static function getSearchTypeConfigs() {
        return self::SEARCH_TYPES;
    }
    
    /**
     * Check if a search type supports radius
     */
    public static function supportsRadius($search_type) {
        $config = self::getSearchTypeConfig($search_type);
        return $config['supports_custom_radius'];
    }
    
    /**
     * Get default radius for a search type
     */
    public static function getDefaultRadius($search_type) {
        $config = self::getSearchTypeConfig($search_type);
        return $config['default_radius'];
    }
    
    /**
     * Get min/max radius for a search type
     */
    public static function getRadiusLimits($search_type) {
        $config = self::getSearchTypeConfig($search_type);
        return [
            'min' => $config['min_radius'],
            'max' => $config['max_radius']
        ];
    }
    
    /**
     * Get context display name
     */
    public static function getContextDisplayName($context) {
        $names = [
            self::CONTEXT_END_USER => 'End User',
            self::CONTEXT_INTERNAL => 'Internal',
            self::CONTEXT_ADMIN => 'Admin'
        ];
        
        return $names[$context] ?? 'Unknown';
    }
    
    /**
     * Get context description
     */
    public static function getContextDescription($context) {
        $descriptions = [
            self::CONTEXT_END_USER => 'Limited results for UI performance',
            self::CONTEXT_INTERNAL => 'Medium result sets for internal operations',
            self::CONTEXT_ADMIN => 'Full results for admin management'
        ];
        
        return $descriptions[$context] ?? 'Unknown context';
    }
}