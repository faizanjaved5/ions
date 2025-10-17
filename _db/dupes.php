<?php
// Database connection settings - update these with your actual credentials
$host     = 'localhost';
$dbname   = 'u185424179_WtONJ';
$username = 'u185424179_S4e4w';  
$password = '04JE8wHMrl';

// CSV file path - update this with your actual file path
$csvFile = 'addcities.csv';

// Table name updated to IONLocalNext
$tableName = 'IONLocalNext';

// PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Open the CSV file
if (($handle = fopen($csvFile, "r")) === FALSE) {
    die("Error opening the CSV file.");
}

// Collect unique slugs from CSV with samples
$csvSlugs = [];
$allCsvSlugs = []; // For samples
$rowNum = 0;
fgetcsv($handle); // Skip header

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $rowNum++;
    if (count($data) >= 7) { // Basic check for valid row
        // Handle split population (same logic as import)
        if (count($data) > 7) {
            $pop_parts = array_slice($data, 5, count($data) - 6);
            $pop_str = implode('', array_map('trim', $pop_parts));
            $slugName_raw = trim(end($data));
            $data = array_slice($data, 0, 5);
            $data[] = $pop_str;
            $data[] = $slugName_raw;
        }
        
        if (count($data) == 7) {
            $slugName = trim($data[6]);
            if (!empty($slugName)) {
                $csvSlugs[$slugName] = true; // Unique (without prefix)
                $allCsvSlugs[] = $slugName; // For samples
            }
        }
    }
}
fclose($handle);

$csvSlugList = array_keys($csvSlugs);
if (empty($csvSlugList)) {
    echo "No valid slugs found in CSV.\n";
    exit;
}

// DB totals and specifics
$totalDbRecords = $pdo->query("SELECT COUNT(*) FROM $tableName")->fetchColumn();
echo "Total records in $tableName table: $totalDbRecords\n\n";

echo "=== CSV SLUG SAMPLES ===\n";
echo "First 10 CSV slugs: " . implode(', ', array_slice($allCsvSlugs, 0, 10)) . "\n";
echo "Last 10 CSV slugs: " . implode(', ', array_slice($allCsvSlugs, -10)) . "\n";
echo "Does CSV have 'dubai'? " . (in_array('dubai', $csvSlugs) ? 'YES' : 'NO') . "\n";
echo "Does CSV have 'karachi'? " . (in_array('karachi', $csvSlugs) ? 'YES' : 'NO') . "\n\n";

// Check specific examples in DB (with "ion-" prefix added to CSV slug)
$examples = ['dubai', 'karachi', 'san-jose'];
echo "=== SPECIFIC EXAMPLE CHECKS (with 'ion-' prefix) ===\n";
foreach ($examples as $ex) {
    $prefixedEx = 'ion-' . $ex;
    $stmtEx = $pdo->prepare("SELECT id, slug, city_name, created_at FROM $tableName WHERE slug = ? LIMIT 1");
    $stmtEx->execute([$prefixedEx]);
    $match = $stmtEx->fetch(PDO::FETCH_ASSOC);
    echo "DB exact match for '$ex' (as '$prefixedEx'): " . ($match ? "YES (ID: {$match['id']}, City: {$match['city_name']})" : 'NO') . "\n";
}

// Case-insensitive check for examples (with prefix)
foreach ($examples as $ex) {
    $prefixedEx = 'ion-' . $ex;
    $stmtCi = $pdo->prepare("SELECT id, slug, city_name, created_at FROM $tableName WHERE LOWER(slug) = LOWER(?) LIMIT 1");
    $stmtCi->execute([$prefixedEx]);
    $matchCi = $stmtCi->fetch(PDO::FETCH_ASSOC);
    echo "DB case-insensitive match for '$ex' (as '$prefixedEx'): " . ($matchCi ? "YES (ID: {$matchCi['id']}, City: {$matchCi['city_name']})" : 'NO') . "\n";
}
echo "\n";

// Function to get duplicates in batches (with "ion-" prefix)
function getDuplicates($pdo, $slugList, $tableName, $caseInsensitive = false) {
    $batchSize = 100;
    $allDuplicates = [];
    
    for ($i = 0; $i < count($slugList); $i += $batchSize) {
        $batch = array_slice($slugList, $i, $batchSize);
        $prefixedBatch = array_map(function($slug) { return 'ion-' . $slug; }, $batch);
        $placeholders = str_repeat('?,', count($prefixedBatch) - 1) . '?';
        
        $whereClause = $caseInsensitive 
            ? "LOWER(slug) IN (" . str_repeat('LOWER(?),', count($prefixedBatch) - 1) . "LOWER(?))" 
            : "slug IN ($placeholders)";
        
        $query = "
            SELECT id, slug, city_name, created_at 
            FROM $tableName 
            WHERE $whereClause
            ORDER BY " . ($caseInsensitive ? "LOWER(slug)" : "slug");
        
        $stmt = $pdo->prepare($query);
        $execBatch = $caseInsensitive ? array_map('strtolower', $prefixedBatch) : $prefixedBatch;
        $stmt->execute($execBatch);
        
        $batchDups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $allDuplicates = array_merge($allDuplicates, $batchDups);
    }
    
    return $allDuplicates;
}

// Exact duplicates (batched, with prefix)
echo "=== EXACT MATCH DUPLICATES (with 'ion-' prefix) ===\n";
$duplicates = getDuplicates($pdo, $csvSlugList, $tableName, false);
echo "Total CSV slugs: " . count($csvSlugList) . "\n";
echo "Existing duplicates in DB (exact slug with prefix): " . count($duplicates) . "\n\n";

if (!empty($duplicates)) {
    echo "Existing Records (will be updated on import):\n";
    echo str_pad('ID', 8) . str_pad('Slug', 25) . str_pad('City Name', 20) . "Created At\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($duplicates as $dup) {
        echo str_pad($dup['id'], 8) . str_pad($dup['slug'], 25) . str_pad($dup['city_name'], 20) . substr($dup['created_at'], 0, 19) . "\n"; // Truncate timestamp
    }
} else {
    echo "No exact duplicates found.\n";
}

// Case-insensitive duplicates (batched, with prefix)
echo "\n=== CASE-INSENSITIVE DUPLICATES (with 'ion-' prefix) ===\n";
$duplicatesCi = getDuplicates($pdo, $csvSlugList, $tableName, true);
echo "Existing duplicates in DB (case-insensitive with prefix): " . count($duplicatesCi) . "\n\n";
if (!empty($duplicatesCi)) {
    echo "Existing Records:\n";
    echo str_pad('ID', 8) . str_pad('Slug', 25) . str_pad('City Name', 20) . "Created At\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($duplicatesCi as $dup) {
        echo str_pad($dup['id'], 8) . str_pad($dup['slug'], 25) . str_pad($dup['city_name'], 20) . substr($dup['created_at'], 0, 19) . "\n";
    }
} else {
    echo "No case-insensitive duplicates found.\n";
}

?>