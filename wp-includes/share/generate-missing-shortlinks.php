<?php
/**
 * Script to generate shortlinks for all videos that don't have them
 * Can be run via web browser or command line
 */

// Load dependencies
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/share-manager.php';

// Set execution time limit for large datasets
set_time_limit(300); // 5 minutes

// Check if running from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Web interface
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Generate Missing Shortlinks</title>
        <style>
            body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
            .success { color: #059669; background: #d1fae5; padding: 12px; border-radius: 8px; margin: 10px 0; }
            .error { color: #dc2626; background: #fee2e2; padding: 12px; border-radius: 8px; margin: 10px 0; }
            .info { color: #0369a1; background: #dbeafe; padding: 12px; border-radius: 8px; margin: 10px 0; }
            .stats { background: #f8fafc; padding: 16px; border-radius: 8px; margin: 20px 0; }
            .button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
            .button:hover { background: #2563eb; }
            .progress { width: 100%; background: #e5e7eb; border-radius: 6px; overflow: hidden; margin: 10px 0; }
            .progress-bar { height: 20px; background: #3b82f6; transition: width 0.3s ease; }
        </style>
    </head>
    <body>
        <h1>🔗 Generate Missing Video Shortlinks</h1>
        <p>This tool will generate friendly shortlinks for all videos that don\'t have them yet.</p>';
}

try {
    // Initialize managers
    $share_manager = new IONShareManager($db);
    
    // Get current stats
    $stats = $share_manager->getShareStats();
    
    if (!$is_cli) {
        echo '<div class="stats">';
        echo '<h3>Current Statistics</h3>';
        echo '<ul>';
        echo '<li><strong>Total Videos:</strong> ' . number_format($stats['total_videos']) . '</li>';
        echo '<li><strong>Videos with Shortlinks:</strong> ' . number_format($stats['videos_with_links']) . '</li>';
        echo '<li><strong>Videos without Shortlinks:</strong> ' . number_format($stats['videos_without_links']) . '</li>';
        echo '<li><strong>Coverage:</strong> ' . $stats['percentage_with_links'] . '%</li>';
        echo '<li><strong>Total Clicks:</strong> ' . number_format($stats['total_clicks']) . '</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo "Current Statistics:\n";
        echo "- Total Videos: " . number_format($stats['total_videos']) . "\n";
        echo "- Videos with Shortlinks: " . number_format($stats['videos_with_links']) . "\n";
        echo "- Videos without Shortlinks: " . number_format($stats['videos_without_links']) . "\n";
        echo "- Coverage: " . $stats['percentage_with_links'] . "%\n";
        echo "- Total Clicks: " . number_format($stats['total_clicks']) . "\n\n";
    }
    
    // Check if action is requested
    $action = $_GET['action'] ?? $_SERVER['argv'][1] ?? '';
    $limit = intval($_GET['limit'] ?? $_SERVER['argv'][2] ?? 100);
    
    if ($action === 'generate') {
        if (!$is_cli) {
            echo '<div class="info">Starting shortlink generation...</div>';
            echo '<div class="progress"><div class="progress-bar" style="width: 0%" id="progress"></div></div>';
            echo '<div id="log"></div>';
            ob_flush();
            flush();
        } else {
            echo "Starting shortlink generation (limit: $limit)...\n";
        }
        
        // Generate missing shortlinks in batches
        $total_processed = 0;
        $total_errors = 0;
        $batch_size = 50;
        $current_limit = min($limit, $batch_size);
        
        do {
            $result = $share_manager->generateMissingShortlinks($current_limit);
            
            if ($result['success']) {
                $total_processed += $result['processed'];
                $total_errors += $result['errors'];
                
                if (!$is_cli) {
                    $progress = min(100, ($total_processed / min($stats['videos_without_links'], $limit)) * 100);
                    echo '<script>
                        document.getElementById("progress").style.width = "' . $progress . '%";
                        document.getElementById("log").innerHTML += "<div>Processed batch: ' . $result['processed'] . ' videos</div>";
                    </script>';
                    ob_flush();
                    flush();
                } else {
                    echo "Processed batch: " . $result['processed'] . " videos\n";
                }
                
                // Continue if we processed a full batch and haven't hit our limit
                $continue = $result['processed'] == $current_limit && $total_processed < $limit;
                
            } else {
                if (!$is_cli) {
                    echo '<div class="error">Error: ' . htmlspecialchars($result['error']) . '</div>';
                } else {
                    echo "Error: " . $result['error'] . "\n";
                }
                break;
            }
            
        } while ($continue);
        
        // Final results
        if (!$is_cli) {
            echo '<div class="success">';
            echo '<h3>✅ Generation Complete!</h3>';
            echo '<ul>';
            echo '<li><strong>Total Processed:</strong> ' . number_format($total_processed) . '</li>';
            echo '<li><strong>Errors:</strong> ' . number_format($total_errors) . '</li>';
            echo '<li><strong>Success Rate:</strong> ' . ($total_processed > 0 ? round((($total_processed - $total_errors) / $total_processed) * 100, 1) : 0) . '%</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<p><a href="?" class="button">← Back to Stats</a></p>';
        } else {
            echo "\n✅ Generation Complete!\n";
            echo "- Total Processed: " . number_format($total_processed) . "\n";
            echo "- Errors: " . number_format($total_errors) . "\n";
            echo "- Success Rate: " . ($total_processed > 0 ? round((($total_processed - $total_errors) / $total_processed) * 100, 1) : 0) . "%\n";
        }
        
    } else {
        if (!$is_cli) {
            if ($stats['videos_without_links'] > 0) {
                echo '<div class="info">';
                echo '<h3>Ready to Generate Shortlinks</h3>';
                echo '<p>Found ' . number_format($stats['videos_without_links']) . ' videos without shortlinks.</p>';
                echo '<form method="get">';
                echo '<input type="hidden" name="action" value="generate">';
                echo '<label for="limit">Limit (max videos to process):</label>';
                echo '<input type="number" name="limit" value="' . min(100, $stats['videos_without_links']) . '" min="1" max="1000" style="margin: 0 10px; padding: 5px;">';
                echo '<button type="submit" class="button">Generate Shortlinks</button>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<div class="success">✅ All videos already have shortlinks!</div>';
            }
        } else {
            echo "Usage:\n";
            echo "  php generate-missing-shortlinks.php generate [limit]\n\n";
            echo "Examples:\n";
            echo "  php generate-missing-shortlinks.php generate 100\n";
            echo "  php generate-missing-shortlinks.php generate 1000\n\n";
            
            if ($stats['videos_without_links'] > 0) {
                echo "Ready to generate shortlinks for " . number_format($stats['videos_without_links']) . " videos.\n";
            } else {
                echo "✅ All videos already have shortlinks!\n";
            }
        }
    }
    
} catch (Exception $e) {
    $error_msg = 'Fatal error: ' . $e->getMessage();
    
    if (!$is_cli) {
        echo '<div class="error">' . htmlspecialchars($error_msg) . '</div>';
    } else {
        echo $error_msg . "\n";
    }
    
    error_log("Shortlink generation script error: " . $e->getMessage());
}

if (!$is_cli) {
    echo '</body></html>';
}
?>
