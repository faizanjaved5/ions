<?php
/**
 * ION Ad Management Admin Interface
 * 
 * Provides an interface for configuring and managing video advertisements
 */

session_start();

// Include authentication and dependencies
require_once '../config/config.php';
require_once '../login/roles.php';
require_once 'AdManager.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: /login/');
    exit();
}

$user_role = $_SESSION['user_role'];

// Check if user can access admin features
if (!in_array($user_role, ['Owner', 'Admin'])) {
    header('Location: /app/?error=unauthorized');
    exit();
}

// Load current ad configuration
$adConfig = include(__DIR__ . '/../config/ads-config.php');

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_global_settings':
                $message = updateGlobalSettings($_POST);
                $messageType = 'success';
                break;
                
            case 'update_ima_settings':
                $message = updateIMASettings($_POST);
                $messageType = 'success';
                break;
                
            case 'test_ad_tag':
                $result = testAdTag($_POST['ad_tag_url']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
        }
        
        // Reload configuration after update
        $adConfig = include(__DIR__ . '/../config/ads-config.php');
    }
}

function updateGlobalSettings($data) {
    // Update global ad settings
    $configFile = __DIR__ . '/../config/ads-config.php';
    
    // Read current config
    $config = include($configFile);
    
    // Update settings
    $config['enabled'] = isset($data['ads_enabled']);
    $config['debug_mode'] = isset($data['debug_mode']);
    
    // Update ad systems
    $config['ad_systems']['ima']['enabled'] = isset($data['ima_enabled']);
    $config['ad_systems']['ssai']['enabled'] = isset($data['ssai_enabled']);
    $config['ad_systems']['prebid']['enabled'] = isset($data['prebid_enabled']);
    
    // Save updated config (simplified - in production you'd want proper config management)
    writeConfigFile($configFile, $config);
    
    return 'Global ad settings updated successfully.';
}

function updateIMASettings($data) {
    $configFile = __DIR__ . '/../config/ads-config.php';
    $config = include($configFile);
    
    // Update IMA settings
    $config['ima']['default_ad_tag'] = $data['ad_tag_url'] ?? '';
    $config['ima']['gam']['network_id'] = $data['gam_network_id'] ?? '';
    $config['ima']['gam']['ad_unit_id'] = $data['gam_ad_unit_id'] ?? '';
    
    // Update ad break settings
    $config['ima']['ad_breaks']['preroll']['enabled'] = isset($data['preroll_enabled']);
    $config['ima']['ad_breaks']['midroll']['enabled'] = isset($data['midroll_enabled']);
    $config['ima']['ad_breaks']['postroll']['enabled'] = isset($data['postroll_enabled']);
    
    if (isset($data['midroll_offsets'])) {
        $offsets = array_filter(explode(',', $data['midroll_offsets']));
        $config['ima']['ad_breaks']['midroll']['offsets'] = $offsets;
    }
    
    writeConfigFile($configFile, $config);
    
    return 'IMA settings updated successfully.';
}

function testAdTag($adTagUrl) {
    if (empty($adTagUrl)) {
        return ['success' => false, 'message' => 'Please provide an ad tag URL to test.'];
    }
    
    // Basic URL validation
    if (!filter_var($adTagUrl, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'Invalid ad tag URL format.'];
    }
    
    // Test if URL is reachable (basic check)
    $headers = @get_headers($adTagUrl);
    if (!$headers || strpos($headers[0], '200') === false) {
        return ['success' => false, 'message' => 'Ad tag URL is not accessible or returns an error.'];
    }
    
    return ['success' => true, 'message' => 'Ad tag URL is valid and accessible.'];
}

function writeConfigFile($filename, $config) {
    // Simplified config writing - in production use proper config management
    $content = "<?php\n/**\n * ION Video Ad Management Configuration (Updated via Admin)\n */\n\nreturn " . var_export($config, true) . ";\n?>";
    file_put_contents($filename, $content);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Management - ION Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            border-bottom: 3px solid #3498db;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .nav {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .nav a {
            color: #3498db;
            text-decoration: none;
            margin-right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .nav a:hover {
            background: #ecf0f1;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: #3498db;
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-enabled {
            background: #27ae60;
        }
        
        .status-disabled {
            background: #e74c3c;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .code {
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎯 ION Ad Management</h1>
        <p>Configure and manage video advertising across your platform</p>
    </div>
    
    <div class="nav">
        <a href="/app/">← Back to Dashboard</a>
        <a href="#global">Global Settings</a>
        <a href="#ima">Google IMA</a>
        <a href="#analytics">Analytics</a>
        <a href="#testing">Testing</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Ad System Status Overview -->
        <div class="card">
            <div class="card-header">📊 Ad System Status</div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <h4>Global Settings</h4>
                        <p>
                            <span class="status-indicator <?= $adConfig['enabled'] ? 'status-enabled' : 'status-disabled' ?>"></span>
                            Ads: <?= $adConfig['enabled'] ? 'Enabled' : 'Disabled' ?>
                        </p>
                        <p>
                            <span class="status-indicator <?= $adConfig['debug_mode'] ? 'status-enabled' : 'status-disabled' ?>"></span>
                            Debug Mode: <?= $adConfig['debug_mode'] ? 'On' : 'Off' ?>
                        </p>
                    </div>
                    
                    <div>
                        <h4>Ad Systems</h4>
                        <p>
                            <span class="status-indicator <?= $adConfig['ad_systems']['ima']['enabled'] ? 'status-enabled' : 'status-disabled' ?>"></span>
                            Google IMA: <?= $adConfig['ad_systems']['ima']['enabled'] ? 'Enabled' : 'Disabled' ?>
                        </p>
                        <p>
                            <span class="status-indicator <?= $adConfig['ad_systems']['ssai']['enabled'] ? 'status-enabled' : 'status-disabled' ?>"></span>
                            SSAI: <?= $adConfig['ad_systems']['ssai']['enabled'] ? 'Enabled' : 'Disabled' ?>
                        </p>
                        <p>
                            <span class="status-indicator <?= $adConfig['ad_systems']['prebid']['enabled'] ? 'status-enabled' : 'status-disabled' ?>"></span>
                            Prebid.js: <?= $adConfig['ad_systems']['prebid']['enabled'] ? 'Enabled' : 'Disabled' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Global Settings -->
        <div class="card" id="global">
            <div class="card-header">⚙️ Global Ad Settings</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_global_settings">
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="ads_enabled" name="ads_enabled" <?= $adConfig['enabled'] ? 'checked' : '' ?>>
                            <label for="ads_enabled">Enable Ads Globally</label>
                        </div>
                        <div class="help-text">Master switch for all advertising functionality</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="debug_mode" name="debug_mode" <?= $adConfig['debug_mode'] ? 'checked' : '' ?>>
                            <label for="debug_mode">Debug Mode</label>
                        </div>
                        <div class="help-text">Enable detailed logging and console output for troubleshooting</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ad Systems</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="ima_enabled" name="ima_enabled" <?= $adConfig['ad_systems']['ima']['enabled'] ? 'checked' : '' ?>>
                            <label for="ima_enabled">Google IMA (Client-Side Ads)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="ssai_enabled" name="ssai_enabled" <?= $adConfig['ad_systems']['ssai']['enabled'] ? 'checked' : '' ?>>
                            <label for="ssai_enabled">Server-Side Ad Insertion (SSAI)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="prebid_enabled" name="prebid_enabled" <?= $adConfig['ad_systems']['prebid']['enabled'] ? 'checked' : '' ?>>
                            <label for="prebid_enabled">Prebid.js Header Bidding</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Global Settings</button>
                </form>
            </div>
        </div>
        
        <!-- Google IMA Settings -->
        <div class="card" id="ima">
            <div class="card-header">📺 Google IMA Configuration</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_ima_settings">
                    
                    <div class="form-group">
                        <label for="ad_tag_url">VAST/VMAP Ad Tag URL</label>
                        <input type="url" id="ad_tag_url" name="ad_tag_url" 
                               value="<?= htmlspecialchars($adConfig['ima']['default_ad_tag']) ?>"
                               placeholder="https://pubads.g.doubleclick.net/gampad/ads?...">
                        <div class="help-text">
                            Your Google Ad Manager (GAM) ad tag URL. This should be a VAST or VMAP URL from your ad server.
                        </div>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label for="gam_network_id">GAM Network ID</label>
                            <input type="text" id="gam_network_id" name="gam_network_id" 
                                   value="<?= htmlspecialchars($adConfig['ima']['gam']['network_id']) ?>"
                                   placeholder="12345678">
                            <div class="help-text">Your Google Ad Manager network ID</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="gam_ad_unit_id">GAM Ad Unit ID</label>
                            <input type="text" id="gam_ad_unit_id" name="gam_ad_unit_id" 
                                   value="<?= htmlspecialchars($adConfig['ima']['gam']['ad_unit_id']) ?>"
                                   placeholder="/12345678/video-ads">
                            <div class="help-text">Your ad unit path in GAM</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ad Break Configuration</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="preroll_enabled" name="preroll_enabled" 
                                   <?= $adConfig['ima']['ad_breaks']['preroll']['enabled'] ? 'checked' : '' ?>>
                            <label for="preroll_enabled">Enable Preroll Ads</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="midroll_enabled" name="midroll_enabled" 
                                   <?= $adConfig['ima']['ad_breaks']['midroll']['enabled'] ? 'checked' : '' ?>>
                            <label for="midroll_enabled">Enable Midroll Ads</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="postroll_enabled" name="postroll_enabled" 
                                   <?= $adConfig['ima']['ad_breaks']['postroll']['enabled'] ? 'checked' : '' ?>>
                            <label for="postroll_enabled">Enable Postroll Ads</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="midroll_offsets">Midroll Ad Offsets</label>
                        <input type="text" id="midroll_offsets" name="midroll_offsets" 
                               value="<?= implode(',', $adConfig['ima']['ad_breaks']['midroll']['offsets']) ?>"
                               placeholder="25%,50%,75%">
                        <div class="help-text">
                            Comma-separated list of when to show midroll ads. Use percentages (25%, 50%) or seconds (120, 300).
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update IMA Settings</button>
                </form>
            </div>
        </div>
        
        <!-- Testing Tools -->
        <div class="card" id="testing">
            <div class="card-header">🧪 Testing Tools</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="test_ad_tag">
                    
                    <div class="form-group">
                        <label for="test_ad_tag_url">Test Ad Tag URL</label>
                        <input type="url" id="test_ad_tag_url" name="ad_tag_url" 
                               value="<?= htmlspecialchars($adConfig['ima']['default_ad_tag']) ?>"
                               placeholder="https://pubads.g.doubleclick.net/gampad/ads?...">
                        <div class="help-text">
                            Test if your ad tag URL is accessible and properly formatted.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Test Ad Tag</button>
                </form>
                
                <hr style="margin: 2rem 0;">
                
                <h4>Sample Ad Tag URLs</h4>
                <p><strong>Google Ad Manager Sample:</strong></p>
                <p class="code">https://pubads.g.doubleclick.net/gampad/ads?iu=/21775744923/external/single_ad_samples&sz=640x480&cust_params=sample_ct%3Dlinear&ciu_szs=300x250%2C728x90&gdfp_req=1&output=vast&unviewed_position_start=1&env=vp&impl=s&correlator=[timestamp]</p>
                
                <p><strong>Test Ad (always returns ads):</strong></p>
                <p class="code">https://pubads.g.doubleclick.net/gampad/ads?iu=/21775744923/external/single_preroll_skippable&sz=640x480&gdfp_req=1&output=vast&unviewed_position_start=1&env=vp&impl=s&correlator=[timestamp]</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">⚡ Quick Actions</div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <h4>Configuration Files</h4>
                        <p>Configuration stored in: <span class="code">config/ads-config.php</span></p>
                        <p>Last modified: <?= date('M j, Y g:i A', filemtime(__DIR__ . '/../config/ads-config.php')) ?></p>
                    </div>
                    
                    <div>
                        <h4>Documentation</h4>
                        <p>• <a href="#" target="_blank">Google IMA SDK Documentation</a></p>
                        <p>• <a href="#" target="_blank">VAST/VMAP Specifications</a></p>
                        <p>• <a href="#" target="_blank">Prebid.js Video Guide</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
