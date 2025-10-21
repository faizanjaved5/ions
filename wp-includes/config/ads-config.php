<?php
/**
 * ION Video Ad Management Configuration
 * 
 * This file contains the configuration settings for video advertising.
 * Supports Google IMA (CSAI), Server-Side Ad Insertion (SSAI), and Prebid.js
 */

return [
    // Global Ad System Settings
    'enabled' => getenv('ION_ADS_ENABLED') ?: true,
    'debug_mode' => getenv('ION_ADS_DEBUG') ?: false,
    
    // Ad System Type Configuration
    'ad_systems' => [
        'ima' => [
            'enabled' => true,
            'name' => 'Google IMA (Client-Side)',
            'description' => 'Client-side ad insertion using Google IMA SDK'
        ],
        'ssai' => [
            'enabled' => false,
            'name' => 'Server-Side Ad Insertion',
            'description' => 'Server-side ad stitching (AWS MediaTailor, etc.)'
        ],
        'prebid' => [
            'enabled' => false,
            'name' => 'Prebid.js Header Bidding',
            'description' => 'Header bidding for increased revenue'
        ]
    ],
    
    // Google IMA Configuration (CSAI)
    'ima' => [
        'sdk_url' => 'https://imasdk.googleapis.com/js/sdkloader/ima3.js',
        'contrib_ads_url' => '/player/plugins/videojs-contrib-ads.min.js',
        'ima_plugin_url' => '/player/plugins/videojs-ima.min.js',
        
        // Default Ad Tag Configuration
        'default_ad_tag' => getenv('ION_IMA_AD_TAG') ?: '',
        
        // Google Ad Manager (GAM) Configuration
        'gam' => [
            'network_id' => getenv('ION_GAM_NETWORK_ID') ?: '',
            'ad_unit_id' => getenv('ION_GAM_AD_UNIT_ID') ?: '/your-network-id/video-ads',
            'custom_targeting' => [
                'content_category' => 'video',
                'player_type' => 'videojs'
            ]
        ],
        
        // Ad Behavior Settings
        'settings' => [
            'auto_play_ad_breaks_on_load' => true,
            'disable_custom_playback_for_ios10_plus' => true,
            'enable_preloading' => true,
            'num_redirects' => 3,
            'show_countdown_timer' => true,
            'vpaid_mode' => 'ENABLED', // ENABLED, DISABLED, INSECURE
            'locale' => 'en',
            'page_url' => null, // Auto-detected if null
            'pp_id' => null, // Publisher Provided ID
            'session_id' => null // Auto-generated if null
        ],
        
        // Ad Break Configuration
        'ad_breaks' => [
            'preroll' => [
                'enabled' => true,
                'offset' => 'start',
                'max_duration' => 30
            ],
            'midroll' => [
                'enabled' => true,
                'offsets' => ['25%', '50%', '75%'], // Percentage or seconds (e.g., 120)
                'max_duration' => 15,
                'min_content_length' => 300 // Only show midrolls for videos longer than 5 minutes
            ],
            'postroll' => [
                'enabled' => true,
                'offset' => 'end',
                'max_duration' => 30
            ]
        ]
    ],
    
    // Server-Side Ad Insertion (SSAI) Configuration
    'ssai' => [
        'provider' => 'aws_mediatailor', // aws_mediatailor, cloudflare_stream, custom
        
        // AWS MediaTailor Configuration
        'aws_mediatailor' => [
            'region' => getenv('AWS_REGION') ?: 'us-east-1',
            'access_key' => getenv('AWS_ACCESS_KEY_ID') ?: '',
            'secret_key' => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
            'configuration_name' => getenv('AWS_MEDIATAILOR_CONFIG') ?: '',
            'playback_prefix' => getenv('AWS_MEDIATAILOR_PLAYBACK_PREFIX') ?: '',
            'session_initialization_endpoint' => getenv('AWS_MEDIATAILOR_SESSION_ENDPOINT') ?: ''
        ],
        
        // Cloudflare Stream SSAI (if available)
        'cloudflare_stream' => [
            'account_id' => getenv('CLOUDFLARE_ACCOUNT_ID') ?: '',
            'api_token' => getenv('CLOUDFLARE_STREAM_API_TOKEN') ?: '',
            'ad_url_template' => getenv('CLOUDFLARE_AD_URL_TEMPLATE') ?: ''
        ]
    ],
    
    // Prebid.js Header Bidding Configuration
    'prebid' => [
        'cdn_url' => 'https://cdn.jsdelivr.net/npm/prebid.js@latest/dist/not-for-prod/prebid.js',
        'timeout' => 2000, // Timeout in milliseconds
        'price_granularity' => 'medium', // low, medium, high, auto, dense, custom
        
        // Bidder Configuration
        'bidders' => [
            'appnexus' => [
                'enabled' => false,
                'placement_id' => getenv('PREBID_APPNEXUS_PLACEMENT_ID') ?: ''
            ],
            'rubicon' => [
                'enabled' => false,
                'account_id' => getenv('PREBID_RUBICON_ACCOUNT_ID') ?: '',
                'site_id' => getenv('PREBID_RUBICON_SITE_ID') ?: '',
                'zone_id' => getenv('PREBID_RUBICON_ZONE_ID') ?: ''
            ]
        ],
        
        // Video Configuration
        'video_config' => [
            'mimes' => ['video/mp4', 'video/webm'],
            'protocols' => [2, 3, 5, 6], // VAST protocols
            'minduration' => 5,
            'maxduration' => 30,
            'w' => 960,
            'h' => 540,
            'startdelay' => 0, // 0 for preroll, -1 for midroll, -2 for postroll
            'placement' => 1, // 1 for in-stream
            'linearity' => 1, // 1 for linear
            'skip' => 0 // 0 for non-skippable, 1 for skippable
        ]
    ],
    
    // Content-Based Ad Controls
    'content_rules' => [
        // Channel-specific ad settings
        'channels' => [
            // Example: disable ads for premium channels
            // 'premium_channel_id' => ['enabled' => false],
            // 'news_channel_id' => ['ima' => ['ad_breaks' => ['midroll' => ['enabled' => false]]]]
        ],
        
        // Video duration-based rules
        'duration_rules' => [
            'short' => [
                'max_duration' => 180, // 3 minutes
                'ad_breaks' => ['midroll' => ['enabled' => false]]
            ],
            'medium' => [
                'min_duration' => 180,
                'max_duration' => 900, // 15 minutes
                'ad_breaks' => ['midroll' => ['offsets' => ['50%']]]
            ],
            'long' => [
                'min_duration' => 900,
                'ad_breaks' => ['midroll' => ['offsets' => ['25%', '50%', '75%']]]
            ]
        ],
        
        // User role-based rules
        'user_roles' => [
            'premium' => ['enabled' => false],
            'subscriber' => ['ima' => ['ad_breaks' => ['preroll' => ['max_duration' => 15]]]],
            'guest' => ['enabled' => true]
        ]
    ],
    
    // Analytics and Tracking
    'analytics' => [
        'enabled' => true,
        'track_quartiles' => true,
        'track_interactions' => true,
        'custom_dimensions' => [
            'video_category' => 'content_category',
            'channel_id' => 'channel',
            'user_type' => 'user_role'
        ]
    ],
    
    // Ad Blocking Detection
    'ad_blocking' => [
        'detection_enabled' => true,
        'fallback_message' => 'Please consider disabling your ad blocker to support our content.',
        'recovery_strategies' => [
            'server_side_fallback' => true,
            'alternative_content' => false
        ]
    ],
    
    // Compliance Settings
    'compliance' => [
        'gdpr_consent_required' => true,
        'ccpa_compliance' => true,
        'coppa_compliance' => false,
        'iab_tcf_version' => '2.0'
    ]
];
?>
