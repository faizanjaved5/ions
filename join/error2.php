<?php
// show-errors.php - Put this in /join/show-errors.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Recent PHP Errors</h1>";

// Common error log locations
$possible_logs = [
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    '/var/log/php_errors.log',
    '/var/log/php/error.log',
    ini_get('error_log'),
    $_SERVER['DOCUMENT_ROOT'] . '/../logs/error.log',
    $_SERVER['DOCUMENT_ROOT'] . '/error_log',
    dirname(__FILE__) . '/error_log'
];

$found = false;
foreach ($possible_logs as $log_file) {
    if ($log_file && file_exists($log_file) && is_readable($log_file)) {
        echo "<h2>Found log: " . htmlspecialchars($log_file) . "</h2>";
        
        // Get last 50 lines
        $lines = [];
        $fp = fopen($log_file, 'r');
        if ($fp) {
            while (!feof($fp)) {
                $lines[] = fgets($fp);
                if (count($lines) > 50) {
                    array_shift($lines);
                }
            }
            fclose($fp);
            
            echo "<pre style='background:#f0f0f0; padding:10px; overflow:auto;'>";
            foreach ($lines as $line) {
                // Highlight errors
                if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                    echo "<span style='color:red;'>" . htmlspecialchars($line) . "</span>";
                } else {
                    echo htmlspecialchars($line);
                }
            }
            echo "</pre>";
            $found = true;
            break;
        }
    }
}

if (!$found) {
    echo "<p>Could not find or read any error log files.</p>";
    echo "<p>Checked locations:</p>";
    echo "<ul>";
    foreach ($possible_logs as $log) {
        if ($log) {
            echo "<li>" . htmlspecialchars($log) . "</li>";
        }
    }
    echo "</ul>";
    
    // Try to trigger an error to find log location
    echo "<p>Triggering test error...</p>";
    error_log("TEST ERROR FROM show-errors.php at " . date('Y-m-d H:i:s'));
    trigger_error("TEST ERROR to find log location", E_USER_ERROR);
}
?>