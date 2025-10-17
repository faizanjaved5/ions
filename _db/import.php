<?php
// Database connection settings - update these with your actual credentials
$host     = 'localhost';
$dbname   = 'u185424179_WtONJ';
$username = 'u185424179_S4e4w';  
$password = '04JE8wHMrl';

// CSV file path
$csvFile = 'addcities.csv';

// Open the CSV file
if (($handle = fopen($csvFile, "r")) === FALSE) {
    die("Error opening the CSV file.");
}

// Initialize counters
$totalRecords = 0;
$importedRecords = 0; // Includes inserts and updates
$failedRecords = 0;
$inserts = 0;
$updates = 0;

// PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Prepare the INSERT statement with ON DUPLICATE KEY UPDATE
// Updated table name to IONLocalNext; assuming UNIQUE KEY on slug
// Includes new fields if added (description, etc.); adjust if not present
$stmt = $pdo->prepare("
    INSERT INTO IONLocalNext 
    (status, type, lookup, slug, page_URL, city_name, state_name, country_code, country_name, population, channel_name, title, seo_title, description, seo_description, pricing_type, pricing_tier_id, created_at, updated_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'tier', ?, NOW(), NOW()) 
    ON DUPLICATE KEY UPDATE 
    status = 'Preview',
    type = VALUES(type),
    lookup = VALUES(lookup),
    page_URL = VALUES(page_URL),
    city_name = VALUES(city_name),
    state_name = VALUES(state_name),
    country_code = VALUES(country_code),
    country_name = VALUES(country_name),
    population = VALUES(population),
    channel_name = VALUES(channel_name),
    title = IF(title IS NULL OR title = '', VALUES(title), title),
    seo_title = IF(seo_title IS NULL OR seo_title = '', VALUES(seo_title), seo_title),
    description = IF(description IS NULL OR description = '', VALUES(description), description),
    seo_description = IF(seo_description IS NULL OR seo_description = '', VALUES(seo_description), seo_description),
    pricing_type = 'tier',
    pricing_tier_id = VALUES(pricing_tier_id),
    updated_at = NOW()
");

// Skip header row
fgetcsv($handle); // Assuming first row is header

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $totalRecords++;
    
    // Basic validation: ensure at least 7 fields before processing (base structure, no type column)
    if (count($data) < 7) {
        $failedRecords++;
        continue;
    }
    
    // Handle split population due to thousand separators (commas in numbers)
    // CSV structure: 0=place_name, 1=ion_name, 2=state_name, 3=country_code, 4=country_name, 5=population (may split), 6=slug_name
    if (count($data) > 7) {
        // Pop was split: join numeric parts from index 5 to second-last
        $pop_parts = array_slice($data, 5, count($data) - 6);
        $pop_str = implode('', array_map('trim', $pop_parts));
        $slugName_raw = trim(end($data));
        
        // Reconstruct data array to 7 elements
        $data = array_slice($data, 0, 5);
        $data[] = $pop_str;
        $data[] = $slugName_raw;
    }
    
    // Now validate reconstructed data has exactly 7 fields
    if (count($data) != 7) {
        $failedRecords++;
        continue;
    }
    
    try {
        // Map CSV fields (trim whitespace) - order: place_name[0], ion_name[1], state_name[2], country_code[3], country_name[4], population[5], slug_name[6]
        $placeName = trim($data[0]);
        $ionName = trim($data[1]);
        $stateName = trim($data[2]);
        $countryCode = trim($data[3]);
        $countryName = trim($data[4]);
        $population_str = trim($data[5]);
        $slugName = trim($data[6]);
        
        // Derive type: 'Country' if state empty, else 'Town'
        $type = empty($stateName) ? 'Country' : 'Town';
        
        // Cast population (remove any remaining commas)
        $population = (int) str_replace(',', '', $population_str);
        
        // Computed fields
        $lookup = str_replace('-', '', $slugName); // Remove dashes for lookup
        $slug = 'ion-' . $slugName; // Prefix to match DB
        $pageURL = 'https://ions.com/' . $slug; // Use prefixed slug
        $channelName = $ionName;
        $title = 'Welcome to ' . $ionName;
        $seoTitle = $ionName . ' Local Community Network';
        $description = 'Got your ' . $ionName . '? ';
        $seo_description = 'Join the ' . $ionName . ' community network. Connect with...';
        
        // Compute pricing_tier_id based on population
        $pricing_tier_id = 1;
        if ($population > 10000000) {
            $pricing_tier_id = 5;
        } elseif ($population > 1000000) {
            $pricing_tier_id = 4;
        } elseif ($population > 500000) {
            $pricing_tier_id = 3;
        } elseif ($population > 100000) {
            $pricing_tier_id = 2;
        }
        
        // Skip if required fields empty
        if (empty($placeName) || empty($slugName)) {
            $failedRecords++;
            continue;
        }
        
        // Execute the statement
        $stmt->execute([
            'Preview', $type, $lookup, $slug, $pageURL, $placeName, $stateName, 
            $countryCode, $countryName, $population, $channelName, $title, $seoTitle,
            $description, $seo_description, $pricing_tier_id
        ]);
        
        $rowCount = $stmt->rowCount();
        if ($rowCount === 1) {
            $inserts++;
            $importedRecords++;
        } elseif ($rowCount === 2) {
            $updates++;
            $importedRecords++;
        } else {
            $failedRecords++;
        }
    } catch (PDOException $e) {
        error_log("Row $totalRecords error: " . $e->getMessage()); // Enable logging for debugging
        $failedRecords++;
    }
}

fclose($handle);

// Output stats
echo "Import completed!\n";
echo "Total records processed: $totalRecords\n";
echo "Records imported (inserted): $inserts\n";
echo "Records updated: $updates\n";
echo "Total successful: $importedRecords\n";
echo "Failed records: $failedRecords\n";
?>