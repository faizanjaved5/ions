<?php
/**
 * TEST: Simulate saving a Google Drive token to the database
 * This will help us identify if there's a database issue
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();

header('Content-Type: text/html; charset=UTF-8');

echo '<pre style="background: #000; color: #0f0; padding: 20px; font-family: monospace; font-size: 14px;">';
echo "═══════════════════════════════════════════════════════════\n";
echo "🔬 GOOGLE DRIVE TOKEN SAVE TEST\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Check session
echo "📋 SESSION CHECK:\n";
echo "   user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "   user_email: " . ($_SESSION['user_email'] ?? 'NOT SET') . "\n";
echo "   All keys: " . implode(', ', array_keys($_SESSION)) . "\n\n";

// Get user ID
$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['user_email'] ?? 'test@example.com';

if (!$userId && isset($_SESSION['user_email'])) {
    echo "📧 Looking up user_id from email...\n";
    try {
        $db = new IONDatabase();
        $user = $db->get_row("SELECT user_id FROM IONEERS WHERE email = ?", [$_SESSION['user_email']]);
        if ($user) {
            $userId = $user->user_id;
            echo "   ✅ Found user_id: " . $userId . "\n\n";
        } else {
            echo "   ❌ User not found in IONEERS table\n\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    }
}

if (!$userId) {
    echo "❌ No user_id available - cannot test\n";
    echo "</pre>";
    exit;
}

// Test database connection
echo "🔌 DATABASE CONNECTION TEST:\n";
try {
    $db = new IONDatabase();
    echo "   ✅ Connection successful\n\n";
} catch (Exception $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
    echo "</pre>";
    exit;
}

// Check if table exists
echo "📊 TABLE CHECK:\n";
try {
    $tableCheck = $db->get_var("SHOW TABLES LIKE 'IONGoogleDriveTokens'");
    if ($tableCheck) {
        echo "   ✅ IONGoogleDriveTokens table exists\n\n";
    } else {
        echo "   ❌ IONGoogleDriveTokens table does NOT exist\n";
        echo "   Please run the SQL script: _db/create_google_drive_tokens_table.sql\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error checking table: " . $e->getMessage() . "\n\n";
}

// Test INSERT
echo "🔑 TEST INSERT:\n";
$testEmail = 'test_' . time() . '@example.com';
$testAccessToken = 'test_access_token_' . bin2hex(random_bytes(16));
$testRefreshToken = 'test_refresh_token_' . bin2hex(random_bytes(16));
$expiresAt = date('Y-m-d H:i:s', time() + 3600);

echo "   Test data:\n";
echo "   - user_id: $userId\n";
echo "   - email: $testEmail\n";
echo "   - access_token: " . substr($testAccessToken, 0, 20) . "...\n";
echo "   - refresh_token: " . substr($testRefreshToken, 0, 20) . "...\n";
echo "   - expires_at: $expiresAt\n\n";

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
    
    echo "   ✅ INSERT executed\n";
    echo "   Result: " . var_export($insertResult, true) . "\n";
    echo "   Result type: " . gettype($insertResult) . "\n\n";
    
    if ($db->last_error) {
        echo "   ❌ Database error: " . $db->last_error . "\n\n";
    }
    
    // Verify
    echo "🔍 VERIFY INSERT:\n";
    $verify = $db->get_row("SELECT * FROM IONGoogleDriveTokens WHERE email = ?", [$testEmail]);
    if ($verify) {
        echo "   ✅ Record found in database!\n";
        echo "   ID: " . $verify->id . "\n";
        echo "   User ID: " . $verify->user_id . "\n";
        echo "   Email: " . $verify->email . "\n\n";
        
        // Clean up test record
        echo "🧹 CLEANUP:\n";
        $db->query("DELETE FROM IONGoogleDriveTokens WHERE email = ?", [$testEmail]);
        echo "   ✅ Test record deleted\n\n";
    } else {
        echo "   ❌ Record NOT found in database\n";
        echo "   Verify result: " . var_export($verify, true) . "\n\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "✅ TEST COMPLETE\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "</pre>";
?>

