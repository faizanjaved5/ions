<?php
/**
 * Test Google Drive Token Insert
 * Tests if we can insert a token into the database
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Google Drive Insert</title>
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
        .info { color: #0ff; }
        pre { background: #000; padding: 10px; border: 1px solid #333; }
    </style>
</head>
<body>
    <h1>🧪 Test Google Drive Token Insert</h1>
    
    <?php
    // Get session user_id
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo '<div class="error">❌ No user_id in session. Please log in first.</div>';
        exit;
    }
    
    echo '<div class="check">✅ User ID from session: ' . $userId . '</div>';
    
    // Test email
    $testEmail = 'test-' . time() . '@gmail.com';
    echo '<div class="info">📧 Test email: ' . $testEmail . '</div>';
    
    // Connect to database
    try {
        $db = new IONDatabase();
        echo '<div class="check">✅ Database connected</div>';
    } catch (Exception $e) {
        echo '<div class="error">❌ Database connection failed: ' . $e->getMessage() . '</div>';
        exit;
    }
    
    // Try to insert a test token
    echo '<br><h2>Testing INSERT...</h2>';
    
    $testAccessToken = 'test_access_token_' . bin2hex(random_bytes(16));
    $testRefreshToken = 'test_refresh_token_' . bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    echo '<div class="info">🔑 Test access token: ' . substr($testAccessToken, 0, 30) . '...</div>';
    echo '<div class="info">🔑 Test refresh token: ' . substr($testRefreshToken, 0, 30) . '...</div>';
    echo '<div class="info">⏰ Expires at: ' . $expiresAt . '</div>';
    
    try {
        $insertResult = $db->query("
            INSERT INTO IONGoogleDriveTokens (user_id, email, access_token, refresh_token, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ", [
            $userId,
            $testEmail,
            $testAccessToken,
            $testRefreshToken,
            $expiresAt
        ]);
        
        echo '<div class="check">✅ INSERT query executed</div>';
        echo '<div class="info">Result type: ' . gettype($insertResult) . '</div>';
        echo '<div class="info">Result value: ' . var_export($insertResult, true) . '</div>';
        
        // Check for errors
        if ($db->last_error) {
            echo '<div class="error">❌ Database error: ' . $db->last_error . '</div>';
        } else {
            echo '<div class="check">✅ No database errors</div>';
        }
        
        // Get the inserted ID
        $insertId = $db->insert_id;
        echo '<div class="info">📍 Insert ID: ' . $insertId . '</div>';
        
        // Verify the insert
        echo '<br><h2>Verifying INSERT...</h2>';
        
        $verify = $db->get_row("
            SELECT id, user_id, email, expires_at, created_at
            FROM IONGoogleDriveTokens
            WHERE user_id = ? AND email = ?
        ", [$userId, $testEmail]);
        
        if ($verify) {
            echo '<div class="check">✅ Record found in database!</div>';
            echo '<pre>';
            echo 'ID: ' . $verify->id . "\n";
            echo 'User ID: ' . $verify->user_id . "\n";
            echo 'Email: ' . $verify->email . "\n";
            echo 'Expires: ' . $verify->expires_at . "\n";
            echo 'Created: ' . $verify->created_at . "\n";
            echo '</pre>';
            
            // Clean up test record
            echo '<br><h2>Cleaning up...</h2>';
            $db->query("DELETE FROM IONGoogleDriveTokens WHERE id = ?", [$verify->id]);
            echo '<div class="check">✅ Test record deleted</div>';
            
            echo '<br><div class="check" style="font-size: 18px;">✅ DATABASE INSERT/SELECT WORKING PERFECTLY!</div>';
            echo '<div class="info">The database is working. The issue must be in the OAuth callback logic.</div>';
            
        } else {
            echo '<div class="error">❌ Record NOT found in database after INSERT!</div>';
            echo '<div class="warning">This means the INSERT failed silently.</div>';
            
            // Try to find any records for this user
            echo '<br><h2>Checking all records for user ' . $userId . '...</h2>';
            $allRecords = $db->get_results("SELECT * FROM IONGoogleDriveTokens WHERE user_id = ?", [$userId]);
            
            if ($allRecords && count($allRecords) > 0) {
                echo '<div class="info">Found ' . count($allRecords) . ' existing record(s):</div>';
                foreach ($allRecords as $record) {
                    echo '<div class="info">- ' . $record->email . ' (ID: ' . $record->id . ')</div>';
                }
            } else {
                echo '<div class="warning">No records found for this user.</div>';
            }
        }
        
    } catch (Exception $e) {
        echo '<div class="error">❌ Exception during INSERT: ' . $e->getMessage() . '</div>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    }
    ?>
    
</body>
</html>

