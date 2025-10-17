<?php
/**
 * ION Video Ad Manager
 * 
 * Handles ad configuration, targeting, and delivery for video content
 */

class AdManager {
    private $config;
    private $user;
    private $video;
    private $channel;
    
    public function __construct($user = null, $video = null, $channel = null) {
        $this->config = include(__DIR__ . '/../config/ads-config.php');
        $this->user = $user;
        $this->video = $video;
        $this->channel = $channel;
    }
    
    /**
     * Check if ads are enabled for the current context
     */
    public function isAdsEnabled(): bool {
        // Global ads disabled
        if (!$this->config['enabled']) {
            return false;
        }
        
        // Check user role restrictions
        if ($this->user && isset($this->user->role)) {
            $userRules = $this->config['content_rules']['user_roles'][$this->user->role] ?? null;
            if ($userRules && isset($userRules['enabled']) && !$userRules['enabled']) {
                return false;
            }
        }
        
        // Check channel-specific rules
        if ($this->channel && isset($this->channel->id)) {
            $channelRules = $this->config['content_rules']['channels'][$this->channel->id] ?? null;
            if ($channelRules && isset($channelRules['enabled']) && !$channelRules['enabled']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get the appropriate ad configuration for the current video
     */
    public function getAdConfig(): array {
        if (!$this->isAdsEnabled()) {
            return ['enabled' => false];
        }
        
        $config = [
            'enabled' => true,
            'debug' => $this->config['debug_mode'],
            'systems' => []
        ];
        
        // Add enabled ad systems
        foreach ($this->config['ad_systems'] as $system => $systemConfig) {
            if ($systemConfig['enabled']) {
                $config['systems'][] = $system;
            }
        }
        
        // Configure IMA if enabled
        if (in_array('ima', $config['systems'])) {
            $config['ima'] = $this->configureIMA();
        }
        
        // Configure SSAI if enabled
        if (in_array('ssai', $config['systems'])) {
            $config['ssai'] = $this->configureSSAI();
        }
        
        // Configure Prebid if enabled
        if (in_array('prebid', $config['systems'])) {
            $config['prebid'] = $this->configurePrebid();
        }
        
        return $config;
    }
    
    /**
     * Configure Google IMA settings
     */
    private function configureIMA(): array {
        $imaConfig = $this->config['ima'];
        
        // Apply user role modifications
        if ($this->user && isset($this->user->role)) {
            $userRules = $this->config['content_rules']['user_roles'][$this->user->role] ?? null;
            if ($userRules && isset($userRules['ima'])) {
                $imaConfig = array_merge_recursive($imaConfig, $userRules['ima']);
            }
        }
        
        // Apply duration-based rules
        if ($this->video && isset($this->video->duration)) {
            $durationRules = $this->getDurationRules($this->video->duration);
            if ($durationRules && isset($durationRules['ad_breaks'])) {
                $imaConfig['ad_breaks'] = array_merge_recursive(
                    $imaConfig['ad_breaks'], 
                    $durationRules['ad_breaks']
                );
            }
        }
        
        // Generate ad tag URL
        $imaConfig['ad_tag_url'] = $this->generateAdTagUrl();
        
        return $imaConfig;
    }
    
    /**
     * Configure SSAI settings
     */
    private function configureSSAI(): array {
        $ssaiConfig = $this->config['ssai'];
        
        // Generate SSAI manifest URL based on provider
        switch ($ssaiConfig['provider']) {
            case 'aws_mediatailor':
                $ssaiConfig['manifest_url'] = $this->generateMediaTailorUrl();
                break;
            case 'cloudflare_stream':
                $ssaiConfig['manifest_url'] = $this->generateCloudflareStreamUrl();
                break;
        }
        
        return $ssaiConfig;
    }
    
    /**
     * Configure Prebid.js settings
     */
    private function configurePrebid(): array {
        $prebidConfig = $this->config['prebid'];
        
        // Filter enabled bidders
        $enabledBidders = [];
        foreach ($prebidConfig['bidders'] as $bidder => $config) {
            if ($config['enabled']) {
                $enabledBidders[$bidder] = $config;
            }
        }
        $prebidConfig['enabled_bidders'] = $enabledBidders;
        
        // Add targeting information
        $prebidConfig['targeting'] = $this->generateTargeting();
        
        return $prebidConfig;
    }
    
    /**
     * Get duration-based rules for the video
     */
    private function getDurationRules(int $duration): ?array {
        foreach ($this->config['content_rules']['duration_rules'] as $rule) {
            $minDuration = $rule['min_duration'] ?? 0;
            $maxDuration = $rule['max_duration'] ?? PHP_INT_MAX;
            
            if ($duration >= $minDuration && $duration <= $maxDuration) {
                return $rule;
            }
        }
        
        return null;
    }
    
    /**
     * Generate VAST/VMAP ad tag URL for Google Ad Manager
     */
    private function generateAdTagUrl(): string {
        if (!empty($this->config['ima']['default_ad_tag'])) {
            return $this->config['ima']['default_ad_tag'];
        }
        
        $gamConfig = $this->config['ima']['gam'];
        if (empty($gamConfig['network_id'])) {
            return '';
        }
        
        $params = [
            'iu' => $gamConfig['ad_unit_id'],
            'env' => 'vp',
            'gdfp_req' => '1',
            'output' => 'vast',
            'unviewed_position_start' => '1',
            'url' => urlencode($_SERVER['HTTP_HOST'] ?? ''),
            'correlator' => time()
        ];
        
        // Add custom targeting
        $targeting = $this->generateTargeting();
        if (!empty($targeting)) {
            $cust_params = [];
            foreach ($targeting as $key => $value) {
                $cust_params[] = urlencode($key) . '=' . urlencode($value);
            }
            $params['cust_params'] = implode('&', $cust_params);
        }
        
        // Add video information
        if ($this->video) {
            $params['sz'] = '960x540'; // Video player size
            $params['vid'] = $this->video->id ?? '';
            if (isset($this->video->duration)) {
                $params['vcon'] = $this->video->duration;
            }
        }
        
        $queryString = http_build_query($params);
        return "https://pubads.g.doubleclick.net/gampad/ads?{$queryString}";
    }
    
    /**
     * Generate AWS MediaTailor manifest URL
     */
    private function generateMediaTailorUrl(): string {
        $config = $this->config['ssai']['aws_mediatailor'];
        
        if (empty($config['playback_prefix']) || !$this->video) {
            return '';
        }
        
        $sessionId = uniqid('ion_');
        $playbackUrl = $config['playback_prefix'] . '/' . $sessionId;
        
        // Add targeting parameters
        $targeting = $this->generateTargeting();
        if (!empty($targeting)) {
            $playbackUrl .= '?' . http_build_query($targeting);
        }
        
        return $playbackUrl;
    }
    
    /**
     * Generate Cloudflare Stream SSAI URL
     */
    private function generateCloudflareStreamUrl(): string {
        $config = $this->config['ssai']['cloudflare_stream'];
        
        if (empty($config['ad_url_template']) || !$this->video) {
            return '';
        }
        
        $targeting = $this->generateTargeting();
        $adUrl = str_replace([
            '{VIDEO_ID}',
            '{USER_ID}',
            '{CHANNEL_ID}'
        ], [
            $this->video->id ?? '',
            $this->user->id ?? '',
            $this->channel->id ?? ''
        ], $config['ad_url_template']);
        
        return $adUrl;
    }
    
    /**
     * Generate targeting parameters for ads
     */
    private function generateTargeting(): array {
        $targeting = [];
        
        // Add video information
        if ($this->video) {
            $targeting['video_id'] = $this->video->id ?? '';
            $targeting['content_category'] = $this->video->category ?? 'general';
            $targeting['video_duration'] = $this->video->duration ?? 0;
            $targeting['video_title'] = substr($this->video->title ?? '', 0, 50);
        }
        
        // Add channel information
        if ($this->channel) {
            $targeting['channel_id'] = $this->channel->id ?? '';
            $targeting['channel_category'] = $this->channel->category ?? 'general';
        }
        
        // Add user information (privacy-compliant)
        if ($this->user) {
            $targeting['user_type'] = $this->user->role ?? 'guest';
            $targeting['user_location'] = $this->user->country ?? '';
        }
        
        // Add page information
        $targeting['page_url'] = $_SERVER['HTTP_HOST'] ?? '';
        $targeting['timestamp'] = time();
        
        return $targeting;
    }
    
    /**
     * Get JavaScript configuration for client-side implementation
     */
    public function getJavaScriptConfig(): string {
        $config = $this->getAdConfig();
        return 'window.IONAdConfig = ' . json_encode($config, JSON_PRETTY_PRINT) . ';';
    }
    
    /**
     * Check if ad blocking is detected and handle accordingly
     */
    public function handleAdBlocking(): array {
        $config = $this->config['ad_blocking'];
        
        return [
            'detection_enabled' => $config['detection_enabled'],
            'message' => $config['fallback_message'],
            'recovery_strategies' => $config['recovery_strategies']
        ];
    }
    
    /**
     * Get analytics configuration
     */
    public function getAnalyticsConfig(): array {
        return $this->config['analytics'];
    }
    
    /**
     * Log ad events for analytics
     */
    public function logAdEvent(string $event, array $data = []): void {
        if (!$this->config['analytics']['enabled']) {
            return;
        }
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'video_id' => $this->video->id ?? null,
            'user_id' => $this->user->id ?? null,
            'channel_id' => $this->channel->id ?? null,
            'data' => $data
        ];
        
        // Log to database or external analytics service
        error_log('[ION Ad Analytics] ' . json_encode($logData));
    }
}
?>
