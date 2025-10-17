<?php
/**
 * ION Search Factory
 * 
 * Provides easy access to the comprehensive unified search service
 * with automatic context detection and configuration
 */

require_once __DIR__ . '/SearchService.php';
require_once __DIR__ . '/SearchConfig.php';

class SearchFactory {
    private static $instances = [];
    
    /**
     * Get search service instance for a specific context
     */
    public static function getSearchService($context = null, $debug_mode = false) {
        if ($context === null) {
            $context = self::detectContext();
        }
        
        $key = $context . '_' . ($debug_mode ? 'debug' : 'normal');
        
        if (!isset(self::$instances[$key])) {
            global $db;
            $pdo = $db->getPDO();
            self::$instances[$key] = new SearchService($pdo, $debug_mode);
        }
        
        return self::$instances[$key];
    }
    
    /**
     * Comprehensive channel search with ALL features
     */
    public static function searchChannels($query, $options = []) {
        $context = $options['context'] ?? self::detectContext();
        $debug_mode = $options['debug'] ?? false;
        
        $search_service = self::getSearchService($context, $debug_mode);
        
        // Apply context-specific limits
        $options = SearchConfig::validateSearchParams($options);
        $options['context'] = $context;
        
        return $search_service->searchChannels($query, $options);
    }
    
    /**
     * Search videos with automatic context detection
     */
    public static function searchVideos($query, $options = []) {
        $context = $options['context'] ?? self::detectContext();
        $debug_mode = $options['debug'] ?? false;
        
        $search_service = self::getSearchService($context, $debug_mode);
        
        // Apply context-specific limits
        $options = SearchConfig::validateSearchParams($options);
        $options['context'] = $context;
        
        return $search_service->searchVideos($query, $options);
    }
    
    /**
     * Get search suggestions
     */
    public static function getSuggestions($query, $type = 'channels', $limit = 10) {
        $context = self::detectContext();
        $search_service = self::getSearchService($context);
        
        return $search_service->getSearchSuggestions($query, $type, $limit);
    }
    
    /**
     * Get filter counts for UI
     */
    public static function getFilterCounts($options = []) {
        $context = self::detectContext();
        $search_service = self::getSearchService($context);
        
        return $search_service->getFilterCounts($options);
    }
    
    /**
     * Detect search context based on current request
     */
    private static function detectContext() {
        // Check if this is an admin request
        if (isset($_SESSION['user_role'])) {
            return SearchConfig::getContextFromUser($_SESSION['user_role']);
        }
        
        // Check if this is an internal API call
        if (isset($_GET['internal']) || isset($_POST['internal'])) {
            return SearchConfig::CONTEXT_INTERNAL;
        }
        
        // Check if this is an admin page
        $admin_pages = ['directory.php', 'channel-bundle-manager.php', 'ionlocalblast.php'];
        $current_page = basename($_SERVER['SCRIPT_NAME']);
        
        if (in_array($current_page, $admin_pages)) {
            return SearchConfig::CONTEXT_ADMIN;
        }
        
        // Default to end-user context
        return SearchConfig::CONTEXT_END_USER;
    }
    
    /**
     * Get context-specific limits
     */
    public static function getLimits($context = null) {
        if ($context === null) {
            $context = self::detectContext();
        }
        
        return SearchConfig::getLimits($context);
    }
    
    /**
     * Create search options with context-specific defaults
     */
    public static function createSearchOptions($overrides = []) {
        $context = self::detectContext();
        $limits = self::getLimits($context);
        
        $defaults = [
            'context' => $context,
            'limit' => $limits['channels'],
            'per_page' => $limits['per_page'],
            'page' => 1,
            'debug' => false,
            'sort' => 'relevance',
            'status' => '',
            'country' => '',
            'state' => ''
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create search options from GET/POST parameters
     */
    public static function createSearchOptionsFromRequest($request_params = null) {
        if ($request_params === null) {
            $request_params = $_GET;
        }
        
        $options = self::createSearchOptions();
        
        // Map common parameter names
        $param_mapping = [
            'q' => 'query',
            'search' => 'query',
            'sort' => 'sort',
            'status' => 'status',
            'country' => 'country',
            'state' => 'state',
            'page' => 'page',
            'per_page' => 'per_page',
            'radius' => 'radius'
        ];
        
        foreach ($param_mapping as $param => $option) {
            if (isset($request_params[$param])) {
                $options[$option] = $request_params[$param];
            }
        }
        
        // Extract query from the options
        $query = $options['query'] ?? '';
        unset($options['query']);
        
        return [$query, $options];
    }
    
    /**
     * Get available search contexts
     */
    public static function getAvailableContexts() {
        return [
            SearchConfig::CONTEXT_END_USER => 'End User (Limited Results)',
            SearchConfig::CONTEXT_INTERNAL => 'Internal (Medium Results)',
            SearchConfig::CONTEXT_ADMIN => 'Admin (Full Results)'
        ];
    }
    
    /**
     * Get search statistics
     */
    public static function getSearchStats() {
        try {
            global $db;
            $pdo = $db->getPDO();
            
            $stats = [];
            
            // Total channels
            $total_stmt = $pdo->query("SELECT COUNT(*) FROM IONLocalNetwork");
            $stats['total_channels'] = $total_stmt->fetchColumn();
            
            // Channels with coordinates
            $coord_stmt = $pdo->query("SELECT COUNT(*) FROM IONLocalNetwork WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
            $stats['channels_with_coordinates'] = $coord_stmt->fetchColumn();
            
            // Countries
            $country_stmt = $pdo->query("SELECT COUNT(DISTINCT country_code) FROM IONLocalNetwork");
            $stats['countries'] = $country_stmt->fetchColumn();
            
            // States
            $state_stmt = $pdo->query("SELECT COUNT(DISTINCT state_code) FROM IONLocalNetwork");
            $stats['states'] = $state_stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            return [];
        }
    }
}