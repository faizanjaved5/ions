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
        'Theme' => 'Default',
        'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background' => ['#6366f1', '#7c3aed'],
        'ButtonColor' => '#4f46e5',
        'DefaultMode' => 'LightMode'
    ],
    'golden_theme' => [
        'Theme' => 'Golden',
        'IONLogo' => 'https://ions.com/wp-content/uploads/2025/07/ionlogo-gold.png',
        'Background' => ['#101728', '#101728'],
        'ButtonColor' => '#8a6948',
        'DefaultMode' => 'DarkMode'
    ],
    'purple_theme' => [
        'Theme' => 'Purple',
        'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background' => ['#7c3aed', '#a855f7'],
        'ButtonColor' => '#8b5cf6',
        'DefaultMode' => 'LightMode'
    ],
    'green_theme' => [
        'Theme' => 'Green',
        'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background' => ['#10b981', '#059669'],
        'ButtonColor' => '#16a34a',
        'DefaultMode' => 'LightMode'
    ],
    'dark_mode' => [
        'Theme' => 'Dark',
        'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background' => ['#1f2937', '#374151'],
        'ButtonColor' => '#6366f1',
        'DefaultMode' => 'DarkMode'
    ],
    'custom' => [
        'Theme' => 'Custom',
        'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
        'Background' => ['#ef4444', '#dc2626'],
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

// Safe current prefs for JS and display
$default_prefs = $sample_preferences['default'];
$prefs_for_js = $default_prefs;
$display_prefs_json = json_encode($default_prefs);
if ($current_preferences && ($curr = json_decode($current_preferences, true)) && json_last_error() === JSON_ERROR_NONE) {
    $prefs_for_js = $curr;
    $display_prefs_json = $current_preferences;
}

function isColorDark($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return ($r * 299 + $g * 587 + $b * 114) / 1000 < 128;
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo ($prefs_for_js['DefaultMode'] === 'DarkMode' ? 'dark' : ''); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI Preferences Test Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --background: hsl(240, 10%, 98%);
            --foreground: hsl(240, 10%, 10%);
            --card: hsl(0, 0%, 100%);
            --card-foreground: hsl(240, 10%, 10%);
            --muted: hsl(240, 5%, 96%);
            --muted-foreground: hsl(240, 4%, 46%);
            --border: hsl(240, 6%, 90%);
            --accent: hsl(263, 70%, 50%);
            --destructive: hsl(0, 84%, 60%);
        }

        .dark {
            --background: hsl(240, 10%, 8%);
            --foreground: hsl(240, 5%, 96%);
            --card: hsl(240, 8%, 12%);
            --card-foreground: hsl(240, 5%, 96%);
            --muted: hsl(240, 5%, 16%);
            --muted-foreground: hsl(240, 5%, 64%);
            --border: hsl(240, 5%, 20%);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--background);
            color: var(--foreground);
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        header {
            border-bottom: 1px solid var(--border);
            background-color: var(--card);
            backdrop-filter: blur(8px);
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 24px 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted-foreground);
            margin-bottom: 8px;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .btn {
            padding: 8px 24px;
            border: 1px solid var(--border);
            background-color: transparent;
            color: var(--foreground);
            border-radius: 9999px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
        }

        .btn:hover {
            background-color: var(--muted);
        }

        .btn-primary {
            background-color: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-back {
            padding: 12px 28px;
            font-size: 1rem;
            font-weight: 500;
        }

        main {
            padding: 48px 0;
        }

        section {
            margin-bottom: 64px;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 24px;
        }

        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .theme-card {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .theme-card:hover {
            transform: scale(1.02);
            box-shadow: 0 20px 60px -10px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .theme-card.selected {
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }

        .theme-card-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
            pointer-events: none;
        }

        .theme-card-content {
            position: relative;
            padding: 24px;
        }

        .theme-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .theme-name {
            font-size: 1.25rem;
            font-weight: bold;
        }

        .active-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .color-swatches {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .color-swatch {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .color-swatch > div:first-child {
            min-height: 2.5em;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .swatch-label {
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.7;
        }

        .swatch-code {
            font-size: 0.875rem;
            font-family: 'Courier New', monospace;
            opacity: 0.7;
            word-break: break-all;
            white-space: normal;
        }

        .swatch-preview {
            height: 40px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .swatch-preview:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .hover-text {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .theme-card:hover .hover-text {
            opacity: 1;
        }

        .json-section {
            background-color: var(--card);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .json-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .json-editor {
            width: 100%;
            min-height: 200px;
            background-color: var(--muted);
            border-radius: 8px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: var(--card-foreground);
            border: 1px solid var(--border);
            resize: vertical;
        }

        .json-editor:focus {
            outline: 2px solid var(--accent);
            outline-offset: 0;
        }

        .json-display {
            background-color: var(--muted);
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
        }

        .json-display pre {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: var(--card-foreground);
        }

        .error-message {
            font-size: 0.875rem;
            color: var(--destructive);
            background-color: rgba(239, 68, 68, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 16px 0;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            justify-content: center;
        }

        .success {
            background-color: hsl(152, 69%, 90%);
            color: hsl(152, 69%, 20%);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--destructive);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .options-list {
            background-color: var(--card);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .options-list ul {
            list-style: none;
            padding: 0;
        }

        .options-list li {
            margin-bottom: 8px;
            padding-left: 0;
        }

        .content-wrapper {
            margin-top: 80px;
        }

        .edit-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        form {
            display: contents;
        }
    </style>
</head>
<body>
    <!-- ION Navbar -->
    <?php $ION_NAVBAR_BASE_URL = '/menu/'; ?>
    <?php require_once __DIR__ . '/../menu/ion-navbar-embed.php'; ?>
   
    <div class="content-wrapper">
    <header>
        <div class="container">
            <div class="header-content">
                <div>
                    <h1>UI Preferences Test Tool</h1>
                    <p class="subtitle">Test different UI customization options for the ION Directory. Select a preset or create your own custom preferences.</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary btn-back" onclick="history.back()">Return Back</button>
                    <button class="btn" onclick="toggleDarkMode()">
                        <span id="modeIcon"><?php echo ($prefs_for_js['DefaultMode'] === 'DarkMode' ? '🌙 Dark' : '☀️ Light'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
       
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section>
            <h2>Available Themes</h2>
            <div class="theme-grid" id="themeGrid">
                <?php foreach ($sample_preferences as $id => $prefs): 
                    $name = ucfirst(str_replace('_', ' ', $id)) . ' Theme';
                    $bg1 = $prefs['Background'][0];
                    $bg2 = $prefs['Background'][1];
                    $btn = $prefs['ButtonColor'];
                    $is_selected = false;
                    if (($curr = json_decode($current_preferences, true)) && isset($curr['Theme']) && $curr['Theme'] === $prefs['Theme']) {
                        $is_selected = true;
                    }
                    $isDark = isColorDark($bg1);
                    $textColor = $isDark ? '#ffffff' : '#000000';
                    $mutedTextColor = $isDark ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.6)';
                    $previewTextColor = $isDark ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.7)';
                    $btnTextColor = '#ffffff'; // Fixed white for button preview
                ?>
                <div class="theme-card <?php echo $is_selected ? 'selected' : ''; ?>" 
                     style="background: linear-gradient(135deg, <?php echo $bg1; ?>, <?php echo $bg2; ?>)">
                    <div class="theme-card-gradient"></div>
                    <div class="theme-card-content">
                        <div class="theme-header">
                            <div class="theme-name" style="color: <?php echo $textColor; ?>"><?php echo $name; ?></div>
                            <?php if ($is_selected): ?>
                                <div class="active-badge" style="color: <?php echo $textColor; ?>">
                                    <div class="pulse-dot"></div>
                                    Active
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="color-swatches">
                            <div class="color-swatch">
                                <div>
                                    <div class="swatch-label" style="color: <?php echo $mutedTextColor; ?>">Background</div>
                                    <div class="swatch-code" style="color: <?php echo $mutedTextColor; ?>"><?php echo $bg1; ?> → <?php echo $bg2; ?></div>
                                </div>
                                <div class="swatch-preview" style="background: linear-gradient(135deg, <?php echo $bg1; ?>, <?php echo $bg2; ?>); color: <?php echo $previewTextColor; ?>">
                                    Preview
                                </div>
                            </div>
                            <div class="color-swatch">
                                <div>
                                    <div class="swatch-label" style="color: <?php echo $mutedTextColor; ?>">Button</div>
                                    <div class="swatch-code" style="color: <?php echo $mutedTextColor; ?>"><?php echo $btn; ?></div>
                                </div>
                                <div class="swatch-preview" style="background-color: <?php echo $btn; ?>; color: <?php echo $btnTextColor; ?>">
                                    Preview
                                </div>
                            </div>
                        </div>
                        <div class="button-group">
                            <form method="post">
                                <input type="hidden" name="preference_set" value="<?php echo $id; ?>">
                                <button type="submit" name="apply_preferences" class="btn <?php echo $is_selected ? '' : 'btn-primary'; ?>">
                                    <?php echo $is_selected ? 'Current Theme' : 'Apply Theme'; ?>
                                </button>
                            </form>
                        </div>
                        <div class="hover-text" style="color: <?php echo $mutedTextColor; ?>">
                            <?php echo $is_selected ? 'Currently selected' : 'Apply to use this theme'; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section>
            <div class="json-header">
                <h2 style="margin: 0;">Custom (JSON) Preferences</h2>
                <div class="edit-controls">
                    <button class="btn" id="editBtn" onclick="toggleEdit()">Edit JSON</button>
                    <div class="button-group" id="saveControls" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="saveJson()">Save Changes</button>
                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                    </div>
                </div>
            </div>
            <div class="json-section">
                <form method="post" id="customForm">
                    <div id="jsonDisplay">
                        <div class="json-display">
                            <pre><?php echo htmlspecialchars($display_prefs_json); ?></pre>
                        </div>
                    </div>
                    <div id="jsonEditor" style="display: none;">
                        <textarea class="json-editor" id="jsonTextarea" name="custom_json"></textarea>
                        <div id="jsonError"></div>
                    </div>
                </form>
            </div>
        </section>

        <section>
            <h2>Available Options</h2>
            <div class="options-list">
                <ul>
                    <li><strong>Theme:</strong> Any string to identify your theme</li>
                    <li><strong>IONLogo:</strong> URL to your custom logo image</li>
                    <li><strong>Background:</strong> Array of two hex colors for gradient [start, end]</li>
                    <li><strong>ButtonColor:</strong> Hex color for buttons and accent elements</li>
                    <li><strong>DefaultMode:</strong> "LightMode" or "DarkMode"</li>
                </ul>
            </div>
        </section>
    </main>
    </div>

    <script>
        let isDarkMode = <?php echo ($prefs_for_js['DefaultMode'] === 'DarkMode' ? 'true' : 'false'); ?>;
        let customPreferences = <?php echo json_encode($prefs_for_js); ?>;
        let isEditing = false;

        function toggleDarkMode() {
            isDarkMode = !isDarkMode;
            document.documentElement.classList.toggle('dark');
            document.getElementById('modeIcon').textContent = isDarkMode ? '🌙 Dark' : '☀️ Light';
        }

        function toggleEdit() {
            isEditing = true;
            document.getElementById('jsonDisplay').style.display = 'none';
            document.getElementById('jsonEditor').style.display = 'block';
            document.getElementById('editBtn').style.display = 'none';
            document.getElementById('saveControls').style.display = 'flex';
            document.getElementById('jsonTextarea').value = JSON.stringify(customPreferences, null, 2);
            document.getElementById('jsonError').innerHTML = '';
        }

        function cancelEdit() {
            isEditing = false;
            document.getElementById('jsonDisplay').style.display = 'block';
            document.getElementById('jsonEditor').style.display = 'none';
            document.getElementById('editBtn').style.display = 'block';
            document.getElementById('saveControls').style.display = 'none';
            document.getElementById('jsonError').innerHTML = '';
        }

        function saveJson() {
            try {
                const parsed = JSON.parse(document.getElementById('jsonTextarea').value);
                
                // Validation
                if (!parsed.Theme || typeof parsed.Theme !== 'string') throw new Error('Theme must be a string');
                if (!parsed.IONLogo || typeof parsed.IONLogo !== 'string' || !parsed.IONLogo.match(/^https?:\/\/.+/)) throw new Error('IONLogo must be a valid URL');
                if (!parsed.Background || !Array.isArray(parsed.Background) || parsed.Background.length !== 2 || 
                    !parsed.Background.every(color => typeof color === 'string' && color.match(/^#[0-9A-Fa-f]{6}$/))) {
                    throw new Error('Background must be an array of two valid hex colors');
                }
                if (!parsed.ButtonColor || typeof parsed.ButtonColor !== 'string' || !parsed.ButtonColor.match(/^#[0-9A-Fa-f]{6}$/)) throw new Error('ButtonColor must be a valid hex color');
                if (!parsed.DefaultMode || !['LightMode', 'DarkMode'].includes(parsed.DefaultMode)) throw new Error('DefaultMode must be LightMode or DarkMode');
                
                // If valid, submit the form
                document.getElementById('customForm').submit();
            } catch (error) {
                document.getElementById('jsonError').innerHTML = `<div class="error-message">${error.message}</div>`;
            }
        }
    </script>
</body>
</html>