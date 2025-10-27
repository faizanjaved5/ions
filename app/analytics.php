<?php
/**
 * Search Analytics Dashboard
 * View search logs and analytics
 */

session_start();

// Load database
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is admin
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    global $db;
    $user_role = $db->get_var("SELECT user_role FROM IONEERS WHERE user_id = " . (int)$_SESSION['user_id']);
    $is_admin = ($user_role === 'Admin' || $user_role === 'Owner');
}

if (!$is_admin) {
    die('<h1>Access Denied</h1><p>You must be an administrator to view search analytics. Your role: ' . htmlspecialchars($user_role ?? 'not found') . '</p>');
}

// Get filters
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
$search_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build WHERE clause
$where = "WHERE search_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
if ($search_type !== 'all') {
    $where .= " AND search_type = '" . $db->esc_sql($search_type) . "'";
}

// Get search statistics
$total_searches = $db->get_var("SELECT COUNT(*) FROM IONSearchLogs {$where}");
$unique_users = $db->get_var("SELECT COUNT(DISTINCT user_id) FROM IONSearchLogs {$where} AND user_id > 0");
$anonymous_searches = $db->get_var("SELECT COUNT(*) FROM IONSearchLogs {$where} AND user_id = 0");
$avg_results = $db->get_var("SELECT AVG(results_count) FROM IONSearchLogs {$where}");

// Get top searches
$top_searches = $db->get_results("
    SELECT 
        search_query,
        COUNT(*) as search_count,
        AVG(results_count) as avg_results,
        MAX(search_date) as last_searched
    FROM IONSearchLogs 
    {$where}
    GROUP BY search_query
    ORDER BY search_count DESC
    LIMIT 50
");

// Get recent searches
$recent_searches = $db->get_results("
    SELECT 
        search_query,
        user_id,
        user_handle,
        results_count,
        search_type,
        search_date
    FROM IONSearchLogs 
    {$where}
    ORDER BY search_date DESC
    LIMIT 100
");

// Get searches by hour (for heatmap)
$searches_by_hour = $db->get_results("
    SELECT 
        HOUR(search_date) as hour,
        COUNT(*) as search_count
    FROM IONSearchLogs 
    {$where}
    GROUP BY HOUR(search_date)
    ORDER BY hour
");

// Get zero-result searches (opportunities for content)
$zero_results = $db->get_results("
    SELECT 
        search_query,
        COUNT(*) as search_count
    FROM IONSearchLogs 
    {$where}
    AND results_count = 0
    GROUP BY search_query
    ORDER BY search_count DESC
    LIMIT 20
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Analytics - ION</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #f59e0b;
            margin-bottom: 30px;
        }
        .filters {
            background: #1e293b;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .filters select, .filters button {
            padding: 10px 15px;
            background: #334155;
            color: white;
            border: 1px solid #475569;
            border-radius: 6px;
            cursor: pointer;
        }
        .filters button {
            background: #f59e0b;
            border: none;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #1e293b;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #f59e0b;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #f59e0b;
        }
        .section {
            background: #1e293b;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .section h2 {
            margin-top: 0;
            color: #f59e0b;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        th {
            background: #334155;
            font-weight: 600;
            color: #f59e0b;
        }
        tr:hover {
            background: #334155;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-creator {
            background: #3b82f6;
            color: white;
        }
        .badge-general {
            background: #10b981;
            color: white;
        }
        .badge-zero {
            background: #ef4444;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Search Analytics Dashboard</h1>
        
        <div class="filters">
            <form method="GET" style="display: flex; gap: 20px; align-items: center;">
                <label>
                    Time Period:
                    <select name="days">
                        <option value="1" <?= $days === 1 ? 'selected' : '' ?>>Last 24 Hours</option>
                        <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90" <?= $days === 90 ? 'selected' : '' ?>>Last 90 Days</option>
                    </select>
                </label>
                <label>
                    Search Type:
                    <select name="type">
                        <option value="all" <?= $search_type === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="general" <?= $search_type === 'general' ? 'selected' : '' ?>>General</option>
                        <option value="creator" <?= $search_type === 'creator' ? 'selected' : '' ?>>Creator (@)</option>
                    </select>
                </label>
                <button type="submit">Update</button>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Searches</h3>
                <div class="value"><?= number_format($total_searches) ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Users</h3>
                <div class="value"><?= number_format($unique_users) ?></div>
            </div>
            <div class="stat-card">
                <h3>Anonymous Searches</h3>
                <div class="value"><?= number_format($anonymous_searches) ?></div>
            </div>
            <div class="stat-card">
                <h3>Avg Results</h3>
                <div class="value"><?= number_format($avg_results, 1) ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2>🔥 Top Search Queries</h2>
            <table>
                <thead>
                    <tr>
                        <th>Search Query</th>
                        <th>Times Searched</th>
                        <th>Avg Results</th>
                        <th>Last Searched</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_searches as $search): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($search->search_query) ?></strong></td>
                            <td><?= number_format($search->search_count) ?></td>
                            <td><?= number_format($search->avg_results, 1) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($search->last_searched)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>⚠️ Zero-Result Searches (Content Opportunities)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Search Query</th>
                        <th>Times Searched</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zero_results as $search): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($search->search_query) ?></strong></td>
                            <td><span class="badge badge-zero"><?= number_format($search->search_count) ?> searches</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>⏱️ Recent Searches</h2>
            <table>
                <thead>
                    <tr>
                        <th>Query</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Results</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_searches as $search): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($search->search_query) ?></strong></td>
                            <td>
                                <?php if ($search->user_id > 0): ?>
                                    @<?= htmlspecialchars($search->user_handle) ?>
                                <?php else: ?>
                                    <em style="color: #94a3b8;">Anonymous</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $search->search_type ?>">
                                    <?= ucfirst($search->search_type) ?>
                                </span>
                            </td>
                            <td><?= number_format($search->results_count) ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($search->search_date)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <p style="text-align: center; color: #64748b; margin-top: 40px;">
            <a href="/search/" style="color: #f59e0b;">← Back to Search</a>
        </p>
    </div>
</body>
</html>
