<?php
/**
 * ION Comprehensive Unified Search Service
 * 
 * Provides advanced search functionality for channels with ALL features:
 * - Text search with relevance scoring (from directory.php)
 * - Multi-word search (City+State, City+Country)
 * - Domain search (single and bulk)
 * - Zip code search with radius (from channel-bundle-manager.php)
 * - Smart slug variations
 * - Advanced filtering (Status, Country, State)
 * - Multiple sorting options
 * - View options (Grid, List, Map)
 * - Pagination with result counts
 * - Context-aware result limits
 */

class SearchService {
    private $pdo;
    private $debug_mode;
    private $query_cache = []; // Simple query result caching for performance
    
    // Search contexts with different limits
    const CONTEXT_END_USER = 'end_user';
    const CONTEXT_INTERNAL = 'internal';
    const CONTEXT_ADMIN = 'admin';
    
    // Context limits will be initialized in constructor
    private $context_limits;
    
    // Status filter options (from directory.php)
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
    
    // Sort options (from directory.php)
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
    
    public function __construct($pdo, $debug_mode = false) {
        $this->pdo = $pdo;
        $this->debug_mode = $debug_mode;
        
        // Initialize context limits from SearchConfig
        $this->context_limits = [
            self::CONTEXT_END_USER => SearchConfig::getLimits(SearchConfig::CONTEXT_END_USER),
            self::CONTEXT_INTERNAL => SearchConfig::getLimits(SearchConfig::CONTEXT_INTERNAL),
            self::CONTEXT_ADMIN => SearchConfig::getLimits(SearchConfig::CONTEXT_ADMIN)
        ];
    }
    
    /**
     * Comprehensive channel search with ALL features
     */
    public function searchChannels($query, $options = []) {
        // Create cache key for this search
        $cache_key = md5($query . serialize($options));
        
        // Check cache first (for non-debug mode)
        if (!$this->debug_mode && isset($this->query_cache[$cache_key])) {
            $this->log("Using cached result for query: '{$query}'");
            return $this->query_cache[$cache_key];
        }
        
        $context = $options['context'] ?? self::CONTEXT_END_USER;
        $limit = $options['limit'] ?? $this->context_limits[$context]['channels'];
        $page = $options['page'] ?? 1;
        $per_page = min($limit, $options['per_page'] ?? $this->context_limits[$context]['per_page']);
        $offset = ($page - 1) * $per_page;
        
        $this->log("=== COMPREHENSIVE CHANNEL SEARCH START ===");
        $this->log("Query: '{$query}', Context: {$context}, Limit: {$limit}, Page: {$page}");
        $this->log("Options: " . json_encode($options));
        
        try {
            // Detect search type and build appropriate query
            $search_type = $this->detectSearchType($query);
            $this->log("Search type detected: {$search_type}");
            
            // Apply search conditions based on type
            $search_conditions = $this->buildSearchConditions($query, $search_type, $options);
            $where_clause = $search_conditions['where'];
            $relevance_clause = $search_conditions['relevance'];
            $params = $search_conditions['params'];
            $is_zip_search = isset($search_conditions['sort_by_distance']) && $search_conditions['sort_by_distance'];
            
            // Apply filters
            $filter_conditions = $this->buildFilterConditions($options);
            if ($is_zip_search) {
                // For zip searches, add filters to WHERE clause before HAVING
                $where_clause .= $filter_conditions['where'];
            } else {
                $where_clause .= $filter_conditions['where'];
            }
            $params = array_merge($params, $filter_conditions['params']);
            
            // Apply sorting
            $sort_clause = $this->buildSortClause($options, $search_type);
            
            // Build base query with relevance scoring (only if needed)
            $base_query = $this->buildBaseChannelQuery();
            if (!empty($relevance_clause)) {
                $base_query = str_replace('FROM IONLocalNetwork', $relevance_clause . ' FROM IONLocalNetwork', $base_query);
            }
            
            // Build final query
            $sql = $base_query . $where_clause;
            
            // Add HAVING clause for zip searches
            if ($is_zip_search && !empty($search_conditions['having'])) {
                $sql .= $search_conditions['having'];
            }
            
            $sql .= $sort_clause . " LIMIT ? OFFSET ?";
            $params[] = $per_page;
            $params[] = $offset;
            
            $this->log("Final SQL: " . $sql);
            $this->log("Params: " . json_encode($params));
            
            // Execute query
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination - OPTIMIZED
            if ($is_zip_search && !empty($relevance_clause)) {
                // For zip searches, we need to include the distance calculation in count query too
                $count_base_query = $this->buildBaseChannelQuery();
                $count_base_query = str_replace('FROM IONLocalNetwork', $relevance_clause . ' FROM IONLocalNetwork', $count_base_query);
                $count_sql = $count_base_query . $where_clause;
                
                // Add HAVING clause for zip searches
                if (!empty($search_conditions['having'])) {
                    $count_sql .= $search_conditions['having'];
                }
                
                $count_params = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
            } else {
                // For non-zip searches, use simple count - much faster
                $count_sql = "SELECT COUNT(*) FROM IONLocalNetwork" . $where_clause;
                $count_params = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
            }
            
            $count_stmt = $this->pdo->prepare($count_sql);
            $count_stmt->execute($count_params);
            $total_count = $count_stmt->fetchColumn();
            
            $this->log("Found {$total_count} channels matching search");
            
            $result = [
                'success' => true,
                'channels' => $channels,
                'pagination' => $this->buildPagination($page, $per_page, $total_count),
                'filters' => $this->buildFilterData($options),
                'sort_options' => self::SORT_OPTIONS,
                'debug' => [
                    'search_type' => $search_type,
                    'query' => $query,
                    'total_found' => $total_count,
                    'applied_filters' => $options
                ]
            ];
            
            // Cache the result for performance (only for successful searches)
            if (!$this->debug_mode) {
                $this->query_cache[$cache_key] = $result;
                // Limit cache size to prevent memory issues
                if (count($this->query_cache) > 100) {
                    array_shift($this->query_cache);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Search error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'channels' => [],
                'pagination' => null
            ];
        }
    }
    
    /**
     * Build base channel query with all necessary fields
     */
    private function buildBaseChannelQuery() {
        return "
            SELECT 
                slug, city_name, channel_name, population, 
                state_name, state_code, country_name, country_code, 
                latitude, longitude, custom_domain, status,
                title, description, created_at
            FROM IONLocalNetwork 
        ";
    }
    
    /**
     * Check if we can use optimized simple query (for performance)
     */
    private function canUseSimpleQuery($query, $options) {
        // Use simple query for empty searches or very basic text searches
        if (empty($query)) {
            return true;
        }
        
        // Use simple query if no complex filters are applied
        $has_complex_filters = !empty($options['status']) || 
                              !empty($options['country']) || 
                              !empty($options['state']);
        
        if (!$has_complex_filters && strlen($query) < 10) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect the type of search query
     */
    private function detectSearchType($query) {
        $query = trim($query);
        
        // Handle empty queries - return all results
        if (empty($query)) {
            return 'all';
        }
        
        // Check for zip code patterns
        if (preg_match('/^\d{4,6}([-,.]\s*\d+)?$/', $query)) {
            return 'zip_code';
        }
        
        // Check for domain patterns
        if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', $query)) {
            return 'domain';
        }
        
        // Check for domain extension patterns
        if (preg_match('/^\.([a-z]{2,6})$/i', $query)) {
            return 'domain_extension';
        }
        
        // Check for bulk domain search
        $potential_domains = preg_split('/[,\s]+/', $query);
        $domain_count = 0;
        foreach ($potential_domains as $item) {
            if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', trim($item))) {
                $domain_count++;
            }
        }
        if ($domain_count >= 2) {
            return 'bulk_domain';
        }
        
        // Check for multi-word search
        $words = array_filter(array_map('trim', explode(' ', $query)));
        if (count($words) >= 2) {
            return 'multi_word';
        }
        
        return 'text';
    }
    
    /**
     * Build search conditions based on search type
     */
    private function buildSearchConditions($query, $search_type, $options) {
        $where = " WHERE 1=1";
        $relevance = "";
        $params = [];
        
        switch ($search_type) {
            case 'all':
                return $this->buildAllSearchConditions();
                
            case 'zip_code':
                return $this->buildZipCodeSearchConditions($query, $options);
                
            case 'domain':
                return $this->buildDomainSearchConditions($query);
                
            case 'domain_extension':
                return $this->buildDomainExtensionSearchConditions($query);
                
            case 'bulk_domain':
                return $this->buildBulkDomainSearchConditions($query);
                
            case 'multi_word':
                return $this->buildMultiWordSearchConditions($query);
                
            case 'text':
            default:
                return $this->buildTextSearchConditions($query);
        }
    }
    
    /**
     * Build conditions for showing all channels (empty search) - OPTIMIZED
     */
    private function buildAllSearchConditions() {
        $where = " WHERE slug IS NOT NULL 
                   AND city_name IS NOT NULL 
                   AND latitude IS NOT NULL 
                   AND longitude IS NOT NULL 
                   AND latitude != '' 
                   AND longitude != ''";
        
        // No relevance scoring for empty search - much faster
        $relevance = "";
        $params = [];
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params
        ];
    }
    
    /**
     * Build zip code search conditions with distance calculation
     */
    private function buildZipCodeSearchConditions($query, $options) {
        $zip_data = $this->parseZipCodeQuery($query);
        $zip_code = $zip_data['zip_code'];
        $radius = $zip_data['radius'];
        
        $this->log("Zip code search: {$zip_code}, radius: {$radius} miles");
        
        // Get coordinates for zip code
        $coords = $this->getCoordinatesForZip($zip_code);
        if (!$coords) {
            throw new Exception("No coordinates found for zip code: {$zip_code}");
        }
        
        $where = " WHERE slug IS NOT NULL 
                   AND city_name IS NOT NULL 
                   AND latitude IS NOT NULL 
                   AND longitude IS NOT NULL 
                   AND latitude != '' 
                   AND longitude != ''";
        
        $relevance = ", (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                        cos(radians(longitude) - radians(?)) + 
                        sin(radians(?)) * sin(radians(latitude)))) AS distance";
        
        $params = [$coords['lat'], $coords['lng'], $coords['lat']];
        
        // Add distance filter - this will be handled separately
        $having_clause = " HAVING distance <= ?";
        $params[] = $radius;
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params,
            'having' => $having_clause,
            'sort_by_distance' => true
        ];
    }
    
    /**
     * Build domain search conditions
     */
    private function buildDomainSearchConditions($query) {
        $where = " WHERE custom_domain LIKE ?";
        $params = ['%' . $query . '%'];
        
        $relevance = ", (
            CASE 
                WHEN custom_domain = ? THEN 100
                WHEN custom_domain LIKE ? THEN 90
                WHEN custom_domain LIKE ? THEN 80
                WHEN custom_domain LIKE ? THEN 70
                ELSE 0
            END
        ) as relevance_score";
        
        $params = array_merge($params, [
            $query,
            $query . '%',
            '%' . $query,
            '%' . $query . '%'
        ]);
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params
        ];
    }
    
    /**
     * Build domain extension search conditions
     */
    private function buildDomainExtensionSearchConditions($query) {
        $where = " WHERE custom_domain LIKE ?";
        $params = ['%' . $query];
        
        return [
            'where' => $where,
            'relevance' => "",
            'params' => $params
        ];
    }
    
    /**
     * Build bulk domain search conditions (enhanced from original)
     */
    private function buildBulkDomainSearchConditions($query) {
        // Parse domains like original - comma or space separated
        $potential_domains = preg_split('/[,\s]+/', trim($query));
        $potential_domains = array_filter(array_map('trim', $potential_domains));
        
        // Validate domains (must look like full domains)
        $valid_domains = [];
        foreach ($potential_domains as $item) {
            if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', $item)) {
                $valid_domains[] = $item;
            }
        }
        
        if (empty($valid_domains)) {
            // Fallback to regular text search if no valid domains
            return $this->buildTextSearchConditions($query);
        }
        
        $domain_conditions = [];
        $params = [];
        
        foreach ($valid_domains as $domain) {
            $domain_conditions[] = "custom_domain LIKE ?";
            $params[] = '%' . $domain . '%';
        }
        
        $where = " WHERE (" . implode(' OR ', $domain_conditions) . ")";
        
        // Enhanced relevance scoring for bulk domains (from original)
        $relevance = ", (CASE ";
        $score = 100;
        foreach ($valid_domains as $domain) {
            $relevance .= "WHEN custom_domain LIKE ? THEN " . $score . " ";
            $params[] = '%' . $domain . '%';
            $score -= 5; // Decrease score for each subsequent domain (order preference)
        }
        $relevance .= "ELSE 0 END) as relevance_score";
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params
        ];
    }
    
    /**
     * Build multi-word search conditions (enhanced from original logic)
     */
    private function buildMultiWordSearchConditions($query) {
        $words = array_filter(array_map('trim', explode(' ', $query)));
        
        if (count($words) === 2) {
            return $this->buildTwoWordSearchConditions($words[0], $words[1]);
        } else {
            return $this->buildThreePlusWordSearchConditions($words);
        }
    }
    
    /**
     * Build two-word search conditions (City+State, City+Country) - enhanced from original
     */
    private function buildTwoWordSearchConditions($word1, $word2) {
        $where = " WHERE (";
        $params = [];
        
        // Create combined search terms (with and without spaces)
        $combined_term_with_space = $word1 . ' ' . $word2;
        $combined_term_without_space = $word1 . $word2;
        
        // Primary combinations: city+state, city+country (both orders)
        $combined_conditions = [
            "(city_name LIKE ? AND state_name LIKE ?)",
            "(city_name LIKE ? AND country_name LIKE ?)",
            "(city_name LIKE ? AND state_name LIKE ?)",
            "(city_name LIKE ? AND country_name LIKE ?)"
        ];
        
        $params = array_merge($params, [
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word2 . '%', '%' . $word1 . '%',
            '%' . $word2 . '%', '%' . $word1 . '%'
        ]);
        
        // Add search for combined terms in city and state names
        $combined_term_conditions = [
            "city_name LIKE ?",
            "city_name LIKE ?",
            "state_name LIKE ?",
            "state_name LIKE ?"
        ];
        
        $params = array_merge($params, [
            '%' . $combined_term_with_space . '%',
            '%' . $combined_term_without_space . '%',
            '%' . $combined_term_with_space . '%',
            '%' . $combined_term_without_space . '%'
        ]);
        
        // Fallback to individual term matches
        $individual_conditions = [
            "city_name LIKE ?",
            "city_name LIKE ?",
            "state_name LIKE ?",
            "state_name LIKE ?",
            "country_name LIKE ?",
            "country_name LIKE ?",
            "custom_domain LIKE ?",
            "custom_domain LIKE ?",
            "title LIKE ?",
            "description LIKE ?"
        ];
        
        $params = array_merge($params, [
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . ' ' . $word2 . '%',
            '%' . $word1 . ' ' . $word2 . '%'
        ]);
        
        $all_conditions = array_merge($combined_conditions, $combined_term_conditions, $individual_conditions);
        $where .= implode(' OR ', $all_conditions) . ")";
        
        // Advanced relevance scoring for two terms (prioritize exact combined matches)
        $relevance = ", (
            CASE 
                WHEN city_name LIKE ? THEN 110
                WHEN city_name LIKE ? THEN 105
                WHEN state_name LIKE ? THEN 102
                WHEN state_name LIKE ? THEN 101
                WHEN (city_name LIKE ? AND state_name LIKE ?) THEN 100
                WHEN (city_name LIKE ? AND country_name LIKE ?) THEN 95
                WHEN (city_name LIKE ? AND state_name LIKE ?) THEN 90
                WHEN (city_name LIKE ? AND country_name LIKE ?) THEN 85
                WHEN city_name LIKE ? THEN 50
                WHEN city_name LIKE ? THEN 45
                WHEN state_name LIKE ? THEN 40
                WHEN state_name LIKE ? THEN 35
                WHEN country_name LIKE ? THEN 30
                WHEN country_name LIKE ? THEN 25
                WHEN title LIKE ? THEN 20
                WHEN description LIKE ? THEN 15
                ELSE 10
            END
        ) as relevance_score";
        
        $relevance_params = [
            '%' . $combined_term_with_space . '%',
            '%' . $combined_term_without_space . '%',
            '%' . $combined_term_with_space . '%',
            '%' . $combined_term_without_space . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word2 . '%', '%' . $word1 . '%',
            '%' . $word2 . '%', '%' . $word1 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . '%', '%' . $word2 . '%',
            '%' . $word1 . ' ' . $word2 . '%',
            '%' . $word1 . ' ' . $word2 . '%'
        ];
        
        $params = array_merge($params, $relevance_params);
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params
        ];
    }
    
    /**
     * Build three or more word search conditions - enhanced from original
     */
    private function buildThreePlusWordSearchConditions($words) {
        $where = " WHERE (";
        $params = [];
        
        // Each word must match in at least one field
        $search_conditions = [];
        foreach ($words as $word) {
            $search_conditions[] = "(city_name LIKE ? OR state_name LIKE ? OR country_name LIKE ? OR custom_domain LIKE ? OR title LIKE ? OR description LIKE ?)";
            $params = array_merge($params, [
                '%' . $word . '%', '%' . $word . '%', '%' . $word . '%',
                '%' . $word . '%', '%' . $word . '%', '%' . $word . '%'
            ]);
        }
        
        $where .= implode(' AND ', $search_conditions) . ")";
        
        // Simple relevance for multi-term
        $relevance = ", (
            CASE 
                WHEN city_name LIKE ? THEN 50
                WHEN state_name LIKE ? THEN 30
                ELSE 10
            END
        ) as relevance_score";
        
        $params = array_merge($params, [
            '%' . $words[0] . '%',
            '%' . $words[0] . '%'
        ]);
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params
        ];
    }
    
    /**
     * Build text search conditions with advanced relevance scoring (enhanced from original)
     */
    private function buildTextSearchConditions($query) {
        $where = " WHERE (";
        $params = [];
        
        // Enhanced search conditions - includes title and description like original
        $search_conditions = [
            "city_name LIKE ?",
            "state_name LIKE ?", 
            "country_name LIKE ?",
            "custom_domain LIKE ?",
            "slug LIKE ?",
            "title LIKE ?",
            "description LIKE ?"
        ];
        
        $search_param = '%' . $query . '%';
        $params = array_fill(0, count($search_conditions), $search_param);
        
        $where .= implode(' OR ', $search_conditions) . ")";
        
        // Advanced relevance scoring - enhanced from original logic
        $relevance = ", (
            CASE 
                WHEN city_name LIKE ? THEN 100
                WHEN city_name LIKE ? THEN 90
                WHEN state_name LIKE ? THEN 80
                WHEN state_name LIKE ? THEN 70
                WHEN country_name LIKE ? THEN 60
                WHEN city_name LIKE ? THEN 50
                WHEN state_name LIKE ? THEN 40
                WHEN country_name LIKE ? THEN 30
                WHEN custom_domain LIKE ? THEN 25
                WHEN title LIKE ? THEN 20
                WHEN slug LIKE ? THEN 15
                WHEN description LIKE ? THEN 10
                ELSE 0
            END
        ) as relevance_score";
        
        $relevance_params = [
            $query,           // city_name exact match
            '%' . $query . '%', // city_name partial match
            $query,           // state_name exact match  
            '%' . $query . '%', // state_name partial match
            $query,           // country_name exact match
            '%' . $query . '%', // city_name contains
            '%' . $query . '%', // state_name contains
            '%' . $query . '%', // country_name contains
            '%' . $query . '%', // custom_domain contains
            '%' . $query . '%', // title contains
            '%' . $query . '%', // slug contains
            '%' . $query . '%'  // description contains
        ];
        
        $params = array_merge($params, $relevance_params);
        
        return [
            'where' => $where,
            'relevance' => $relevance,
            'params' => $params
        ];
    }
    
    /**
     * Build filter conditions (status, country, state)
     */
    private function buildFilterConditions($options) {
        $where = "";
        $params = [];
        
        // Status filter
        if (!empty($options['status'])) {
            $where .= " AND status = ?";
            $params[] = $options['status'];
        }
        
        // Country filter
        if (!empty($options['country'])) {
            $where .= " AND country_code = ?";
            $params[] = strtoupper($options['country']);
        }
        
        // State filter
        if (!empty($options['state'])) {
            $where .= " AND state_code = ?";
            $params[] = strtoupper($options['state']);
        }
        
        return [
            'where' => $where,
            'params' => $params
        ];
    }
    
    /**
     * Build sort clause
     */
    private function buildSortClause($options, $search_type) {
        $sort = $options['sort'] ?? 'relevance';
        
        // For zip code searches, default to distance sorting
        if ($search_type === 'zip_code' && $sort === 'relevance') {
            $sort = 'distance';
        }
        
        switch ($sort) {
            case 'distance':
                return " ORDER BY distance ASC, city_name ASC";
            case 'relevance':
                return " ORDER BY relevance_score DESC, city_name ASC";
            case 'population':
                return " ORDER BY CAST(population AS UNSIGNED) DESC, city_name ASC";
            case 'city_name':
                return " ORDER BY city_name ASC";
            case 'state_name':
                return " ORDER BY state_name ASC, city_name ASC";
            case 'country_name':
                return " ORDER BY country_name ASC, state_name ASC, city_name ASC";
            case 'custom_domain':
                return " ORDER BY custom_domain ASC, city_name ASC";
            case 'status':
                return " ORDER BY status ASC, city_name ASC";
            default:
                return " ORDER BY city_name ASC";
        }
    }
    
    /**
     * Build filter data for UI
     */
    private function buildFilterData($options) {
        return [
            'status_options' => self::STATUS_FILTERS,
            'sort_options' => self::SORT_OPTIONS,
            'selected_status' => $options['status'] ?? '',
            'selected_country' => $options['country'] ?? '',
            'selected_state' => $options['state'] ?? '',
            'selected_sort' => $options['sort'] ?? 'relevance'
        ];
    }
    
    /**
     * Parse zip code query for code and radius
     */
    private function parseZipCodeQuery($query) {
        $zip_code = $query;
        $radius = 30; // default
        
        if (preg_match('/^(\d{4,6})[,.]\s*(\d+)$/', $query, $matches)) {
            $zip_code = $matches[1];
            $radius = max(30, min(200, intval($matches[2])));
        }
        
        return [
            'zip_code' => $zip_code,
            'radius' => $radius
        ];
    }
    
    /**
     * Get coordinates for zip code
     */
    private function getCoordinatesForZip($zip_code) {
        try {
            $sql = "SELECT geo_point FROM IONGeoCodes WHERE zip_code = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$zip_code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->log("ZIP DEBUG: Looking for zip code: {$zip_code}");
            $this->log("ZIP DEBUG: Query result: " . json_encode($result));
            
            if ($result && !empty($result['geo_point'])) {
                $coords = explode(', ', $result['geo_point']);
                if (count($coords) === 2) {
                    $this->log("ZIP DEBUG: Found coordinates: " . json_encode($coords));
                    return [
                        'lat' => floatval(trim($coords[0])),
                        'lng' => floatval(trim($coords[1]))
                    ];
                }
            }
            
            $this->log("ZIP DEBUG: No coordinates found for zip code: {$zip_code}");
            return null;
            
        } catch (Exception $e) {
            $this->log("Error getting coordinates for zip {$zip_code}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Build pagination data
     */
    private function buildPagination($page, $per_page, $total_count) {
        // Ensure total_count is an integer
        $total_count = (int)$total_count;
        $per_page = (int)$per_page;
        $page = (int)$page;
        
        $total_pages = ceil($total_count / $per_page);
        
        return [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1,
            'showing_start' => (($page - 1) * $per_page) + 1,
            'showing_end' => min($page * $per_page, $total_count)
        ];
    }
    
    /**
     * Log debug information
     */
    private function log($message) {
        if ($this->debug_mode) {
            error_log("[IONSearchService] " . $message);
        }
    }
    
    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions($query, $type = 'channels', $limit = 10) {
        $suggestions = [];
        
        try {
            if ($type === 'channels') {
                $sql = "
                    SELECT DISTINCT city_name, state_name, country_name 
                    FROM IONLocalNetwork 
                    WHERE city_name LIKE ? 
                    ORDER BY city_name 
                    LIMIT ?
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['%' . $query . '%', $limit]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $result) {
                    $suggestions[] = $result['city_name'] . ', ' . $result['state_name'];
                }
            }
            
        } catch (Exception $e) {
            $this->log("Error getting suggestions: " . $e->getMessage());
        }
        
        return $suggestions;
    }
    
    /**
     * Clear query cache (useful for testing or when data changes)
     */
    public function clearCache() {
        $this->query_cache = [];
        $this->log("Query cache cleared");
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        return [
            'cache_size' => count($this->query_cache),
            'cache_keys' => array_keys($this->query_cache)
        ];
    }
    
    /**
     * Get filter counts for UI
     */
    public function getFilterCounts($options = []) {
        try {
            $counts = [];
            
            // Status counts
            $status_sql = "SELECT status, COUNT(*) as count FROM IONLocalNetwork GROUP BY status";
            $status_stmt = $this->pdo->prepare($status_sql);
            $status_stmt->execute();
            $status_results = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($status_results as $result) {
                $counts[$result['status']] = $result['count'];
            }
            
            // Country counts
            $country_sql = "SELECT country_code, country_name, COUNT(*) as count FROM IONLocalNetwork GROUP BY country_code, country_name ORDER BY count DESC";
            $country_stmt = $this->pdo->prepare($country_sql);
            $country_stmt->execute();
            $counts['countries'] = $country_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $counts;
            
        } catch (Exception $e) {
            $this->log("Error getting filter counts: " . $e->getMessage());
            return [];
        }
    }
}