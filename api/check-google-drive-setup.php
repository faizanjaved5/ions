<?php
/**
 * Google Drive Setup Diagnostic Tool
 * Checks if everything is configured correctly
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Google Drive Setup Diagnostic</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1a1a1a;
            color: #0f0;
        }
        .check { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .section { margin: 20px 0; border: 1px solid #333; padding: 15px; }
        h2 { color: #0ff; }
        table { border-collapse: collapse; margin: 10px 0; }
        td { padding: 5px 10px; border: 1px solid #333; }
    </style>
</head>
<body>
    <h1>🔍 Google Drive Setup Diagnostic</h1>
    
    <?php
    $checks = [];
    
    // ============================================
    // CHECK 1: Session
    // ============================================
    echo '<div class="section">';
    echo '<h2>1. Session Check</h2>';
    
    if (isset($_SESSION['user_id'])) {
        echo '<div class="check">✅ user_id in session: ' . $_SESSION['user_id'] . '</div>';
        $checks['session_user_id'] = true;
        $userId = $_SESSION['user_id'];
    } else {
        echo '<div class="error">❌ No user_id in session</div>';
        $checks['session_user_id'] = false;
        $userId = null;
    }
    
    if (isset($_SESSION['user_email'])) {
        echo '<div class="check">✅ user_email in session: ' . $_SESSION['user_email'] . '</div>';
        $checks['session_email'] = true;
    } else {
        echo '<div class="error">❌ No user_email in session</div>';
        $checks['session_email'] = false;
    }
    
    echo '</div>';
    
    // ============================================
    // CHECK 2: Configuration
    // ============================================
    echo '<div class="section">';
    echo '<h2>2. Configuration Check</h2>';
    
    $config = require __DIR__ . '/../config/config.php';
    
    if (!empty($config['google_drive_clientid'])) {
        echo '<div class="check">✅ google_drive_clientid configured</div>';
        $checks['client_id'] = true;
    } else {
        echo '<div class="error">❌ google_drive_clientid NOT configured</div>';
        $checks['client_id'] = false;
    }
    
    if (!empty($config['google_drive_secretid'])) {
        echo '<div class="check">✅ google_drive_secretid configured</div>';
        $checks['client_secret'] = true;
    } else {
        echo '<div class="error">❌ google_drive_secretid NOT configured</div>';
        $checks['client_secret'] = false;
    }
    
    if (!empty($config['google_redirect_uri'])) {
        echo '<div class="check">✅ google_redirect_uri: ' . $config['google_redirect_uri'] . '</div>';
        $checks['redirect_uri'] = true;
    } else {
        echo '<div class="error">❌ google_redirect_uri NOT configured</div>';
        $checks['redirect_uri'] = false;
    }
    
    echo '</div>';
    
    // ============================================
    // CHECK 3: Database Connection
    // ============================================
    echo '<div class="section">';
    echo '<h2>3. Database Connection</h2>';
    
    try {
        $db = new IONDatabase();
        echo '<div class="check">✅ Database connection successful</div>';
        $checks['db_connection'] = true;
    } catch (Exception $e) {
        echo '<div class="error">❌ Database connection failed: ' . $e->getMessage() . '</div>';
        $checks['db_connection'] = false;
        $db = null;
    }
    
    echo '</div>';
    
    // ============================================
    // CHECK 4: Table Exists
    // ============================================
    echo '<div class="section">';
    echo '<h2>4. Table Check</h2>';
    
    if ($db) {
        try {
            $result = $db->query("SHOW TABLES LIKE 'IONGoogleDriveTokens'");
            
            if ($result && $db->num_rows > 0) {
                echo '<div class="check">✅ IONGoogleDriveTokens table exists</div>';
                $checks['table_exists'] = true;
                
                // Check table structure
                $structure = $db->query("DESCRIBE IONGoogleDriveTokens");
                echo '<table>';
                echo '<tr><td><strong>Field</strong></td><td><strong>Type</strong></td><td><strong>Key</strong></td></tr>';
                while ($row = $db->fetch_object()) {
                    echo '<tr><td>' . $row->Field . '</td><td>' . $row->Type . '</td><td>' . $row->Key . '</td></tr>';
                }
                echo '</table>';
                
            } else {
                echo '<div class="error">❌ IONGoogleDriveTokens table DOES NOT exist</div>';
                echo '<div class="warning">⚠️  You need to run the SQL script: iblog/_db/create_google_drive_tokens_table.sql</div>';
                $checks['table_exists'] = false;
            }
        } catch (Exception $e) {
            echo '<div class="error">❌ Error checking table: ' . $e->getMessage() . '</div>';
            $checks['table_exists'] = false;
        }
    } else {
        echo '<div class="error">❌ Cannot check table (no database connection)</div>';
        $checks['table_exists'] = false;
    }
    
    echo '</div>';
    
    // ============================================
    // CHECK 5: Existing Tokens
    // ============================================
    echo '<div class="section">';
    echo '<h2>5. Existing Tokens</h2>';
    
    if ($db && $checks['table_exists'] && $userId) {
        try {
            $tokens = $db->get_results("SELECT id, email, expires_at, created_at FROM IONGoogleDriveTokens WHERE user_id = ?", [$userId]);
            
            if ($tokens && count($tokens) > 0) {
                echo '<div class="check">✅ Found ' . count($tokens) . ' token(s) for user_id: ' . $userId . '</div>';
                echo '<table>';
                echo '<tr><td><strong>ID</strong></td><td><strong>Email</strong></td><td><strong>Expires At</strong></td><td><strong>Created</strong></td></tr>';
                foreach ($tokens as $token) {
                    $expired = strtotime($token->expires_at) < time() ? ' (EXPIRED)' : '';
                    echo '<tr><td>' . $token->id . '</td><td>' . $token->email . '</td><td>' . $token->expires_at . $expired . '</td><td>' . $token->created_at . '</td></tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="warning">⚠️  No tokens found for user_id: ' . $userId . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">❌ Error checking tokens: ' . $e->getMessage() . '</div>';
        }
    } else {
        echo '<div class="warning">⚠️  Cannot check tokens (missing requirements)</div>';
    }
    
    echo '</div>';
    
    // ============================================
    // SUMMARY
    // ============================================
    echo '<div class="section">';
    echo '<h2>Summary</h2>';
    
    $allPassed = array_reduce($checks, function($carry, $item) {
        return $carry && $item;
    }, true);
    
    if ($allPassed) {
        echo '<div class="check" style="font-size: 18px;">✅ All checks passed! Google Drive should work.</div>';
    } else {
        echo '<div class="error" style="font-size: 18px;">❌ Some checks failed. Please fix the issues above.</div>';
        
        // Show specific recommendations
        if (!$checks['table_exists']) {
            echo '<div class="warning"><br><strong>ACTION REQUIRED:</strong><br>';
            echo 'Run this SQL script on your database:<br>';
            echo '<code>iblog/_db/create_google_drive_tokens_table.sql</code></div>';
        }
        
        if (!$checks['session_user_id']) {
            echo '<div class="warning"><br><strong>ACTION REQUIRED:</strong><br>';
            echo 'You must be logged in to use Google Drive integration.</div>';
        }
        
        if (!$checks['client_id'] || !$checks['client_secret']) {
            echo '<div class="warning"><br><strong>ACTION REQUIRED:</strong><br>';
            echo 'Configure Google Drive OAuth credentials in config.php</div>';
        }
    }
    
    echo '</div>';
    ?>
    
</body>
</html>

