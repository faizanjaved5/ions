<?php
// Add this at the very top of preferences.php
require_once __DIR__ . '/../config/database.php';
require_once(__DIR__ . '/../login/session.php');

error_log('preferences.php: Database environment loaded.');

// Use your custom database class instead of $wpdb - SAME AS directory.php
$wpdb = $db; // This assigns your custom database object to $wpdb
error_log('preferences.php: Database object assigned: ' . (is_object($wpdb) ? 'SUCCESS' : 'FAILED'));

// Additional debugging
if (!$wpdb) {
    error_log('ERROR: $wpdb is null - database connection failed');
    die('Database connection failed');
}

if (!is_object($wpdb)) {
    error_log('ERROR: $wpdb is not an object - type: ' . gettype($wpdb));
    die('Database object is invalid');
}

// Test if the database connection actually works
try {
    $test_result = $wpdb->get_var("SELECT 1");
    error_log('Database connection test: ' . ($test_result == 1 ? 'SUCCESS' : 'FAILED'));
} catch (Exception $e) {
    error_log('Database connection test failed: ' . $e->getMessage());
    die('Database connection test failed: ' . $e->getMessage());
}

// Sample preference configurations for testing
$sample_preferences = [
    'default' => [
        'Theme'       => 'Default',
        'IONLogo'     => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background'  => ['#6366f1', '#7c3aed'],
        'ButtonColor' => '#4f46e5',
        'DefaultMode' => 'LightMode'
    ],
    'golden_theme' => [
        'Theme'       => 'Golden',
        'IONLogo'     => 'https://ions.com/wp-content/uploads/2025/07/ionlogo-gold.png',
        'Background'  => ['#101728', '#101728'],
        'ButtonColor' => '#8a6948',
        'DefaultMode' => 'DarkMode'
    ],
    'purple_theme'    => [
        'Theme'       => 'Purple',
        'IONLogo'     => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background'  => ['#7c3aed', '#a855f7'],
        'ButtonColor' => '#8b5cf6',
        'DefaultMode' => 'LightMode'
    ],
    'green_theme'     => [
        'Theme'       => 'Green',
        'IONLogo'     => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background'  => ['#10b981', '#059669'],
        'ButtonColor' => '#16a34a',
        'DefaultMode' => 'LightMode'
    ],
    'dark_mode'       => [
        'Theme'       => 'Dark',
        'IONLogo'     => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background'  => ['#1f2937', '#374151'],
        'ButtonColor' => '#6366f1',
        'DefaultMode' => 'DarkMode'
    ],
    'custom' => [
        'Theme'       => 'Custom',
        'IONLogo'     => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background'  => ['#ef4444', '#dc2626'],
        'ButtonColor' => '#e11d48',
        'DefaultMode' => 'LightMode'
    ]
];

// Handle preference updates
if ($_POST && isset($_POST['apply_preferences']) && isset($_POST['preference_set'])) {
    $user_email = $_SESSION['user_email'] ?? '';
    $preference_set = $_POST['preference_set'];
    
    if (isset($sample_preferences[$preference_set])) {
        $preferences_json = json_encode($sample_preferences[$preference_set]);
        
        $result = $wpdb->update(
            'IONEERS',
            ['preferences' => $preferences_json],
            ['email' => $user_email],
            ['%s'],
            ['%s']
        );
        
        if ($result !== false) {
            $success_message = "Preferences updated successfully! <a href='directory.php'>View Directory</a>";
        } else {
            $error_message = "Failed to update preferences: " . $wpdb->last_error;
        }
    }
}

// Handle custom JSON input
if ($_POST && isset($_POST['apply_custom']) && isset($_POST['custom_json'])) {
    $user_email = $_SESSION['user_email'] ?? '';
    $custom_json = $_POST['custom_json'];
    
    $decoded = json_decode($custom_json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $result = $wpdb->update(
            'IONEERS',
            ['preferences' => $custom_json],
            ['email' => $user_email],
            ['%s'],
            ['%s']
        );
        
        if ($result !== false) {
            $success_message = "Custom preferences applied successfully! <a href='directory.php'>View Directory</a>";
        } else {
            $error_message = "Failed to update preferences: " . $wpdb->last_error;
        }
    } else {
        $error_message = "Invalid JSON format. Please check your syntax.";
    }
}

error_log('WPDB debug - wpdb object: ' . (is_object($wpdb) ? 'EXISTS' : 'NULL'));
error_log('WPDB debug - wpdb type: ' . gettype($wpdb));
if (isset($db)) {
    error_log('DB debug - db object: ' . (is_object($db) ? 'EXISTS' : 'NULL'));
}

// Get current user preferences
$user_email = $_SESSION['user_email'] ?? '';
$current_preferences = $wpdb->get_var($wpdb->prepare("SELECT preferences FROM IONEERS WHERE email = %s", $user_email));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test UI Preferences - ION Directory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            /* max-width: 1200px; */
            margin: 0 auto;
            /* padding: 20px; */
            line-height: 1.6;
            background: #0a0e1a;
            color: #e5e7eb;
        }
        
        h1, h2, h3 {
            color: #f59e0b;
        }
        
        .preference-card {
            border: 1px solid #2d3748;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #1a1f2e;
        }
        
        .preference-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .color-preview {
            width: 60px;
            height: 40px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .gradient-preview {
            background: linear-gradient(135deg, var(--start), var(--end));
        }
        
        .button-preview {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
        }
        
        .current-preferences {
            background: #1e293b;
            border-color: #f59e0b;
        }
        
        .current-preferences pre {
            color: #d1d5db;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        textarea {
            width: 100%;
            height: 200px;
            font-family: monospace;
            font-size: 14px;
        }
        
        button {
            background: #f59e0b;
            color: #0a0e1a;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        button:hover {
            background: #d97706;
        }
        
        a {
            color: #f59e0b;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .content-wrapper {
            margin-top: 80px;
        }
    </style>
</head>
<body>
    <!-- ION Navbar -->
    <?php $ION_NAVBAR_BASE_URL = '/menu/'; ?>
    <?php require_once __DIR__ . '/../menu/ion-navbar-embed.php'; ?>
    
    <div class="content-wrapper">
    <h1>UI Preferences Test Tool</h1>
    <p>Test different UI customization options for the ION Directory. Select a preset or create your own custom preferences.</p>
    
    <?php if (isset($success_message)): ?>
        <div class="success"><?= $success_message ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="error"><?= $error_message ?></div>
    <?php endif; ?>
    
    <p><a href="directory.php">← Back to Directory</a></p>

    <h2>Current Preferences</h2>
    <div class="preference-card current-preferences">
        <pre><?= $current_preferences ? htmlspecialchars($current_preferences) : 'No preferences set (using defaults)' ?></pre>
    </div>
    
    <h2>Preset Themes</h2>
    
    <?php foreach ($sample_preferences as $key => $prefs): ?>
    <div class="preference-card">
        <h3><?= ucfirst(str_replace('_', ' ', $key)) ?> Theme</h3>
        
        <div class="preference-preview">
            <div class="color-preview gradient-preview" style="--start: <?= $prefs['Background'][0] ?>; --end: <?= $prefs['Background'][1] ?>"></div>
            <div class="color-preview button-preview" style="background: <?= $prefs['ButtonColor'] ?>">Button</div>
            <span><strong>Mode:</strong> <?= $prefs['DefaultMode'] ?></span>
        </div>
        
        <div style="font-size: 14px; color: #666; margin-bottom: 15px;">
            <strong>Background:</strong> <?= implode(' → ', $prefs['Background']) ?><br>
            <strong>Button Color:</strong> <?= $prefs['ButtonColor'] ?><br>
            <strong>Logo:</strong> <?= strlen($prefs['IONLogo']) > 50 ? substr($prefs['IONLogo'], 0, 50) . '...' : $prefs['IONLogo'] ?>
        </div>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="preference_set" value="<?= $key ?>">
            <button type="submit" name="apply_preferences">Apply This Theme</button>
        </form>
    </div>
    <?php endforeach; ?>
    
    <h2>Custom JSON Preferences</h2>
    <div class="preference-card">
        <p>Enter your custom preferences in JSON format:</p>
        <form method="post">
            <textarea name="custom_json" placeholder='{
  "Theme": "My Custom Theme",
  "IONLogo": "https://example.com/my-logo.png",
  "Background": ["#ff6b6b", "#4ecdc4"],
  "ButtonColor": "#45b7d1",
  "DefaultMode": "LightMode"
}'><?= $current_preferences ?></textarea>
            <br><br>
            <button type="submit" name="apply_custom">Apply Custom Preferences</button>
        </form>
    </div>
    
    <h2>Available Options</h2>
    <div class="preference-card">
        <ul>
            <li><strong>Theme:</strong> Any string to identify your theme</li>
            <li><strong>IONLogo:</strong> URL to your custom logo image</li>
            <li><strong>Background:</strong> Array of two hex colors for gradient [start, end]</li>
            <li><strong>ButtonColor:</strong> Hex color for buttons and accent elements</li>
            <li><strong>DefaultMode:</strong> "LightMode" or "DarkMode"</li>
        </ul>
    </div>
    </div>
    
</body>
</html>