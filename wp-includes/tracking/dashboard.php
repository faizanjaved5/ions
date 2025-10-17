<?php
/**
 * Video Stats Dashboard Example
 * Shows how to retrieve and display video tracking statistics
 */

// Load config and tracker
$config = require __DIR__ . '/../../config/config.php';
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
    die("Connection failed: " . $e->getMessage());
}

// Initialize tracker
$tracker = new VideoTracker($pdo);

// Get stats for different views
$dateFrom = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$pageSlug = isset($_GET['page']) ? $_GET['page'] : null;

// Get overall stats
$overallStats = $tracker->getStats(null, null, $pageSlug);

// Get date range stats
$dateRangeStats = $tracker->getStats(null, null, $pageSlug, $dateFrom, $dateTo);

// Get top performing videos
$sql = "SELECT 
            video_id,
            video_type,
            page_slug,
            city_slug,
            impressions,
            clicks,
            unique_impressions,
            unique_clicks,
            ROUND(clicks * 100.0 / NULLIF(impressions, 0), 2) as ctr
        FROM video_tracking
        WHERE impressions > 0
        ORDER BY clicks DESC
        LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$topVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily trends
$sql = "SELECT 
            track_date,
            SUM(impressions) as daily_impressions,
            SUM(clicks) as daily_clicks,
            ROUND(SUM(clicks) * 100.0 / NULLIF(SUM(impressions), 0), 2) as daily_ctr
        FROM video_tracking_daily
        WHERE track_date >= :date_from
        GROUP BY track_date
        ORDER BY track_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':date_from' => $dateFrom]);
$dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Tracking Dashboard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .data-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .ctr-good { color: #22c55e; }
        .ctr-medium { color: #f59e0b; }
        .ctr-low { color: #ef4444; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filters form {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        .filters input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filters button {
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .filters button:hover {
            background: #2563eb;
        }
        
        h1, h2 {
            color: #333;
        }
        
        .video-id {
            font-family: monospace;
            font-size: 0.9em;
            color: #666;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .badge-youtube { background: #ff0000; color: white; }
        .badge-vimeo { background: #1ab7ea; color: white; }
        .badge-muvi { background: #673ab7; color: white; }
        .badge-rumble { background: #85c742; color: white; }
    </style>
</head>
<body>
    <div class="dashboard">
        <h1>Video Tracking Dashboard</h1>
        
        <div class="filters">
            <form method="get">
                <div>
                    <label for="from">From:</label>
                    <input type="date" id="from" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div>
                    <label for="to">To:</label>
                    <input type="date" id="to" name="to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div>
                    <label for="page">Page Slug:</label>
                    <input type="text" id="page" name="page" value="<?= htmlspecialchars($pageSlug ?? '') ?>" placeholder="Optional">
                </div>
                <button type="submit">Filter</button>
            </form>
        </div>
        
        <?php
        // Calculate totals
        $totalImpressions = array_sum(array_column($dateRangeStats, 'total_impressions'));
        $totalClicks = array_sum(array_column($dateRangeStats, 'total_clicks'));
        $totalCTR = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Impressions</div>
                <div class="stat-value"><?= number_format($totalImpressions) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Clicks</div>
                <div class="stat-value"><?= number_format($totalClicks) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average CTR</div>
                <div class="stat-value <?= $totalCTR > 5 ? 'ctr-good' : ($totalCTR > 2 ? 'ctr-medium' : 'ctr-low') ?>">
                    <?= $totalCTR ?>%
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Videos Tracked</div>
                <div class="stat-value"><?= count($topVideos) ?></div>
            </div>
        </div>
        
        <h2>Top Performing Videos</h2>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Video ID</th>
                        <th>Type</th>
                        <th>Page</th>
                        <th>City</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topVideos as $video): ?>
                    <tr>
                        <td class="video-id"><?= htmlspecialchars($video['video_id']) ?></td>
                        <td><span class="badge badge-<?= $video['video_type'] ?>"><?= $video['video_type'] ?></span></td>
                        <td><?= htmlspecialchars($video['page_slug']) ?></td>
                        <td><?= htmlspecialchars($video['city_slug'] ?? '-') ?></td>
                        <td><?= number_format($video['impressions']) ?></td>
                        <td><?= number_format($video['clicks']) ?></td>
                        <td class="<?= $video['ctr'] > 5 ? 'ctr-good' : ($video['ctr'] > 2 ? 'ctr-medium' : 'ctr-low') ?>">
                            <?= $video['ctr'] ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <h2>Daily Trends</h2>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyTrends as $day): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($day['track_date'])) ?></td>
                        <td><?= number_format($day['daily_impressions']) ?></td>
                        <td><?= number_format($day['daily_clicks']) ?></td>
                        <td class="<?= $day['daily_ctr'] > 5 ? 'ctr-good' : ($day['daily_ctr'] > 2 ? 'ctr-medium' : 'ctr-low') ?>">
                            <?= $day['daily_ctr'] ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>