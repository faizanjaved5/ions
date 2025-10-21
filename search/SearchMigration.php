<?php
/**
 * ION Search Migration Helper
 * 
 * Provides easy migration from existing search implementations
 * to the unified search service
 */

require_once __DIR__ . '/SearchFactory.php';
require_once __DIR__ . '/SearchConfig.php';

class SearchMigration {
    
    /**
     * Migrate directory.php search to unified search
     */
    public static function migrateDirectorySearch($query, $options = []) {
        // Extract parameters from directory.php format
        $search_options = [
            'context' => SearchConfig::CONTEXT_ADMIN,
            'debug' => true,
            'sort' => $options['sort'] ?? 'relevance',
            'status' => $options['status_filter'] ?? $options['status'] ?? '',
            'country' => $options['country_filter'] ?? $options['country'] ?? '',
            'state' => $options['state_filter'] ?? $options['state'] ?? '',
            'page' => $options['page'] ?? 1,
            'per_page' => $options['per_page'] ?? 50,
            'limit' => $options['per_page'] ?? 50,  // Set limit to match per_page for directory.php
            'view' => $options['view'] ?? 'grid'
        ];
        
        return SearchFactory::searchChannels($query, $search_options);
    }
    
    /**
     * Migrate channel-bundle-manager.php search to unified search
     */
    public static function migrateChannelBundleSearch($query, $options = []) {
        // Extract parameters from channel-bundle-manager.php format
        $search_options = [
            'context' => SearchConfig::CONTEXT_ADMIN,
            'debug' => true,
            'limit' => $options['limit'] ?? 1000,
            'page' => 1,
            'per_page' => $options['limit'] ?? 1000
        ];
        
        return SearchFactory::searchChannels($query, $search_options);
    }
    
    /**
     * Migrate ionlocalblast.php search to unified search
     */
    public static function migrateIonLocalBlastSearch($query, $options = []) {
        // Extract parameters from ionlocalblast.php format
        $search_options = [
            'context' => SearchConfig::CONTEXT_ADMIN,
            'debug' => true,
            'limit' => 50,
            'page' => 1,
            'per_page' => 50
        ];
        
        return SearchFactory::searchChannels($query, $search_options);
    }
    
    /**
     * Convert unified search result to directory.php format
     */
    public static function convertToDirectoryFormat($result) {
        if (!$result['success']) {
            return $result;
        }
        
        // Add directory.php specific fields
        foreach ($result['channels'] as &$channel) {
            // Add flag emoji for country
            if (isset($channel['country_code'])) {
                $channel['flag_emoji'] = self::getFlagEmoji($channel['country_code']);
            }
            
            // Add status display name
            if (isset($channel['status'])) {
                $channel['status_display'] = SearchConfig::getStatusFilters()[$channel['status']] ?? $channel['status'];
            }
            
            // Add distance display if available
            if (isset($channel['distance'])) {
                $channel['distance_display'] = round($channel['distance'], 1) . ' miles';
            }
        }
        
        return $result;
    }
    
    /**
     * Convert unified search result to channel-bundle-manager.php format
     */
    public static function convertToChannelBundleFormat($result) {
        if (!$result['success']) {
            return $result;
        }
        
        // Add channel-bundle-manager.php specific fields
        $formatted_result = [
            'success' => $result['success'],
            'channels' => $result['channels'],
            'debug' => $result['debug'] ?? null
        ];
        
        // Add pagination if needed
        if (isset($result['pagination'])) {
            $formatted_result['pagination'] = $result['pagination'];
        }
        
        return $formatted_result;
    }
    
    /**
     * Convert unified search result to ionlocalblast.php format
     */
    public static function convertToIonLocalBlastFormat($result) {
        if (!$result['success']) {
            return $result;
        }
        
        // Add ionlocalblast.php specific fields
        $formatted_result = [
            'success' => $result['success'],
            'channels' => $result['channels']
        ];
        
        // Add debug info if available
        if (isset($result['debug'])) {
            $formatted_result['debug'] = $result['debug'];
        }
        
        return $formatted_result;
    }
    
    /**
     * Get flag emoji for country code
     */
    private static function getFlagEmoji($country_code) {
        $flags = [
            'US' => '🇺🇸',
            'CA' => '🇨🇦',
            'GB' => '🇬🇧',
            'AU' => '🇦🇺',
            'DE' => '🇩🇪',
            'FR' => '🇫🇷',
            'IT' => '🇮🇹',
            'ES' => '🇪🇸',
            'MX' => '🇲🇽',
            'BR' => '🇧🇷',
            'JP' => '🇯🇵',
            'CN' => '🇨🇳',
            'IN' => '🇮🇳',
            'RU' => '🇷🇺',
            'KR' => '🇰🇷'
        ];
        
        return $flags[strtoupper($country_code)] ?? '🌍';
    }
    
    /**
     * Create search form HTML for directory.php
     */
    public static function createDirectorySearchForm($current_options = []) {
        $query = $current_options['q'] ?? '';
        $sort = $current_options['sort'] ?? 'relevance';
        $status = $current_options['status'] ?? '';
        $country = $current_options['country'] ?? '';
        $state = $current_options['state'] ?? '';
        $view = $current_options['view'] ?? 'grid';
        
        $status_options = SearchConfig::getStatusFilters();
        $sort_options = SearchConfig::getSortOptions();
        $view_options = SearchConfig::getViewOptions();
        
        ob_start();
        ?>
        <form action="" method="get" id="filter-form" style="display: contents;">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search cities, states, slugs or domains">
            
            <select name="sort" onchange="this.form.submit()">
                <?php foreach ($sort_options as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach ($status_options as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="country" id="country-filter" onchange="this.form.submit()">
                <option value="">All Countries</option>
                <!-- Countries will be populated dynamically -->
            </select>
            
            <select name="state" id="state-filter" onchange="this.form.submit()" <?= !$country ? 'disabled' : '' ?>>
                <option value="">All States/Provinces</option>
                <!-- States will be populated dynamically -->
            </select>
        </form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Create search form HTML for channel-bundle-manager.php
     */
    public static function createChannelBundleSearchForm($current_options = []) {
        $query = $current_options['search'] ?? '';
        
        ob_start();
        ?>
        <div class="search-container">
            <input type="text" id="channelSearch" class="search-input" 
                   placeholder="Search by name, city, slug, or zip code (e.g., 90210, 90210,50)..." 
                   value="<?= htmlspecialchars($query) ?>">
            <button onclick="searchChannels()" class="btn btn-primary" id="channelSearchBtn">
                <span class="btn-text">Search Channels</span>
                <span class="btn-loading hidden">Searching...</span>
            </button>
        </div>
        <div class="search-help">
            <p><strong>Search Options:</strong></p>
            <ul>
                <li><strong>Text Search:</strong> Search by channel name, city name, or slug</li>
                <li><strong>Zip Code Search:</strong> Enter a zip code (e.g., 90210) to find channels within 30 miles</li>
                <li><strong>Custom Radius:</strong> Use comma or period to specify radius (e.g., 90210,50 for 50 miles)</li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Create search form HTML for ionlocalblast.php
     */
    public static function createIonLocalBlastSearchForm($current_options = []) {
        $query = $current_options['query'] ?? '';
        
        ob_start();
        ?>
        <div class="search-container">
            <input type="text" id="channelSearch" class="search-input" 
                   placeholder="Search by name, city, slug, or zip code (e.g., 90210, 90210,50)..." 
                   value="<?= htmlspecialchars($query) ?>">
            <button onclick="searchChannels()" class="btn btn-primary" id="channelSearchBtn">
                <span class="btn-text">Search Channels</span>
                <span class="btn-loading hidden">Searching...</span>
            </button>
        </div>
        <div class="search-help">
            <p><strong>Search Options:</strong></p>
            <ul>
                <li><strong>Text Search:</strong> Search by channel name, city name, or slug</li>
                <li><strong>Zip Code Search:</strong> Enter a zip code (e.g., 90210) to find channels within 30 miles</li>
                <li><strong>Custom Radius:</strong> Use comma or period to specify radius (e.g., 90210,50 for 50 miles)</li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
