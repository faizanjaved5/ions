/**
 * ION Video.js Ad Manager Plugin
 * 
 * Comprehensive ad management for Video.js with support for:
 * - Google IMA (Client-Side Ad Insertion)
 * - Server-Side Ad Insertion (SSAI)
 * - Prebid.js Header Bidding
 * - Ad blocking detection and recovery
 */

(function(videojs) {
    'use strict';
    
    // Plugin defaults
    const defaults = {
        enabled: true,
        debug: false,
        systems: ['ima'],
        adBlockingDetection: true,
        analytics: true
    };
    
    /**
     * ION Ad Manager Plugin
     */
    const ionAdManager = function(options) {
        const player = this;
        const settings = videojs.mergeOptions(defaults, options);
        
        // Plugin state
        let adConfig = null;
        let currentAdSystem = null;
        let adBlockingDetected = false;
        let analyticsData = {};
        
        // Initialize the plugin
        function init() {
            console.log('🎯 ION Ad Manager: Initializing...', settings);
            
            if (!settings.enabled) {
                console.log('🎯 ION Ad Manager: Disabled by configuration');
                return;
            }
            
            // Load ad configuration
            adConfig = window.IONAdConfig || settings;
            
            // Check for ad blocking
            if (settings.adBlockingDetection) {
                detectAdBlocking().then(detected => {
                    adBlockingDetected = detected;
                    if (detected) {
                        handleAdBlocking();
                    } else {
                        initializeAdSystems();
                    }
                });
            } else {
                initializeAdSystems();
            }
            
            // Setup analytics
            if (settings.analytics) {
                setupAnalytics();
            }
        }
        
        /**
         * Detect ad blocking
         */
        function detectAdBlocking() {
            return new Promise(resolve => {
                // Create a test element that ad blockers typically block
                const testAd = document.createElement('div');
                testAd.innerHTML = '&nbsp;';
                testAd.className = 'adsbox';
                testAd.style.position = 'absolute';
                testAd.style.left = '-9999px';
                testAd.style.height = '1px';
                testAd.style.width = '1px';
                
                document.body.appendChild(testAd);
                
                setTimeout(() => {
                    const isBlocked = testAd.offsetHeight === 0;
                    document.body.removeChild(testAd);
                    
                    console.log('🛡️ ION Ad Manager: Ad blocking detected:', isBlocked);
                    resolve(isBlocked);
                }, 100);
            });
        }
        
        /**
         * Handle ad blocking detection
         */
        function handleAdBlocking() {
            console.log('🛡️ ION Ad Manager: Handling ad blocking');
            
            const config = adConfig.ad_blocking || {};
            
            if (config.recovery_strategies?.server_side_fallback) {
                // Try to use SSAI as fallback
                if (adConfig.systems.includes('ssai')) {
                    console.log('🛡️ ION Ad Manager: Falling back to SSAI');
                    initializeSSAI();
                    return;
                }
            }
            
            // Show message to user (optional)
            if (config.message) {
                showAdBlockingMessage(config.message);
            }
            
            // Continue without ads
            console.log('🛡️ ION Ad Manager: Continuing without ads');
        }
        
        /**
         * Show ad blocking message
         */
        function showAdBlockingMessage(message) {
            const overlay = document.createElement('div');
            overlay.className = 'ion-ad-blocking-overlay';
            overlay.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 20px;
            `;
            
            overlay.innerHTML = `
                <div>
                    <p>${message}</p>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="margin-top: 10px; padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Continue
                    </button>
                </div>
            `;
            
            player.el().appendChild(overlay);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (overlay.parentElement) {
                    overlay.remove();
                }
            }, 10000);
        }
        
        /**
         * Initialize ad systems
         */
        function initializeAdSystems() {
            const systems = adConfig.systems || settings.systems;
            
            console.log('🎯 ION Ad Manager: Initializing ad systems:', systems);
            
            // Try systems in order of preference
            for (const system of systems) {
                switch (system) {
                    case 'prebid':
                        if (initializePrebid()) {
                            currentAdSystem = 'prebid';
                            return;
                        }
                        break;
                    case 'ima':
                        if (initializeIMA()) {
                            currentAdSystem = 'ima';
                            return;
                        }
                        break;
                    case 'ssai':
                        if (initializeSSAI()) {
                            currentAdSystem = 'ssai';
                            return;
                        }
                        break;
                }
            }
            
            console.log('🎯 ION Ad Manager: No ad systems could be initialized');
        }
        
        /**
         * Initialize Google IMA
         */
        function initializeIMA() {
            console.log('🎯 ION Ad Manager: Initializing Google IMA');
            
            if (!adConfig.ima || !adConfig.ima.ad_tag_url) {
                console.warn('🎯 ION Ad Manager: IMA configuration missing');
                return false;
            }
            
            // Check if IMA SDK is loaded
            if (typeof google === 'undefined' || !google.ima) {
                console.warn('🎯 ION Ad Manager: Google IMA SDK not loaded');
                return false;
            }
            
            // Check if videojs-contrib-ads is loaded
            if (!player.ads) {
                console.warn('🎯 ION Ad Manager: videojs-contrib-ads not loaded');
                return false;
            }
            
            // Initialize contrib-ads
            player.ads({
                debug: settings.debug,
                timeout: 5000,
                prerollTimeout: 1000,
                postrollTimeout: 1000
            });
            
            // Setup IMA
            const imaOptions = {
                id: player.id(),
                adTagUrl: adConfig.ima.ad_tag_url,
                adsRenderingSettings: {
                    restoreCustomPlaybackStateOnAdBreakComplete: true,
                    enablePreloading: adConfig.ima.settings?.enable_preloading || true
                },
                showCountdown: adConfig.ima.settings?.show_countdown_timer || true,
                locale: adConfig.ima.settings?.locale || 'en'
            };
            
            // Apply additional IMA settings
            if (adConfig.ima.settings) {
                Object.assign(imaOptions, adConfig.ima.settings);
            }
            
            // Initialize IMA plugin
            player.ima(imaOptions);
            
            // Setup IMA event listeners
            setupIMAEventListeners();
            
            console.log('✅ ION Ad Manager: Google IMA initialized');
            return true;
        }
        
        /**
         * Setup IMA event listeners
         */
        function setupIMAEventListeners() {
            player.on('ads-ad-started', () => {
                console.log('📺 ION Ad Manager: Ad started');
                logAnalyticsEvent('ad_started');
            });
            
            player.on('ads-ad-ended', () => {
                console.log('📺 ION Ad Manager: Ad ended');
                logAnalyticsEvent('ad_completed');
            });
            
            player.on('ads-ad-skipped', () => {
                console.log('📺 ION Ad Manager: Ad skipped');
                logAnalyticsEvent('ad_skipped');
            });
            
            player.on('ads-ad-error', (event) => {
                console.error('❌ ION Ad Manager: Ad error:', event);
                logAnalyticsEvent('ad_error', { error: event.error });
            });
            
            // IMA-specific events
            if (player.ima) {
                player.ima.addEventListener(google.ima.AdEvent.Type.IMPRESSION, () => {
                    logAnalyticsEvent('ad_impression');
                });
                
                player.ima.addEventListener(google.ima.AdEvent.Type.CLICK, () => {
                    logAnalyticsEvent('ad_click');
                });
                
                player.ima.addEventListener(google.ima.AdEvent.Type.FIRST_QUARTILE, () => {
                    logAnalyticsEvent('ad_quartile', { quartile: 1 });
                });
                
                player.ima.addEventListener(google.ima.AdEvent.Type.MIDPOINT, () => {
                    logAnalyticsEvent('ad_quartile', { quartile: 2 });
                });
                
                player.ima.addEventListener(google.ima.AdEvent.Type.THIRD_QUARTILE, () => {
                    logAnalyticsEvent('ad_quartile', { quartile: 3 });
                });
            }
        }
        
        /**
         * Initialize Server-Side Ad Insertion
         */
        function initializeSSAI() {
            console.log('🎯 ION Ad Manager: Initializing SSAI');
            
            if (!adConfig.ssai || !adConfig.ssai.manifest_url) {
                console.warn('🎯 ION Ad Manager: SSAI configuration missing');
                return false;
            }
            
            // Replace video source with SSAI manifest
            const currentSources = player.currentSources();
            const ssaiSource = {
                src: adConfig.ssai.manifest_url,
                type: 'application/x-mpegURL' // HLS
            };
            
            player.src(ssaiSource);
            
            // Setup SSAI event tracking (if supported by provider)
            setupSSAIEventListeners();
            
            console.log('✅ ION Ad Manager: SSAI initialized');
            return true;
        }
        
        /**
         * Setup SSAI event listeners
         */
        function setupSSAIEventListeners() {
            // SSAI events are typically handled server-side
            // But we can track basic playback events
            
            player.on('timeupdate', () => {
                // Check for ad break markers in the stream
                // This would depend on the SSAI provider's implementation
            });
        }
        
        /**
         * Initialize Prebid.js Header Bidding
         */
        function initializePrebid() {
            console.log('🎯 ION Ad Manager: Initializing Prebid.js');
            
            if (!window.pbjs || !adConfig.prebid) {
                console.warn('🎯 ION Ad Manager: Prebid.js not available');
                return false;
            }
            
            const prebidConfig = adConfig.prebid;
            
            // Configure Prebid
            window.pbjs.que.push(() => {
                // Set configuration
                window.pbjs.setConfig({
                    debug: settings.debug,
                    priceGranularity: prebidConfig.price_granularity || 'medium',
                    timeout: prebidConfig.timeout || 2000
                });
                
                // Add video ad units
                const adUnits = [{
                    code: 'video-ad-slot',
                    mediaTypes: {
                        video: prebidConfig.video_config
                    },
                    bids: generatePrebidBids(prebidConfig.enabled_bidders)
                }];
                
                window.pbjs.addAdUnits(adUnits);
                
                // Request bids
                window.pbjs.requestBids({
                    timeout: prebidConfig.timeout,
                    bidsBackHandler: handlePrebidBids
                });
            });
            
            console.log('✅ ION Ad Manager: Prebid.js initialized');
            return true;
        }
        
        /**
         * Generate Prebid bidder configurations
         */
        function generatePrebidBids(enabledBidders) {
            const bids = [];
            
            Object.entries(enabledBidders).forEach(([bidder, config]) => {
                if (config.enabled) {
                    bids.push({
                        bidder: bidder,
                        params: config
                    });
                }
            });
            
            return bids;
        }
        
        /**
         * Handle Prebid bid responses
         */
        function handlePrebidBids() {
            const adUnits = window.pbjs.getAdUnits();
            const winningBid = window.pbjs.getHighestCpmBids('video-ad-slot')[0];
            
            if (winningBid) {
                console.log('🎯 ION Ad Manager: Prebid winning bid:', winningBid);
                
                // Use winning bid to create VAST URL for IMA
                const vastUrl = winningBid.vastUrl || generateVastUrlFromBid(winningBid);
                
                if (vastUrl) {
                    // Initialize IMA with the winning bid's VAST URL
                    adConfig.ima = adConfig.ima || {};
                    adConfig.ima.ad_tag_url = vastUrl;
                    initializeIMA();
                }
            } else {
                console.log('🎯 ION Ad Manager: No Prebid bids, falling back to IMA');
                initializeIMA();
            }
        }
        
        /**
         * Generate VAST URL from Prebid bid
         */
        function generateVastUrlFromBid(bid) {
            // This would typically involve calling the bidder's win URL
            // and getting back a VAST response
            return bid.vastUrl;
        }
        
        /**
         * Setup analytics tracking
         */
        function setupAnalytics() {
            analyticsData = {
                sessionId: generateSessionId(),
                playerId: player.id(),
                videoId: getVideoId(),
                timestamp: Date.now()
            };
            
            console.log('📊 ION Ad Manager: Analytics initialized:', analyticsData);
        }
        
        /**
         * Log analytics events
         */
        function logAnalyticsEvent(event, data = {}) {
            if (!settings.analytics) {
                return;
            }
            
            const eventData = {
                ...analyticsData,
                event: event,
                timestamp: Date.now(),
                adSystem: currentAdSystem,
                ...data
            };
            
            // Log to console in debug mode
            if (settings.debug) {
                console.log('📊 ION Ad Manager: Analytics event:', eventData);
            }
            
            // Send to analytics endpoint
            sendAnalyticsEvent(eventData);
        }
        
        /**
         * Send analytics event to server
         */
        function sendAnalyticsEvent(eventData) {
            // Use navigator.sendBeacon for reliability
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(eventData)], {
                    type: 'application/json'
                });
                navigator.sendBeacon('/api/analytics/ad-events', blob);
            } else {
                // Fallback to fetch
                fetch('/api/analytics/ad-events', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(eventData)
                }).catch(error => {
                    console.warn('📊 ION Ad Manager: Analytics error:', error);
                });
            }
        }
        
        /**
         * Utility functions
         */
        function generateSessionId() {
            return 'ion_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        }
        
        function getVideoId() {
            // Extract video ID from player or page context
            return player.options().videoId || window.IONVideoId || '';
        }
        
        // Public API
        player.ionAdManager = {
            getConfig: () => adConfig,
            getCurrentSystem: () => currentAdSystem,
            isAdBlockingDetected: () => adBlockingDetected,
            logEvent: logAnalyticsEvent,
            
            // Manual ad system initialization
            initializeIMA: initializeIMA,
            initializeSSAI: initializeSSAI,
            initializePrebid: initializePrebid
        };
        
        // Initialize when player is ready
        player.ready(() => {
            init();
        });
        
        // Cleanup on dispose
        player.on('dispose', () => {
            // Cleanup ad systems
            if (currentAdSystem === 'ima' && player.ima) {
                player.ima.dispose();
            }
        });
    };
    
    // Register the plugin
    videojs.registerPlugin('ionAdManager', ionAdManager);
    
})(window.videojs);
