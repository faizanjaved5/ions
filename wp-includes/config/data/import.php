<?php
/**
 * import.php
 * Location: public_html/data/import.php
 * Importer with:
 *  - suppress deprecated & notice warnings
 *  - full progress logging (import.log)
 *  - failed‐row logging (import-failed.log)
 *  - fixed table name IONLocalNetwork
 *  - LONGTEXT columns auto‐created/updated
 *  - indexes on slug, lookup, custom_domain
 *  - cleanup + truncation of long fields
 *  - forced string casting (no NULLs)
 *  - uniqueness on slug, lookup, custom_domain
 *  - auto‑convert of city_name & county_name to LONGTEXT
 */

// 0) Suppress deprecated & notice
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// 1) Logging helpers
function logProgress($msg) {
    $time = date('[Y-m-d H:i:s]');
    echo "$time $msg<br>\n";
    @file_put_contents(__DIR__ . '/import.log', "$time $msg\n", FILE_APPEND);
}
function logFailed($assoc, $error) {
    $time    = date('[Y-m-d H:i:s]');
    $logFile = __DIR__ . '/import-failed.log';
    $fp      = fopen($logFile, 'a');
    if (!$fp) return;
    static $headerWritten = false;
    if (!$headerWritten && filesize($logFile) === 0) {
        fputcsv($fp, array_merge(['timestamp','error'], array_keys($assoc)));
        $headerWritten = true;
    }
    fputcsv($fp, array_merge([$time, $error], array_values($assoc)));
    fclose($fp);
}

// 2) Bootstrap WordPress
$wp_load = __DIR__ . '/../wp-load.php';
if (!file_exists($wp_load)) {
    logProgress("Error: wp-load.php not found at $wp_load");
    exit;
}
require $wp_load;
global $wpdb;
logProgress("WP loaded from: $wp_load");

// 3) Configuration
$table      = 'IONLocalNetwork';   // fixed table name
$startId    = 10001;
$dir        = __DIR__;
$desiredCSV = 'ionlocalnetwork.csv';

// 4) Locate CSV (case‑insensitive)
$csvPath = '';
foreach (scandir($dir) as $f) {
    if (is_file("$dir/$f") && strtolower($f) === strtolower($desiredCSV)) {
        $csvPath = "$dir/$f";
        break;
    }
}
if (!$csvPath) {
    logProgress("Error: CSV '$desiredCSV' not found in $dir");
    exit;
}
logProgress("CSV found at: $csvPath");

// 5) Open CSV
if (!($fh = fopen($csvPath,'r'))) {
    logProgress("Error: could not open CSV");
    exit;
}
logProgress("Opened CSV");

// 6) Read header row
$raw = fgetcsv($fh, 0, ',');
if (!$raw || !is_array($raw)) {
    logProgress("Error: invalid/missing header");
    fclose($fh);
    exit;
}
// build header list, skip Excel’s own “id”
$headers = [];
foreach ($raw as $h) {
    $h = trim($h);
    if (substr($h,0,3) === "\xEF\xBB\xBF") {
        $h = substr($h,3);
    }
    if (strtolower($h) === 'id') continue;
    $headers[] = $h;
}
logProgress("Detected columns: " . implode(', ', $headers));

// 7) Helper to escape column names
function esc($col) {
    return '`' . str_replace('`','``',$col) . '`';
}

// 8) Create or alter table (LONGTEXT columns)
$tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table));
if ($tableExists !== $table) {
    $defs = [];
    foreach ($headers as $c) {
        $defs[] = esc($c) . " LONGTEXT NULL";
    }
    $charset   = $wpdb->get_charset_collate();
    $createSQL = "CREATE TABLE {$table} (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        " . implode(",\n        ", $defs) . ",
        PRIMARY KEY (`id`)
    ) {$charset} AUTO_INCREMENT={$startId};";
    logProgress("Creating table");
    if ($wpdb->query($createSQL) === false) {
        logProgress("Error creating table: " . $wpdb->last_error);
        fclose($fh);
        exit;
    }
    logProgress("Table '{$table}' created");
} else {
    $existingCols = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
    foreach ($headers as $c) {
        if (! in_array($c, $existingCols, true)) {
            $addSQL = "ALTER TABLE `{$table}` ADD COLUMN " . esc($c) . " LONGTEXT NULL";
            logProgress("Adding column {$c}");
            if ($wpdb->query($addSQL) === false) {
                logProgress("Error adding column {$c}: " . $wpdb->last_error);
            }
        }
    }
    // reset AUTO_INCREMENT
    $wpdb->query("ALTER TABLE `{$table}` AUTO_INCREMENT={$startId}");
    logProgress("Reset AUTO_INCREMENT to {$startId}");
}

// ─── NEW: Convert existing city_name & county_name to LONGTEXT ───
$toModify = ['city_name','county_name'];
foreach ($toModify as $col) {
    $modSQL = "
      ALTER TABLE `{$table}`
      MODIFY COLUMN `{$col}` LONGTEXT NULL
    ";
    logProgress("Modifying column {$col} → LONGTEXT");
    if ($wpdb->query($modSQL) === false) {
        logProgress("Error modifying {$col}: " . $wpdb->last_error);
    }
}

// 9) Add lookup indexes
$indexSQL = "
    ALTER TABLE `{$table}`
      ADD INDEX idx_slug (`slug`(255)),
      ADD INDEX idx_lookup (`lookup`(255)),
      ADD INDEX idx_custom_domain (`custom_domain`(255))
";
logProgress("Adding indexes");
if ($wpdb->query($indexSQL) === false) {
    logProgress("Error adding indexes: " . $wpdb->last_error);
} else {
    logProgress("Indexes added");
}

// 10) Import rows with cleanup/truncate & uniqueness
$inserted      = 0;
$rowNum        = 1;
$limits        = [
    'custom_domain'=>255,
    'lookup'       =>255,
    'slug'         =>191,
    'page_URL'     =>2048,
    'channel_name'=>255,
];
$uniqueFields  = ['slug','custom_domain','lookup'];

while (($row = fgetcsv($fh, 0, ',')) !== false) {
    $rowNum++;
    array_shift($row); // drop Excel 'id'
    // normalize length
    $hc = count($headers);
    if (count($row) < $hc) {
        $row = array_pad($row, $hc, '');
    } elseif (count($row) > $hc) {
        $row = array_slice($row, 0, $hc);
    }
    $assoc = array_combine($headers, $row);

    // force strings
    foreach ($assoc as $k => $v) {
        $assoc[$k] = (string)$v;
    }
    // cleanup/truncate
    foreach ($limits as $col => $max) {
        if (isset($assoc[$col])) {
            $v = preg_replace("/[\\x00-\\x1F\\x7F]/u", "", $assoc[$col]);
            if (mb_strlen($v) > $max) {
                $v = mb_substr($v, 0, $max);
            }
            $assoc[$col] = $v;
        }
    }
    // uniqueness
    foreach ($uniqueFields as $col) {
        if (!empty($assoc[$col])) {
            $base = $assoc[$col];
            $new  = $base;
            $i    = 1;
            while ( $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE `$col`=%s", $new
            ) ) > 0 ) {
                $new = $base . '-' . $i++;
            }
            if ($new !== $base) {
                logProgress("Adjusted {$col} on row {$rowNum} → {$new}");
                $assoc[$col] = $new;
            }
        }
    }
    // insert
    if ($wpdb->insert($table, $assoc) === false) {
        $err = $wpdb->last_error;
        logProgress("Error inserting row {$rowNum}: {$err}");
        logFailed($assoc, $err);
    } else {
        $inserted++;
        if ($inserted % 5000 === 0) {
            logProgress("Inserted {$inserted} rows so far");
        }
    }
}

fclose($fh);
logProgress("Import complete: {$inserted} rows added to '{$table}'");
?>