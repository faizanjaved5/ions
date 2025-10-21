<?php
// error-test.php - Put this in /join/error-test.php
echo "Line 1: PHP is working<br>";

ini_set('display_errors', 1);
error_reporting(E_ALL);
echo "Line 2: Error reporting enabled<br>";

session_start();
echo "Line 3: Session started<br>";

// Test config
echo "Line 4: Testing config load...<br>";
$config_file = __DIR__ . '/../config/config.php';
if (!file_exists($config_file)) {
    die("Config file not found at: $config_file");
}
$config = require $config_file;
echo "Line 5: Config loaded<br>";

// Test database
echo "Line 6: Testing database load...<br>";
$db_file = __DIR__ . '/../config/database.php';
if (!file_exists($db_file)) {
    die("Database file not found at: $db_file");
}
require_once $db_file;
echo "Line 7: Database file included<br>";

// Test global db
global $db;
if ($db) {
    echo "Line 8: Global \$db exists<br>";
    if ($db->isConnected()) {
        echo "Line 9: Database is connected<br>";
    } else {
        echo "Line 9: Database NOT connected<br>";
    }
} else {
    echo "Line 8: Global \$db is NULL<br>";
}

echo "<br><strong>If you see this, PHP is working fine!</strong>";
?>