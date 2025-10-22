<?php
/**
 * Show PHP Error Log Location and Recent R2 Deletion Attempts
 */

// Get PHP error log location
$error_log = ini_get('error_log');
$display_errors = ini_get('display_errors');
$log_errors = ini_get('log_errors');

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Error Log</title>
    <style>
        body { font-family: Arial; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .log { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body>
    <h1>🔍 PHP Error Log Configuration</h1>
    
    <div class="info">
        <strong>PHP Error Log Settings:</strong><br>
        Error Log File: <code><?= $error_log ?: 'Not configured (using system default)' ?></code><br>
        Display Errors: <code><?= $display_errors ?></code><br>
        Log Errors: <code><?= $log_errors ?></code><br>
    </div>
    
    <h2>Recent R2 Deletion Attempts</h2>
    <p>Looking for R2 deletion logs in recent PHP errors...</p>
    
    <?php
    // Try to find and read error log
    $possible_logs = [
        $error_log,
        __DIR__ . '/../uploads/error.log',
        __DIR__ . '/../error.log',
        $_SERVER['DOCUMENT_ROOT'] . '/error.log',
        ini_get('error_log'),
        '/var/log/php_errors.log',
        'C:\\Windows\\Temp\\php_errors.log',
        'C:\\xampp\\apache\\logs\\error.log',
        'C:\\wamp64\\logs\\php_error.log'
    ];
    
    $found_log = null;
    foreach ($possible_logs as $log_path) {
        if ($log_path && file_exists($log_path) && is_readable($log_path)) {
            $found_log = $log_path;
            break;
        }
    }
    
    if ($found_log) {
        echo "<div class='info'>✅ Found log file: <code>$found_log</code></div>";
        
        // Read last 1000 lines and filter for R2 deletion
        $lines = file($found_log);
        $total_lines = count($lines);
        $recent_lines = array_slice($lines, -1000); // Last 1000 lines
        
        $r2_lines = array_filter($recent_lines, function($line) {
            return (
                stripos($line, 'R2 DELETE') !== false ||
                stripos($line, 'deleteFromCloudflareR2') !== false ||
                stripos($line, 'ATTEMPTING R2 DELETION') !== false ||
                stripos($line, 'R2 DELETION RESULT') !== false ||
                stripos($line, 'DELETE DEBUG') !== false
            );
        });
        
        if (empty($r2_lines)) {
            echo "<div class='info'>⚠️ No R2 deletion attempts found in last 1000 log lines</div>";
            echo "<p>This could mean:</p>";
            echo "<ul>";
            echo "<li>No videos have been deleted recently</li>";
            echo "<li>Videos being deleted are not detected as R2 URLs</li>";
            echo "<li>Error logging is not capturing the R2 deletion attempts</li>";
            echo "</ul>";
        } else {
            echo "<div class='info'>✅ Found " . count($r2_lines) . " R2 deletion log entries</div>";
            echo "<div class='log'>";
            foreach ($r2_lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</div>";
        }
        
    } else {
        echo "<div class='info'>⚠️ Could not find PHP error log file</div>";
        echo "<p>Tried these locations:</p><ul>";
        foreach ($possible_logs as $log_path) {
            if ($log_path) {
                $exists = file_exists($log_path) ? '✅ EXISTS' : '❌ NOT FOUND';
                echo "<li><code>$log_path</code> - $exists</li>";
            }
        }
        echo "</ul>";
    }
    ?>
    
    <h2>Manual Test: Trigger R2 Deletion Log</h2>
    <p>Click below to trigger a test log entry:</p>
    <?php
    if (isset($_GET['test_log'])) {
        error_log("🧪 TEST R2 DELETE LOG: This is a test to verify error logging is working");
        error_log("🔍 DELETE DEBUG: test_video_id=999, video_link='https://vid.ions.com/test.mp4', source='Test'");
        echo "<div class='info'>✅ Test log entries written. Check the log above to see if they appear.</div>";
    }
    ?>
    <a href="?test_log=1" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Write Test Log Entry</a>
    
</body>
</html>

