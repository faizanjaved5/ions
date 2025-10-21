<?php
/**
 * CLI Script for Creating Channel Bundles
 * 
 * Usage: php create-bundle-cli.php [options]
 * 
 * Options:
 * --name="Bundle Name"     Bundle name (required)
 * --slug="bundle-slug"     Bundle slug (required)
 * --file="channels.csv"    CSV file with channel slugs (required)
 * --price=299.99           Bundle price (optional)
 * --description="Desc"     Bundle description (optional)
 * --dry-run               Validate only, don't create (optional)
 * 
 * Example:
 * php create-bundle-cli.php --name="Major Cities" --slug="major-cities" --file="cities.csv" --price=299.99
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Parse command line arguments
$options = getopt('', [
    'name:',
    'slug:',
    'file:',
    'price:',
    'description:',
    'dry-run',
    'help'
]);

// Show help
if (isset($options['help']) || empty($options)) {
    echo "Channel Bundle Creator CLI\n";
    echo "==========================\n\n";
    echo "Usage: php create-bundle-cli.php [options]\n\n";
    echo "Options:\n";
    echo "  --name=\"Bundle Name\"     Bundle name (required)\n";
    echo "  --slug=\"bundle-slug\"     Bundle slug (required)\n";
    echo "  --file=\"channels.csv\"    CSV file with channel slugs (required)\n";
    echo "  --price=299.99           Bundle price (optional)\n";
    echo "  --description=\"Desc\"     Bundle description (optional)\n";
    echo "  --dry-run               Validate only, don't create (optional)\n";
    echo "  --help                  Show this help message\n\n";
    echo "Example:\n";
    echo "  php create-bundle-cli.php --name=\"Major Cities\" --slug=\"major-cities\" --file=\"cities.csv\" --price=299.99\n";
    exit(0);
}

// Validate required options
if (empty($options['name']) || empty($options['slug']) || empty($options['file'])) {
    echo "Error: --name, --slug, and --file are required options.\n";
    echo "Use --help for usage information.\n";
    exit(1);
}

$bundle_name = $options['name'];
$bundle_slug = $options['slug'];
$file_path = $options['file'];
$price = floatval($options['price'] ?? 0);
$description = $options['description'] ?? '';
$dry_run = isset($options['dry-run']);

// Validate file exists
if (!file_exists($file_path)) {
    echo "Error: File '$file_path' not found.\n";
    exit(1);
}

echo "Channel Bundle Creator\n";
echo "======================\n\n";
echo "Bundle Name: $bundle_name\n";
echo "Bundle Slug: $bundle_slug\n";
echo "File: $file_path\n";
echo "Price: $" . number_format($price, 2) . "\n";
echo "Description: " . ($description ?: 'None') . "\n";
echo "Mode: " . ($dry_run ? 'DRY RUN (validation only)' : 'CREATE') . "\n\n";

// Read and parse file
echo "Reading channel file...\n";
$file_content = file_get_contents($file_path);
if ($file_content === false) {
    echo "Error: Could not read file '$file_path'.\n";
    exit(1);
}

// Parse channels (support CSV, TXT, or JSON)
$channels = [];
$lines = explode("\n", $file_content);
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) { // Skip empty lines and comments
        continue;
    }
    
    // Handle CSV format (first column)
    if (strpos($line, ',') !== false) {
        $parts = explode(',', $line);
        $channels[] = trim($parts[0]);
    } else {
        $channels[] = $line;
    }
}

$channels = array_unique(array_filter($channels));
echo "Found " . count($channels) . " unique channels.\n\n";

if (empty($channels)) {
    echo "Error: No valid channels found in file.\n";
    exit(1);
}

// Validate channels against database
echo "Validating channels against database...\n";
try {
    $valid_channels = validate_channels($channels);
    $invalid_channels = array_diff($channels, $valid_channels);
    
    echo "Valid channels: " . count($valid_channels) . "\n";
    echo "Invalid channels: " . count($invalid_channels) . "\n\n";
    
    if (count($invalid_channels) > 0) {
        echo "Invalid channels:\n";
        foreach (array_slice($invalid_channels, 0, 10) as $channel) {
            echo "  - $channel\n";
        }
        if (count($invalid_channels) > 10) {
            echo "  ... and " . (count($invalid_channels) - 10) . " more\n";
        }
        echo "\n";
    }
    
    if (empty($valid_channels)) {
        echo "Error: No valid channels found in database.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error validating channels: " . $e->getMessage() . "\n";
    exit(1);
}

// Show sample of valid channels
echo "Sample valid channels:\n";
foreach (array_slice($valid_channels, 0, 5) as $channel) {
    echo "  - $channel\n";
}
if (count($valid_channels) > 5) {
    echo "  ... and " . (count($valid_channels) - 5) . " more\n";
}
echo "\n";

if ($dry_run) {
    echo "DRY RUN: Bundle would be created with " . count($valid_channels) . " channels.\n";
    echo "Use without --dry-run to actually create the bundle.\n";
    exit(0);
}

// Create bundle
echo "Creating bundle...\n";
try {
    $bundle_id = create_bundle($bundle_name, $bundle_slug, $description, $price, $valid_channels);
    echo "Bundle created successfully!\n";
    echo "Bundle ID: $bundle_id\n";
    echo "Total channels: " . count($valid_channels) . "\n";
    echo "Bundle slug: $bundle_slug\n";
    
} catch (Exception $e) {
    echo "Error creating bundle: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nBundle creation completed successfully!\n";

/**
 * Validate channels against database
 */
function validate_channels($channel_slugs) {
    global $pdo;
    
    if (empty($channel_slugs)) {
        return [];
    }
    
    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($channel_slugs) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT slug FROM IONLocalNetwork 
        WHERE slug IN ($placeholders)
    ");
    
    $stmt->execute($channel_slugs);
    $valid_slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $valid_slugs;
}

/**
 * Create bundle in database
 */
function create_bundle($bundle_name, $bundle_slug, $description, $price, $channels) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Check if bundle slug already exists
        $check_stmt = $pdo->prepare("SELECT id FROM IONLocalBundles WHERE bundle_slug = ?");
        $check_stmt->execute([$bundle_slug]);
        if ($check_stmt->fetch()) {
            throw new Exception("Bundle slug '$bundle_slug' already exists");
        }
        
        // Insert bundle
        $stmt = $pdo->prepare("
            INSERT INTO IONLocalBundles (
                bundle_name, bundle_slug, description, price, currency,
                channel_count, channels, categories, status, created_by
            ) VALUES (
                :bundle_name, :bundle_slug, :description, :price, :currency,
                :channel_count, :channels, :categories, 'active', :created_by
            )
        ");
        
        $stmt->execute([
            ':bundle_name' => $bundle_name,
            ':bundle_slug' => $bundle_slug,
            ':description' => $description,
            ':price' => $price,
            ':currency' => 'USD',
            ':channel_count' => count($channels),
            ':channels' => json_encode($channels),
            ':categories' => json_encode(['General']),
            ':created_by' => 'cli-script'
        ]);
        
        $bundle_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        return $bundle_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>
