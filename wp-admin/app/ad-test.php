<?php
/**
 * Ad System Test Page
 * 
 * Test page for validating ad functionality without requiring a real video
 */

session_start();

// Include dependencies
require_once 'VideoAdIntegration.php';

// Create mock objects for testing
$mockUser = (object)[
    'id' => 'test-user-123',
    'role' => 'guest'
];

$mockVideo = (object)[
    'id' => 'test-video-456',
    'title' => 'Test Video for Ad System',
    'duration' => 300, // 5 minutes
    'category' => 'test',
    'video_link' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4'
];

$mockChannel = (object)[
    'id' => 'test-channel-789',
    'category' => 'entertainment'
];

// Initialize ad integration
$adIntegration = new VideoAdIntegration($mockUser, $mockVideo, $mockChannel);
$adConfig = $adIntegration->getAdConfig();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Ad System Test</title>
    
    <!-- Video.js CSS -->
    <link rel="stylesheet" href="/player/video-js.min.css">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .test-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-header h1 {
            color: #2c3e50;
            margin: 0 0 10px 0;
        }
        
        .player-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .debug-panel {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 14px;
        }
        
        .debug-panel h3 {
            margin: 0 0 15px 0;
            color: #3498db;
        }
        
        .config-display {
            background: #34495e;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-enabled { background: #27ae60; }
        .status-disabled { background: #e74c3c; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        
        #console-output {
            height: 200px;
            overflow-y: auto;
            background: #000;
            color: #0f0;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-header">
            <h1>🧪 ION Ad System Test</h1>
            <p>This page tests the ad management system with a sample video and mock data.</p>
        </div>
        
        <!-- Status Overview -->
        <div class="status-grid">
            <div class="status-card">
                <h4>Ad System Status</h4>
                <p>
                    <span class="status-indicator <?= $adConfig['enabled'] ? 'status-enabled' : 'status-disabled' ?>"></span>
                    <?= $adConfig['enabled'] ? 'Enabled' : 'Disabled' ?>
                </p>
            </div>
            
            <div class="status-card">
                <h4>Active Systems</h4>
                <?php if (isset($adConfig['systems'])): ?>
                    <?php foreach ($adConfig['systems'] as $system): ?>
                        <p>
                            <span class="status-indicator status-enabled"></span>
                            <?= strtoupper($system) ?>
                        </p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No ad systems active</p>
                <?php endif; ?>
            </div>
            
            <div class="status-card">
                <h4>Test Configuration</h4>
                <p><strong>User Role:</strong> <?= $mockUser->role ?></p>
                <p><strong>Video Duration:</strong> <?= $mockVideo->duration ?>s</p>
                <p><strong>Debug Mode:</strong> <?= $adConfig['debug'] ? 'On' : 'Off' ?></p>
            </div>
        </div>
        
        <!-- Video Player -->
        <div class="player-container">
            <h3>📺 Test Video Player</h3>
            <video
                id="test-video-player"
                class="video-js vjs-default-skin"
                controls
                preload="metadata"
                data-setup='{"responsive": true, "fluid": true}'
                poster="https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/images/BigBuckBunny.jpg"
            >
                <source src="<?= $mockVideo->video_link ?>" type="video/mp4">
                <p class="vjs-no-js">
                    To view this video please enable JavaScript, and consider upgrading to a web browser that
                    <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                </p>
            </video>
        </div>
        
        <!-- Test Controls -->
        <div class="debug-panel">
            <h3>🎛️ Test Controls</h3>
            <button class="btn btn-primary" onclick="requestAds()">Request Ads</button>
            <button class="btn btn-success" onclick="simulateAdEvent('ad_started')">Simulate Ad Start</button>
            <button class="btn btn-warning" onclick="simulateAdEvent('ad_error')">Simulate Ad Error</button>
            <button class="btn btn-primary" onclick="clearConsole()">Clear Console</button>
        </div>
        
        <!-- Console Output -->
        <div class="debug-panel">
            <h3>📊 Console Output</h3>
            <div id="console-output"></div>
        </div>
        
        <!-- Configuration Display -->
        <div class="debug-panel">
            <h3>⚙️ Current Ad Configuration</h3>
            <div class="config-display"><?= json_encode($adConfig, JSON_PRETTY_PRINT) ?></div>
        </div>
    </div>
    
    <!-- Video.js JavaScript -->
    <script src="/player/video.min.js"></script>
    
    <!-- Ad System Includes -->
    <?= $adIntegration->getAdSystemIncludes() ?>
    
    <!-- Ad Configuration -->
    <?= $adIntegration->getAdConfigScript() ?>
    
    <script>
        // Set video context for ad targeting
        window.IONVideoId = '<?= $mockVideo->id ?>';
        window.IONChannelId = '<?= $mockChannel->id ?>';
        
        // Console logging override
        const originalConsoleLog = console.log;
        const originalConsoleError = console.error;
        const originalConsoleWarn = console.warn;
        
        function logToDisplay(message, type = 'log') {
            const output = document.getElementById('console-output');
            const timestamp = new Date().toLocaleTimeString();
            const colorMap = {
                'log': '#0f0',
                'error': '#f00',
                'warn': '#ff0'
            };
            
            const logLine = document.createElement('div');
            logLine.style.color = colorMap[type] || '#0f0';
            logLine.textContent = `[${timestamp}] ${message}`;
            output.appendChild(logLine);
            output.scrollTop = output.scrollHeight;
        }
        
        console.log = function(...args) {
            originalConsoleLog.apply(console, args);
            logToDisplay(args.join(' '), 'log');
        };
        
        console.error = function(...args) {
            originalConsoleError.apply(console, args);
            logToDisplay(args.join(' '), 'error');
        };
        
        console.warn = function(...args) {
            originalConsoleWarn.apply(console, args);
            logToDisplay(args.join(' '), 'warn');
        };
        
        // Initialize player with ads
        let player;
        
        <?= $adIntegration->getPlayerInitScript('test-video-player', [
            'responsive' => true,
            'fluid' => true,
            'playbackRates' => [0.5, 1, 1.25, 1.5, 2],
            'controls' => true,
            'preload' => 'metadata'
        ]) ?>
        
        // Test functions
        function requestAds() {
            if (player && player.ionAdManager) {
                console.log('🎯 Manually requesting ads...');
                // This would trigger ad request in a real scenario
                player.ionAdManager.logEvent('manual_ad_request', { 
                    trigger: 'test_button',
                    timestamp: Date.now()
                });
            } else {
                console.error('❌ Ad manager not available');
            }
        }
        
        function simulateAdEvent(eventType) {
            if (player && player.ionAdManager) {
                console.log(`📺 Simulating ad event: ${eventType}`);
                player.ionAdManager.logEvent(eventType, { 
                    simulated: true,
                    timestamp: Date.now()
                });
            } else {
                console.error('❌ Ad manager not available');
            }
        }
        
        function clearConsole() {
            document.getElementById('console-output').innerHTML = '';
        }
        
        // Log initial status
        setTimeout(() => {
            console.log('🧪 ION Ad System Test Page Loaded');
            console.log('📊 Ad Configuration Loaded:', !!window.IONAdConfig);
            console.log('🎬 Video.js Player Ready:', !!player);
            console.log('🎯 Ad Manager Available:', !!(player && player.ionAdManager));
            
            if (window.IONAdConfig) {
                console.log('⚙️ Ad Systems Enabled:', window.IONAdConfig.systems);
            }
        }, 1000);
    </script>
</body>
</html>
