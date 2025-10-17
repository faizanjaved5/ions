<?php
/**
 * Video Tracking Queue Processor
 * Run this via cron every minute:
 * * * * * * /usr/bin/php /path/to/process-video-tracking.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

// Load config
$config = require __DIR__ . '/../../config/config.php';

// Include the tracker class
require_once __DIR__ . '/../../includes/video-tracker.php';

// Set up PDO
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Video tracking cron PDO connection failed: " . $e->getMessage());
    exit(1);
}

// Set up Redis (optional)
$redis = null;
if (isset($config['redis_enabled']) && $config['redis_enabled']) {
    try {
        $redis = new Redis();
        $redis->connect($config['redis_host'] ?? '127.0.0.1', $config['redis_port'] ?? 6379);
        if (isset($config['redis_password'])) {
            $redis->auth($config['redis_password']);
        }
    } catch (Exception $e) {
        error_log("Redis connection failed in cron: " . $e->getMessage());
        $redis = null;
    }
}

// Initialize tracker
$tracker = new VideoTracker($pdo, $redis);

// Process the queue
echo "Starting video tracking queue processing...\n";
$startTime = microtime(true);

try {
    $tracker->processQueue();
    $duration = round(microtime(true) - $startTime, 2);
    echo "Queue processed successfully in {$duration} seconds\n";
} catch (Exception $e) {
    error_log("Video tracking queue processing failed: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

file_put_contents(__DIR__ . "/cronlog.txt", date("Y-m-d H:i:s") . " - Cron executed\n", FILE_APPEND);

?>