<?php
/**
 * Database Structure Sync Tool
 * Compares ION production vs IBlog sandbox and generates safe migrations
 */

echo "<h1>Database Structure Sync Analysis</h1>";
echo "<p>Comparing ION (production) vs IBlog (sandbox)</p>";

$ionFile = __DIR__ . '/../app/IONDatabaseStructure.csv';
$iblogFile = __DIR__ . '/../app/IBlogDatabaseStructure.csv';

if (!file_exists($ionFile) || !file_exists($iblogFile)) {
    die("Error: CSV files not found");
}

// Parse CSV files
function parseStructure($file) {
    $structure = [];
    $handle = fopen($file, 'r');
    $headers = null;
    
    while (($row = fgetcsv($handle)) !== false) {
        if (!$headers) {
            $headers = $row;
            continue;
        }
        
        if (count($row) < 12) continue;
        
        $table = $row[0];
        $ordinal = $row[1];
        $column = $row[2];
        $type = $row[3];
        $nullable = $row[5];
        $default = $row[6];
        $attributes = $row[7];
        
        if (!isset($structure[$table])) {
            $structure[$table] = [];
        }
        
        $structure[$table][$column] = [
            'ordinal' => $ordinal,
            'type' => $type,
            'nullable' => $nullable,
            'default' => $default,
            'attributes' => $attributes
        ];
    }
    
    fclose($handle);
    return $structure;
}

$ionStructure = parseStructure($ionFile);
$iblogStructure = parseStructure($iblogFile);

// Compare structures
$differences = [];

// Tables in IBlog but not in ION
foreach ($iblogStructure as $table => $columns) {
    if (!isset($ionStructure[$table])) {
        $differences['missing_tables_in_ion'][] = $table;
    }
}

// Tables in ION but not in IBlog
foreach ($ionStructure as $table => $columns) {
    if (!isset($iblogStructure[$table])) {
        $differences['missing_tables_in_iblog'][] = $table;
    }
}

// Column differences
foreach ($iblogStructure as $table => $iblogColumns) {
    if (!isset($ionStructure[$table])) continue;
    
    $ionColumns = $ionStructure[$table];
    
    // Columns in IBlog but not in ION
    foreach ($iblogColumns as $column => $iblogData) {
        if (!isset($ionColumns[$column])) {
            $differences['missing_columns_in_ion'][] = [
                'table' => $table,
                'column' => $column,
                'type' => $iblogData['type'],
                'nullable' => $iblogData['nullable'],
                'default' => $iblogData['default'],
                'attributes' => $iblogData['attributes']
            ];
        } else {
            // Column exists in both - check for type differences
            $ionData = $ionColumns[$column];
            
            if ($iblogData['type'] !== $ionData['type'] || 
                $iblogData['nullable'] !== $ionData['nullable'] ||
                $iblogData['default'] !== $ionData['default'] ||
                $iblogData['attributes'] !== $ionData['attributes']) {
                
                // Ignore cosmetic differences like 'nan' defaults
                $isCosmetic = (
                    strpos($iblogData['default'] ?? '', 'nan') !== false ||
                    strpos($ionData['default'] ?? '', 'nan') !== false
                );
                
                if (!$isCosmetic) {
                    $differences['column_differences'][] = [
                        'table' => $table,
                        'column' => $column,
                        'iblog' => $iblogData,
                        'ion' => $ionData
                    ];
                }
            }
        }
    }
    
    // Columns in ION but not in IBlog
    foreach ($ionColumns as $column => $ionData) {
        if (!isset($iblogColumns[$column])) {
            $differences['missing_columns_in_iblog'][] = [
                'table' => $table,
                'column' => $column,
                'type' => $ionData['type'],
                'nullable' => $ionData['nullable'],
                'default' => $ionData['default'],
                'attributes' => $ionData['attributes']
            ];
        }
    }
}

// Display results
echo "<h2>Analysis Results</h2>";

// Missing tables
if (!empty($differences['missing_tables_in_ion'])) {
    echo "<h3 style='color: orange;'>⚠️ Tables in IBlog but NOT in ION:</h3>";
    echo "<ul>";
    foreach ($differences['missing_tables_in_ion'] as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
}

if (!empty($differences['missing_tables_in_iblog'])) {
    echo "<h3 style='color: blue;'>ℹ️ Tables in ION but NOT in IBlog:</h3>";
    echo "<ul>";
    foreach ($differences['missing_tables_in_iblog'] as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
}

// Missing columns in ION (need to add to production)
if (!empty($differences['missing_columns_in_ion'])) {
    echo "<h3 style='color: red;'>🔴 CRITICAL: Columns in IBlog but NOT in ION (ADD TO PRODUCTION):</h3>";
    echo "<pre style='background: #ffe6e6; padding: 15px; border-left: 4px solid red;'>";
    echo "-- Run these on ION PRODUCTION:\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($differences['missing_columns_in_ion'] as $col) {
        $nullable = $col['nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col['default'] ? "DEFAULT '{$col['default']}'" : '';
        $attributes = $col['attributes'];
        
        echo "ALTER TABLE `{$col['table']}` \n";
        echo "  ADD COLUMN `{$col['column']}` {$col['type']} $nullable $default $attributes;\n\n";
    }
    
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    echo "</pre>";
}

// Missing columns in IBlog (need to add to sandbox)
if (!empty($differences['missing_columns_in_iblog'])) {
    echo "<h3 style='color: orange;'>⚠️ Columns in ION but NOT in IBlog (ADD TO SANDBOX):</h3>";
    echo "<pre style='background: #fff3cd; padding: 15px; border-left: 4px solid orange;'>";
    echo "-- Run these on IBLOG SANDBOX:\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($differences['missing_columns_in_iblog'] as $col) {
        $nullable = $col['nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col['default'] ? "DEFAULT '{$col['default']}'" : '';
        $attributes = $col['attributes'];
        
        echo "ALTER TABLE `{$col['table']}` \n";
        echo "  ADD COLUMN `{$col['column']}` {$col['type']} $nullable $default $attributes;\n\n";
    }
    
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    echo "</pre>";
}

// Column type differences
if (!empty($differences['column_differences'])) {
    echo "<h3 style='color: blue;'>ℹ️ Column Definition Differences (Review Carefully):</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table</th><th>Column</th><th>IBlog</th><th>ION</th></tr>";
    
    foreach ($differences['column_differences'] as $diff) {
        echo "<tr>";
        echo "<td><strong>{$diff['table']}</strong></td>";
        echo "<td><strong>{$diff['column']}</strong></td>";
        echo "<td style='background: #e8f4f8;'>";
        echo "Type: {$diff['iblog']['type']}<br>";
        echo "Nullable: {$diff['iblog']['nullable']}<br>";
        echo "Default: {$diff['iblog']['default']}<br>";
        echo "Attributes: {$diff['iblog']['attributes']}";
        echo "</td>";
        echo "<td style='background: #fff4e6;'>";
        echo "Type: {$diff['ion']['type']}<br>";
        echo "Nullable: {$diff['ion']['nullable']}<br>";
        echo "Default: {$diff['ion']['default']}<br>";
        echo "Attributes: {$diff['ion']['attributes']}";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Summary
echo "<hr>";
echo "<h2>Summary</h2>";
echo "<ul>";
echo "<li><strong>Missing in ION:</strong> " . count($differences['missing_columns_in_ion'] ?? []) . " columns</li>";
echo "<li><strong>Missing in IBlog:</strong> " . count($differences['missing_columns_in_iblog'] ?? []) . " columns</li>";
echo "<li><strong>Definition differences:</strong> " . count($differences['column_differences'] ?? []) . " columns</li>";
echo "</ul>";

if (empty($differences['missing_columns_in_ion']) && 
    empty($differences['missing_columns_in_iblog']) && 
    empty($differences['column_differences'])) {
    echo "<h2 style='color: green;'>✅ Databases are fully synchronized!</h2>";
}
?>

