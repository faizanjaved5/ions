<?php
/**
 * geocodes-import.php
 * Location: public_html/data/geocodes-import.php
 *
 * - NO WordPress. Uses PDO.
 * - Reads DB creds from /config/config.php (expects array with host, dbname, username, password).
 * - Reads IONGeoCodes.csv in the SAME folder.
 * - Creates IONGeoCodes with correct schema (TEXT name fields, VARCHAR(64) code list).
 * - Normalizes booleans/numbers, cleans Geo Point, extracts 5-digit FIPS codes.
 * - Batch inserts with a prepared statement; logs progress and failures.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
@ini_set('auto_detect_line_endings', '1');

/* ---------- Logging helpers ---------- */
function logProgress($msg) {
    $time = date('[Y-m-d H:i:s]');
    echo "$time $msg<br>\n";
    @file_put_contents(__DIR__ . '/import.log', "$time $msg\n", FILE_APPEND);
}
function logFailed(array $assoc, string $error) {
    $time = date('[Y-m-d H:i:s]');
    $logFile = __DIR__ . '/import-failed.log';
    $first   = !file_exists($logFile) || filesize($logFile) === 0;
    $fp = fopen($logFile, 'a');
    if (!$fp) return;
    if ($first) {
        fputcsv($fp, array_merge(['timestamp','error'], array_keys($assoc)));
    }
    fputcsv($fp, array_merge([$time, $error], array_values($assoc)));
    fclose($fp);
}

/* ---------- Load DB config ---------- */
$cfg = null;
$cfgFile = __DIR__ . '/../config/config.php';
if (file_exists($cfgFile)) {
    $cfg = include $cfgFile;
} else {
    logProgress("Error: config file not found at $cfgFile");
    exit;
}

/* Support multiple possible config shapes */
$dbHost = $dbName = $dbUser = $dbPass = null;
if (is_array($cfg)) {
    // Preferred: ['host'=>..., 'dbname'=>..., 'username'=>..., 'password'=>...]
    $dbHost = $cfg['host']     ?? null;
    $dbName = $cfg['dbname']   ?? null;
    $dbUser = $cfg['username'] ?? null;
    $dbPass = $cfg['password'] ?? null;

    // Fallback if nested under 'database'
    if (!$dbHost && isset($cfg['database']) && is_array($cfg['database'])) {
        $dbHost = $cfg['database']['host']     ?? null;
        $dbName = $cfg['database']['dbname']   ?? null;
        $dbUser = $cfg['database']['username'] ?? null;
        $dbPass = $cfg['database']['password'] ?? null;
    }
}
if (!$dbHost || !$dbName || !$dbUser) {
    logProgress("Error: DB credentials not found in $cfgFile");
    exit;
}
logProgress("Using DB: {$dbName} @ {$dbHost} (user: {$dbUser})");

/* ---------- Connect PDO ---------- */
try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (Throwable $e) {
    logProgress("Error: DB connection failed - " . $e->getMessage());
    exit;
}

/* ---------- CSV location ---------- */
$table   = 'IONGeoCodes';
$csvName = 'IONGeoCodes.csv';
$csvPath = '';

foreach (scandir(__DIR__) as $f) {
    if (is_file(__DIR__ . "/$f") && strtolower($f) === strtolower($csvName)) {
        $csvPath = __DIR__ . "/$f";
        break;
    }
}
if (!$csvPath) {
    logProgress("Error: CSV '$csvName' not found in " . __DIR__);
    exit;
}
logProgress("CSV: $csvPath");

/* ---------- Create table if needed ---------- */
$createSQL = "
CREATE TABLE IF NOT EXISTS `$table` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zip_code` CHAR(5) NOT NULL,
  `official_usps_city_name` VARCHAR(128) NOT NULL,
  `official_usps_state_code` CHAR(2) NOT NULL,
  `official_state_name` VARCHAR(128) NOT NULL,
  `zcta` TINYINT(1) NULL,
  `zcta_parent` CHAR(5) NULL,
  `population` INT NULL,
  `density` DECIMAL(12,4) NULL,
  `primary_official_county_code` CHAR(5) NULL,
  `primary_official_county_name` TEXT NULL,
  `county_weights` TEXT NULL,
  `official_county_name` TEXT NULL,
  `official_county_code` VARCHAR(64) NULL,
  `imprecise` TINYINT(1) NULL,
  `military` TINYINT(1) NULL,
  `timezone` VARCHAR(64) NULL,
  `geo_point` VARCHAR(64) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_zip` (`zip_code`),
  KEY `idx_state` (`official_usps_state_code`),
  KEY `idx_county_code` (`primary_official_county_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$pdo->exec($createSQL);
logProgress("Table ready: $table");

/* ---------- Self-heal schema (safe even if already correct) ---------- */
try {
    $col = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'official_county_code'")->fetch();
    if ($col && stripos($col->Type ?? '', 'char(5)') !== false) {
        logProgress("Widening official_county_code to VARCHAR(64)...");
        $pdo->exec("ALTER TABLE `$table` MODIFY `official_county_code` VARCHAR(64) NULL");
        logProgress("official_county_code widened to VARCHAR(64).");
    }
    foreach (['official_county_name', 'primary_official_county_name'] as $nm) {
        $c = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$nm'")->fetch();
        if ($c && stripos($c->Type ?? '', 'varchar(') !== false) {
            logProgress("Widening $nm to TEXT...");
            $pdo->exec("ALTER TABLE `$table` MODIFY `$nm` TEXT NULL");
            logProgress("$nm widened to TEXT.");
        }
    }
} catch (Throwable $e) {
    logProgress("Schema self-heal warning: " . $e->getMessage());
}

/* ---------- Open CSV ---------- */
$fh = fopen($csvPath, 'r');
if (!$fh) {
    logProgress("Error: could not open CSV");
    exit;
}

/* ---------- Read & normalize header ---------- */
$rawHeader = fgetcsv($fh, 0, ',');
if (!$rawHeader) {
    logProgress("Error: missing/invalid header row");
    fclose($fh);
    exit;
}
$header = array_map(function($h){
    $h = trim($h ?? '');
    if (substr($h,0,3) === "\xEF\xBB\xBF") $h = substr($h,3); // strip BOM
    return strtolower($h);
}, $rawHeader);

/* Map CSV header → DB columns (case-insensitive) */
$map = [
  'zip code'                      => 'zip_code',
  'official usps city name'       => 'official_usps_city_name',
  'official usps state code'      => 'official_usps_state_code',
  'official state name'           => 'official_state_name',
  'zcta'                          => 'zcta',
  'zcta parent'                   => 'zcta_parent',
  'population'                    => 'population',
  'density'                       => 'density',
  'primary official county code'  => 'primary_official_county_code',
  'primary official county name'  => 'primary_official_county_name',
  'county weights'                => 'county_weights',
  'official county name'          => 'official_county_name',
  'official county code'          => 'official_county_code',
  'imprecise'                     => 'imprecise',
  'military'                      => 'military',
  'timezone'                      => 'timezone',
  'geo point'                     => 'geo_point',
];

/* Build column index map */
$idx = []; // csvIndex => dbColumn
for ($i=0; $i<count($header); $i++) {
    $h = $header[$i];
    if (isset($map[$h])) $idx[$i] = $map[$h];
}
$required = ['zip_code','official_usps_city_name','official_usps_state_code','official_state_name'];
foreach ($required as $colname) {
    if (!in_array($colname, $idx, true)) {
        logProgress("Error: required column '$colname' not found in CSV header");
        fclose($fh);
        exit;
    }
}
logProgress('Header mapped: ' . implode(', ', array_values($idx)));

/* ---------- Prepare INSERT ---------- */
$insertSql = "
INSERT INTO `$table` (
  zip_code, official_usps_city_name, official_usps_state_code, official_state_name,
  zcta, zcta_parent, population, density,
  primary_official_county_code, primary_official_county_name, county_weights,
  official_county_name, official_county_code, imprecise, military, timezone, geo_point
) VALUES (
  :zip_code, :official_usps_city_name, :official_usps_state_code, :official_state_name,
  :zcta, :zcta_parent, :population, :density,
  :primary_official_county_code, :primary_official_county_name, :county_weights,
  :official_county_name, :official_county_code, :imprecise, :military, :timezone, :geo_point
)";
$stmt = $pdo->prepare($insertSql);

/* ---------- Helpers ---------- */
$boolify = function($v) {
    if ($v === '' || $v === null) return null;
    $v = strtolower($v);
    return in_array($v, ['1','y','yes','true','t'], true) ? 1 : 0;
};
$numOrNull = function($v) {
    if ($v === '' || $v === null) return null;
    $v = str_replace([',',' '], '', $v);
    return is_numeric($v) ? $v + 0 : null;
};
$cleanName = function($v, $max = null) {
    if ($v === '' || $v === null) return null;
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    if ($max !== null && mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v === '' ? null : $v;
};

/* ---------- Ingest ---------- */
$inserted = 0;
$rowNum   = 1; // header consumed

// Use a transaction for speed; chunk every 10k rows to keep it safe
$pdo->beginTransaction();

while (($row = fgetcsv($fh, 0, ',')) !== false) {
    $rowNum++;

    // Build associative record from mapped header
    $rec = [
      'zip_code'                         => '',
      'official_usps_city_name'          => '',
      'official_usps_state_code'         => '',
      'official_state_name'              => '',
      'zcta'                             => null,
      'zcta_parent'                      => null,
      'population'                       => null,
      'density'                          => null,
      'primary_official_county_code'     => null,
      'primary_official_county_name'     => null,
      'county_weights'                   => null,
      'official_county_name'             => null,
      'official_county_code'             => null,
      'imprecise'                        => null,
      'military'                         => null,
      'timezone'                         => null,
      'geo_point'                        => null,
    ];

    foreach ($idx as $i => $dbCol) {
        $val = isset($row[$i]) ? trim((string)$row[$i]) : '';
        $rec[$dbCol] = $val;
    }

    // Normalize
    $rec['zip_code']   = str_pad(preg_replace('/\D/','', $rec['zip_code']), 5, '0', STR_PAD_LEFT);
    $rec['zcta']       = $boolify($rec['zcta']);
    $rec['imprecise']  = $boolify($rec['imprecise']);
    $rec['military']   = $boolify($rec['military']);
    $rec['population'] = $numOrNull($rec['population']);
    $rec['density']    = $numOrNull($rec['density']);

    if (!empty($rec['geo_point'])) {
        $rec['geo_point'] = str_replace(["\r","\n"], '', $rec['geo_point']);
    }

    // County codes: keep only 5-digit FIPS; allow comma-separated list
    $codes = [];
    if (!empty($rec['official_county_code'])) {
        foreach (preg_split('/[;,]/', $rec['official_county_code']) as $part) {
            if (preg_match('/\b(\d{5})\b/', $part, $m)) $codes[] = $m[1];
        }
        $codes = array_values(array_unique($codes));
        $rec['official_county_code'] = $codes ? substr(implode(',', $codes), 0, 64) : null;
    } else {
        $rec['official_county_code'] = null;
    }

    // Primary county code: single 5-digit FIPS
    if (!empty($rec['primary_official_county_code'])) {
        if (preg_match('/\b(\d{5})\b/', $rec['primary_official_county_code'], $m)) {
            $rec['primary_official_county_code'] = $m[1];
        } else {
            $rec['primary_official_county_code'] = null;
        }
    } elseif (!empty($codes)) {
        $rec['primary_official_county_code'] = $codes[0];
    }

    // County names: strip control chars / collapse spaces
    $rec['official_county_name']         = $cleanName($rec['official_county_name']);
    $rec['primary_official_county_name'] = $cleanName($rec['primary_official_county_name']);

    // Insert
    try {
        $stmt->execute([
            ':zip_code'                        => $rec['zip_code'],
            ':official_usps_city_name'         => $rec['official_usps_city_name'] ?: null,
            ':official_usps_state_code'        => $rec['official_usps_state_code'] ?: null,
            ':official_state_name'             => $rec['official_state_name'] ?: null,
            ':zcta'                            => $rec['zcta'],
            ':zcta_parent'                     => $rec['zcta_parent'] ?: null,
            ':population'                      => $rec['population'],
            ':density'                         => $rec['density'],
            ':primary_official_county_code'    => $rec['primary_official_county_code'],
            ':primary_official_county_name'    => $rec['primary_official_county_name'],
            ':county_weights'                  => $rec['county_weights'] ?: null,
            ':official_county_name'            => $rec['official_county_name'],
            ':official_county_code'            => $rec['official_county_code'],
            ':imprecise'                       => $rec['imprecise'],
            ':military'                        => $rec['military'],
            ':timezone'                        => $rec['timezone'] ?: null,
            ':geo_point'                       => $rec['geo_point'] ?: null,
        ]);
        $inserted++;
        if ($inserted % 5000 === 0) {
            logProgress("Inserted $inserted rows...");
            $pdo->commit();
            $pdo->beginTransaction();
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
        logProgress("Row $rowNum FAILED: $err");
        logFailed($rec, $err);
    }
}
$pdo->commit();
fclose($fh);

logProgress("DONE. Inserted $inserted rows into `$table`.");
