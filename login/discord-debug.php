<?php
/**
 * Discord OAuth Configuration Debugger
 * Place this in your /login/ directory and visit it to see your exact configuration
 */

session_start();

$config = require __DIR__ . '/../config/config.php';

$discord_client_id = $config['discord_clientid'] ?? 'NOT SET';
$discord_redirect_uri = $config['discord_redirect_uri'] ?? 'NOT SET';

// Generate what the OAuth URL would be
$state = bin2hex(random_bytes(16));
$discord_oauth_url = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
    'client_id' => $discord_client_id,
    'redirect_uri' => $discord_redirect_uri,
    'response_type' => 'code',
    'scope' => 'identify email',
    'state' => $state,
    'prompt' => 'consent'
]);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Discord OAuth Debug</title>
    <style>
        body {
            font-family: monospace;
            background: #1a1a1a;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        .section {
            background: #2d2d2d;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #444;
        }
        .label {
            color: #ffd700;
            font-weight: bold;
        }
        .value {
            color: #4CAF50;
            word-break: break-all;
        }
        .error {
            color: #ff4d4d;
        }
        .warning {
            background: #ff9800;
            color: #000;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background: #4CAF50;
            color: #000;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        code {
            background: #000;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <h1>🔍 Discord OAuth Configuration Debug</h1>
    
    <div class="section">
        <h2>Current Environment</h2>
        <p><span class="label">Current URL:</span> <span class="value"><?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></span></p>
        <p><span class="label">Server Name:</span> <span class="value"><?php echo $_SERVER['SERVER_NAME']; ?></span></p>
        <p><span class="label">HTTPS:</span> <span class="value"><?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'YES' : 'NO'; ?></span></p>
    </div>

    <div class="section">
        <h2>Discord Configuration from config.php</h2>
        <p><span class="label">Client ID:</span> <span class="value"><?php echo htmlspecialchars($discord_client_id); ?></span></p>
        <p><span class="label">Redirect URI (from config):</span> <span class="value"><?php echo htmlspecialchars($discord_redirect_uri); ?></span></p>
    </div>

    <div class="section">
        <h2>Constructed Discord OAuth URL</h2>
        <p><span class="label">Full URL:</span><br><span class="value" style="font-size: 12px;"><?php echo htmlspecialchars($discord_oauth_url); ?></span></p>
        <p><span class="label">URL Decoded Redirect URI:</span> <span class="value"><?php echo urldecode($discord_redirect_uri); ?></span></p>
    </div>

    <div class="section">
        <h2>⚠️ Common Issues Checklist</h2>
        
        <?php if (strpos($discord_redirect_uri, 'NOT SET') !== false): ?>
            <div class="error">❌ Discord redirect URI is NOT SET in config!</div>
        <?php endif; ?>
        
        <?php if (substr($discord_redirect_uri, -1) === '/'): ?>
            <div class="warning">⚠️ Your redirect URI ends with a slash (/). Make sure Discord app settings match exactly!</div>
        <?php endif; ?>
        
        <?php if (strpos($discord_redirect_uri, 'http://') === 0): ?>
            <div class="warning">⚠️ Using HTTP (not HTTPS). Discord may require HTTPS in production.</div>
        <?php endif; ?>
        
        <?php if (strpos($discord_redirect_uri, 'localhost') !== false || strpos($discord_redirect_uri, '127.0.0.1') !== false): ?>
            <div class="warning">⚠️ Using localhost. This should only be used in development!</div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>✅ What to Check in Discord Developer Portal</h2>
        <ol>
            <li>Go to: <a href="https://discord.com/developers/applications" target="_blank" style="color: #5865F2;">Discord Developer Portal</a></li>
            <li>Select your application (ID: <code><?php echo htmlspecialchars($discord_client_id); ?></code>)</li>
            <li>Go to <strong>OAuth2 → General</strong></li>
            <li>Under "Redirects", verify you have <strong>EXACTLY</strong> this redirect URI:</li>
        </ol>
        <div style="background: #000; padding: 15px; margin: 10px 0; border-radius: 4px; border: 2px solid #ffd700;">
            <code style="color: #4CAF50; font-size: 14px;"><?php echo htmlspecialchars($discord_redirect_uri); ?></code>
        </div>
        <p><strong>Important:</strong> The redirect URI must match <em>exactly</em> - including:</p>
        <ul>
            <li>Protocol (http vs https)</li>
            <li>Domain (including www or not)</li>
            <li>Path (including trailing slash or not)</li>
            <li>Case sensitivity</li>
        </ul>
    </div>

    <div class="section">
        <h2>🔧 Quick Fix Solutions</h2>
        
        <h3>Solution 1: Update Discord Developer Portal</h3>
        <p>Add this <strong>exact</strong> redirect URI to your Discord app:</p>
        <div style="background: #000; padding: 10px; border-radius: 4px;">
            <code style="color: #ffd700;"><?php echo htmlspecialchars($discord_redirect_uri); ?></code>
        </div>

        <h3>Solution 2: Update Your config.php</h3>
        <p>If your current environment is different, update your config to match:</p>
        <div style="background: #000; padding: 10px; border-radius: 4px;">
            <code style="color: #ffd700;">'discord_redirect_uri' => '<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/login/discord-oauth.php',</code>
        </div>

        <h3>Solution 3: Multiple Environments</h3>
        <p>If you have dev/staging/production, add ALL redirect URIs to Discord:</p>
        <ul>
            <li><code>http://localhost/login/discord-oauth.php</code> (dev)</li>
            <li><code>https://staging.yourdomain.com/login/discord-oauth.php</code> (staging)</li>
            <li><code>https://yourdomain.com/login/discord-oauth.php</code> (production)</li>
        </ul>
    </div>

    <div class="section">
        <h2>📋 Current Request Details</h2>
        <pre style="background: #000; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php 
        echo "REQUEST_SCHEME: " . ($_SERVER['REQUEST_SCHEME'] ?? 'not set') . "\n";
        echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
        echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n";
        echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'not set') . "\n";
        echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
        echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set') . "\n";
        ?></pre>
    </div>

</body>
</html>