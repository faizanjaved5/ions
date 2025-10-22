<?php
/**
 * Video Ad Integration Helper
 * 
 * Integrates the ad management system with existing video players
 */

require_once __DIR__ . '/AdManager.php';

class VideoAdIntegration {
    private $adManager;
    
    public function __construct($user = null, $video = null, $channel = null) {
        $this->adManager = new AdManager($user, $video, $channel);
    }
    
    /**
     * Generate the required JavaScript includes for ad systems
     */
    public function getAdSystemIncludes(): string {
        $config = $this->adManager->getAdConfig();
        
        if (!$config['enabled']) {
            return '';
        }
        
        $includes = [];
        
        // Google IMA SDK (always include if IMA is enabled)
        if (in_array('ima', $config['systems'])) {
            $includes[] = '<script src="https://imasdk.googleapis.com/js/sdkloader/ima3.js"></script>';
            $includes[] = '<script src="/player/plugins/videojs-contrib-ads.min.js"></script>';
            $includes[] = '<script src="/player/plugins/videojs-ima.min.js"></script>';
        }
        
        // Prebid.js (if enabled)
        if (in_array('prebid', $config['systems'])) {
            $includes[] = '<script src="/player/plugins/prebid.js"></script>';
        }
        
        // ION Ad Manager Plugin
        $includes[] = '<script src="/player/plugins/videojs-ad-manager.js"></script>';
        
        return implode("\n    ", $includes);
    }
    
    /**
     * Generate the ad configuration JavaScript
     */
    public function getAdConfigScript(): string {
        $config = $this->adManager->getAdConfig();
        
        if (!$config['enabled']) {
            return '';
        }
        
        return '<script>' . $this->adManager->getJavaScriptConfig() . '</script>';
    }
    
    /**
     * Generate the player initialization script with ad support
     */
    public function getPlayerInitScript(string $playerId, array $playerOptions = []): string {
        $config = $this->adManager->getAdConfig();
        
        if (!$config['enabled']) {
            return $this->getBasicPlayerScript($playerId, $playerOptions);
        }
        
        $script = "
        // Initialize Video.js player with ad support
        var player = videojs('{$playerId}', " . json_encode($playerOptions) . ");
        
        // Initialize ION Ad Manager
        player.ionAdManager({
            enabled: " . ($config['enabled'] ? 'true' : 'false') . ",
            debug: " . ($config['debug'] ? 'true' : 'false') . ",
            systems: " . json_encode($config['systems']) . ",
            adBlockingDetection: true,
            analytics: true
        });
        
        player.ready(function() {
            console.log('✅ Video.js player with ads ready');
            
            // Enhanced error handling
            player.on('error', function() {
                var error = player.error();
                console.error('❌ Video playback error:', error);
                
                if (error && error.code === 4) {
                    console.log('🔄 Attempting to reload video source...');
                    player.load();
                }
            });
            
            // Track video events for analytics
            player.on('loadeddata', function() {
                console.log('✅ Video loaded successfully');
                if (player.ionAdManager) {
                    player.ionAdManager.logEvent('video_loaded');
                }
            });
            
            player.on('play', function() {
                console.log('▶️ Video started playing');
                if (player.ionAdManager) {
                    player.ionAdManager.logEvent('video_play');
                }
            });
            
            player.on('ended', function() {
                console.log('⏹️ Video finished playing');
                if (player.ionAdManager) {
                    player.ionAdManager.logEvent('video_ended');
                }
            });
            
            player.on('fullscreenchange', function() {
                if (player.isFullscreen()) {
                    console.log('📱 Entered fullscreen mode');
                    if (player.ionAdManager) {
                        player.ionAdManager.logEvent('fullscreen_enter');
                    }
                }
            });
        });";
        
        return $script;
    }
    
    /**
     * Generate basic player script without ads
     */
    private function getBasicPlayerScript(string $playerId, array $playerOptions = []): string {
        return "
        // Initialize Video.js player (ads disabled)
        var player = videojs('{$playerId}', " . json_encode($playerOptions) . ");
        
        player.ready(function() {
            console.log('✅ Video.js player ready (no ads)');
        });
        
        player.on('error', function() {
            var error = player.error();
            console.error('❌ Video playback error:', error);
            
            if (error && error.code === 4) {
                console.log('🔄 Attempting to reload video source...');
                player.load();
            }
        });";
    }
    
    /**
     * Check if ads should be shown for this video context
     */
    public function shouldShowAds(): bool {
        return $this->adManager->isAdsEnabled();
    }
    
    /**
     * Get ad manager instance
     */
    public function getAdManager(): AdManager {
        return $this->adManager;
    }
    
    /**
     * Get ad configuration
     */
    public function getAdConfig(): array {
        return $this->adManager->getAdConfig();
    }
    
    /**
     * Generate complete ad integration for a video player
     */
    public function getCompleteIntegration(string $playerId, array $playerOptions = [], array $videoData = []): array {
        $config = $this->adManager->getAdConfig();
        
        return [
            'enabled' => $config['enabled'],
            'includes' => $this->getAdSystemIncludes(),
            'config_script' => $this->getAdConfigScript(),
            'init_script' => $this->getPlayerInitScript($playerId, $playerOptions),
            'video_id' => $videoData['id'] ?? '',
            'channel_id' => $videoData['channel_id'] ?? '',
            'debug_info' => $config['debug'] ? $config : null
        ];
    }
}
?>
