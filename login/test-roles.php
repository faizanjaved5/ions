<?php
/**
 * ION Platform - Role Permissions Test Page
 * Use this to verify role-based access control is working correctly
 */

// Start session and include roles
session_start();
require_once 'roles.php';

// Test with different roles if provided
$test_role = $_GET['test_role'] ?? ($_SESSION['user_role'] ?? 'Viewer');
$user_email = $_SESSION['user_email'] ?? 'test@example.com';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Role Permissions Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
        .role-header { background: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .permissions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .permission-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; }
        .allowed { background: #f0fdf4; border-color: #22c55e; }
        .denied { background: #fef2f2; border-color: #ef4444; }
        .access-yes { color: #22c55e; font-weight: bold; }
        .access-no { color: #ef4444; font-weight: bold; }
        .test-links { margin: 20px 0; }
        .test-links a { display: inline-block; margin: 5px; padding: 8px 15px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="role-header">
        <h1>ION Platform - Role Permissions Test</h1>
        <p><strong>Testing Role:</strong> <?= htmlspecialchars($test_role) ?></p>
        <p><strong>User:</strong> <?= htmlspecialchars($user_email) ?></p>
        
        <div class="test-links">
            <strong>Test Different Roles:</strong>
            <a href="?test_role=Owner">Owner</a>
            <a href="?test_role=Admin">Admin</a>
            <a href="?test_role=Member">Member</a>
            <a href="?test_role=Creator">Creator</a>
            <a href="?test_role=Viewer">Viewer</a>
        </div>
    </div>

    <h2>Section Access Permissions</h2>
    <div class="permissions-grid">
        <?php
        $sections = ['IONS', 'IONEERS', 'ION_VIDS', 'IONSIGHTS'];
        foreach ($sections as $section) {
            $canAccess = IONRoles::canAccessSection($test_role, $section);
            $cardClass = $canAccess ? 'allowed' : 'denied';
            $accessText = $canAccess ? '✅ ALLOWED' : '❌ DENIED';
            $accessClass = $canAccess ? 'access-yes' : 'access-no';
            
            echo "<div class='permission-card $cardClass'>";
            echo "<h3>$section</h3>";
            echo "<p class='$accessClass'>$accessText</p>";
            
            if ($canAccess) {
                echo "<ul>";
                $actions = ['add', 'edit', 'delete', 'upload', 'view'];
                foreach ($actions as $action) {
                    if (IONRoles::canPerformAction($test_role, $section, $action)) {
                        echo "<li>✅ Can $action</li>";
                    }
                }
                echo "</ul>";
            }
            echo "</div>";
        }
        ?>
    </div>

    <h2>Feature Permissions</h2>
    <div class="permissions-grid">
        <?php
        $features = [
            'user_management' => 'User Management',
            'system_settings' => 'System Settings', 
            'bulk_operations' => 'Bulk Operations',
            'export_data' => 'Export Data',
            'view_analytics' => 'View Analytics',
            'moderate_content' => 'Moderate Content'
        ];
        
        foreach ($features as $feature => $label) {
            $hasPermission = IONRoles::hasFeaturePermission($test_role, $feature);
            $cardClass = $hasPermission ? 'allowed' : 'denied';
            $accessText = $hasPermission ? '✅ ALLOWED' : '❌ DENIED';
            $accessClass = $hasPermission ? 'access-yes' : 'access-no';
            
            echo "<div class='permission-card $cardClass'>";
            echo "<h3>$label</h3>";
            echo "<p class='$accessClass'>$accessText</p>";
            echo "</div>";
        }
        ?>
    </div>

    <h2>Navigation Visibility</h2>
    <div class="permissions-grid">
        <?php
        foreach ($sections as $section) {
            $showNav = IONRoles::showNavigation($test_role, $section);
            $cardClass = $showNav ? 'allowed' : 'denied';
            $navText = $showNav ? '👁️ VISIBLE' : '🚫 HIDDEN';
            $navClass = $showNav ? 'access-yes' : 'access-no';
            
            echo "<div class='permission-card $cardClass'>";
            echo "<h3>$section Tab</h3>";
            echo "<p class='$navClass'>$navText</p>";
            echo "</div>";
        }
        ?>
    </div>

    <h2>Test Section Access</h2>
    <div style="margin: 20px 0;">
        <p><strong>Click these links to test actual section access:</strong></p>
        <a href="../app/directory.php" target="_blank">Test IONS Directory</a> |
        <a href="../app/ioneers.php" target="_blank">Test IONEERS</a> |
        <a href="../app/uploaders.php" target="_blank">Test ION VIDS</a>
        <p><small>Note: You'll be redirected if you don't have access to a section.</small></p>
    </div>

    <h2>Role Information</h2>
    <div style="background: #f9fafb; padding: 15px; border-radius: 8px;">
        <h3><?= htmlspecialchars($test_role) ?></h3>
        <p><?= IONRoles::getRoleDescription($test_role) ?></p>
        
        <h4>Accessible Sections:</h4>
        <ul>
            <?php 
            $accessible = IONRoles::getAccessibleSections($test_role);
            if (empty($accessible)) {
                echo "<li>No sections accessible</li>";
            } else {
                foreach ($accessible as $section) {
                    echo "<li>$section</li>";
                }
            }
            ?>
        </ul>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #eff6ff; border-radius: 8px;">
        <h3>🔒 Security Summary</h3>
        <p><strong>Current Implementation:</strong></p>
        <ul>
            <li>✅ <strong>Viewers & Creators</strong> are blocked from IONS and IONEERS</li>
            <li>✅ <strong>Navigation tabs</strong> are hidden for unauthorized sections</li>
            <li>✅ <strong>Page-level protection</strong> redirects unauthorized users</li>
            <li>✅ <strong>Action-based permissions</strong> control what users can do</li>
            <li>✅ <strong>Centralized role management</strong> in roles.php</li>
        </ul>
        
        <p><strong>Role Hierarchy (High to Low Access):</strong><br>
        Owner → Admin → Member → Creator → Viewer</p>
    </div>
</body>
</html>