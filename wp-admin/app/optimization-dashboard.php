<?php
/**
 * Video Optimization Dashboard
 * 
 * Monitor video optimization status and performance
 */

session_start();

// Include authentication and database
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../login/roles.php';

// Initialize database connection
global $wpdb, $db;
if (!isset($wpdb)) {
    $wpdb = $db; // Use the IONDatabase instance as $wpdb
}

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

// Get optimization statistics
function getOptimizationStats() {
    global $wpdb;
    
    // Verify database connection
    if (!$wpdb || !method_exists($wpdb, 'get_results')) {
        return [
            'error' => true,
            'message' => 'Database connection not available. Please check your database configuration.'
        ];
    }
    
    try {
        // Check if optimization columns exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM IONLocalVideos LIKE 'optimization_status'");
        if (empty($columns)) {
            return ['migration_needed' => true];
        }
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
    
    $stats = [];
    
    // Overall statistics
    $stats['total_videos'] = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos WHERE upload_status = 'Completed'");
    
    $stats['optimization_breakdown'] = $wpdb->get_results(
        "SELECT optimization_status, COUNT(*) as count 
         FROM IONLocalVideos 
         WHERE upload_status = 'Completed' 
         GROUP BY optimization_status"
    );
    
    // Processing queue
    $queueExists = $wpdb->get_var("SHOW TABLES LIKE 'IONVideoQueue'");
    if ($queueExists) {
        $stats['queue_stats'] = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM IONVideoQueue 
             GROUP BY status"
        );
        
        $stats['pending_queue'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM IONVideoQueue WHERE status = 'pending'"
        );
    }
    
    // File size savings
    $stats['compression_stats'] = $wpdb->get_row(
        "SELECT 
            COUNT(*) as optimized_count,
            AVG(compression_ratio) as avg_compression,
            SUM(original_file_size) as total_original_size,
            SUM(optimized_file_size) as total_optimized_size
         FROM IONLocalVideos 
         WHERE optimization_status = 'ready' 
         AND original_file_size > 0 
         AND optimized_file_size > 0"
    );
    
    // Recent activity
    $stats['recent_optimizations'] = $wpdb->get_results(
        "SELECT id, title, optimization_status, optimization_completed_at, compression_ratio
         FROM IONLocalVideos 
         WHERE optimization_completed_at IS NOT NULL 
         ORDER BY optimization_completed_at DESC 
         LIMIT 10"
    );
    
    return $stats;
}

$stats = getOptimizationStats();

// Handle manual optimization trigger
if ($_POST['action'] ?? '' === 'queue_optimization') {
    $videoId = intval($_POST['video_id']);
    
    if ($videoId > 0) {
        global $wpdb;
        
        // Add to optimization queue
        $result = $wpdb->insert('IONVideoQueue', [
            'video_id' => $videoId,
            'priority' => 'high',
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            $message = "Video queued for optimization successfully.";
            $messageType = 'success';
        } else {
            $message = "Failed to queue video for optimization.";
            $messageType = 'error';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Optimization Dashboard - ION</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            background: #ecf0f1;
            border-radius: 10px;
            height: 20px;
            margin: 0.5rem 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: #27ae60;
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .status-list {
            list-style: none;
        }
        
        .status-list li {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-pending { background: #f39c12; }
        .status-processing { background: #3498db; }
        .status-ready { background: #27ae60; }
        .status-error { background: #e74c3c; }
        .status-none { background: #95a5a6; }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        
        .recent-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .recent-table th,
        .recent-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .recent-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .recent-table tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎬 Video Optimization Dashboard</h1>
        <p>Monitor and manage video optimization across your platform</p>
    </div>
    
    <div class="nav">
        <a href="/app/">← Back to Dashboard</a>
        <a href="/app/ad-admin.php">Ad Management</a>
        <a href="/app/video-optimization-migration.php">Migration</a>
        <a href="#queue">Optimization Queue</a>
        <a href="#recent">Recent Activity</a>
    </div>
    
    <div class="container">
        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($stats['error'])): ?>
            <div class="alert alert-error">
                <strong>❌ Database Error:</strong> 
                <?= htmlspecialchars($stats['message']) ?>
                <br><br>
                <strong>Troubleshooting Steps:</strong>
                <ol style="margin: 1rem 0; padding-left: 2rem;">
                    <li>Check that your database configuration is correct in <code>config/config.php</code></li>
                    <li>Verify the database server is running and accessible</li>
                    <li>Ensure the IONLocalVideos table exists</li>
                    <li>Try running the migration: <a href="/app/video-optimization-migration.php" class="btn btn-primary" style="margin-left: 0.5rem;">Run Migration</a></li>
                </ol>
            </div>
        <?php elseif (isset($stats['migration_needed'])): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Migration Required:</strong> 
                Video optimization tables haven't been created yet. 
                <a href="/app/video-optimization-migration.php" class="btn btn-primary" style="margin-left: 1rem;">Run Migration</a>
            </div>
        <?php else: ?>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <!-- Total Videos -->
                <div class="stat-card">
                    <h3>📊 Total Videos</h3>
                    <div class="stat-number"><?= number_format($stats['total_videos']) ?></div>
                    <div class="stat-label">Uploaded videos</div>
                </div>
                
                <!-- Optimization Status Breakdown -->
                <div class="stat-card">
                    <h3>🎯 Optimization Status</h3>
                    <ul class="status-list">
                        <?php if (!empty($stats['optimization_breakdown'])): ?>
                            <?php foreach ($stats['optimization_breakdown'] as $status): ?>
                                <li>
                                    <span>
                                        <span class="status-indicator status-<?= $status->optimization_status ?: 'none' ?>"></span>
                                        <?= ucfirst($status->optimization_status ?: 'None') ?>
                                    </span>
                                    <span><?= number_format($status->count) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No data available</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Queue Status -->
                <?php if (isset($stats['queue_stats'])): ?>
                <div class="stat-card">
                    <h3>📋 Processing Queue</h3>
                    <div class="stat-number"><?= $stats['pending_queue'] ?? 0 ?></div>
                    <div class="stat-label">Videos pending optimization</div>
                    
                    <?php if (!empty($stats['queue_stats'])): ?>
                        <ul class="status-list" style="margin-top: 1rem;">
                            <?php foreach ($stats['queue_stats'] as $queueStatus): ?>
                                <li>
                                    <span><?= ucfirst($queueStatus->status) ?></span>
                                    <span><?= $queueStatus->count ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Compression Statistics -->
                <?php if ($stats['compression_stats'] && $stats['compression_stats']->optimized_count > 0): ?>
                <div class="stat-card">
                    <h3>💾 Storage Savings</h3>
                    <div class="stat-number"><?= round($stats['compression_stats']->avg_compression, 1) ?>%</div>
                    <div class="stat-label">Average compression ratio</div>
                    
                    <?php 
                    $totalSaved = $stats['compression_stats']->total_original_size - $stats['compression_stats']->total_optimized_size;
                    $savedGB = round($totalSaved / (1024 * 1024 * 1024), 2);
                    ?>
                    
                    <div style="margin-top: 1rem;">
                        <div class="stat-label">Total space saved: <strong><?= $savedGB ?> GB</strong></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Optimizations -->
            <?php if (!empty($stats['recent_optimizations'])): ?>
            <div class="stat-card" id="recent">
                <h3>📈 Recent Optimizations</h3>
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Video Title</th>
                            <th>Status</th>
                            <th>Compression</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_optimizations'] as $video): ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($video->title, 0, 50)) ?><?= strlen($video->title) > 50 ? '...' : '' ?></td>
                                <td>
                                    <span class="status-indicator status-<?= $video->optimization_status ?>"></span>
                                    <?= ucfirst($video->optimization_status) ?>
                                </td>
                                <td>
                                    <?= $video->compression_ratio ? round($video->compression_ratio, 1) . '%' : 'N/A' ?>
                                </td>
                                <td><?= $video->optimization_completed_at ? date('M j, g:i A', strtotime($video->optimization_completed_at)) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="stat-card">
                <h3>⚡ Quick Actions</h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="/app/OptimizedUploadHandler.php" class="btn btn-primary">Process Queue Manually</a>
                    <a href="/app/video-optimization-migration.php" class="btn btn-success">Update Configuration</a>
                    <button onclick="refreshStats()" class="btn btn-primary">Refresh Statistics</button>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <h4>Manual Optimization</h4>
                    <form method="POST" style="display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem;">
                        <input type="hidden" name="action" value="queue_optimization">
                        <input type="number" name="video_id" placeholder="Video ID" required style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="submit" class="btn btn-success">Queue for Optimization</button>
                    </form>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
    
    <script>
        function refreshStats() {
            location.reload();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            // Only auto-refresh if user is still active
            if (document.hasFocus()) {
                refreshStats();
            }
        }, 30000);
    </script>
</body>
</html>
