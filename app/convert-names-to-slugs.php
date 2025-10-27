<?php
/**
 * Convert Existing Category/Network Names to Slugs
 * 
 * This script updates existing IONLocalVideos records that have
 * descriptive names in ion_category and ion_network fields,
 * converting them to slugs from the IONNetworks table.
 */

// Include dependencies
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "  ION Category/Network Name → Slug Converter  \n";
echo "==============================================\n\n";

try {
    $pdo = $db->getPDO();
    
    // === STEP 1: Count videos needing conversion ===
    echo "📊 Analyzing existing videos...\n\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN ion_category IS NOT NULL AND ion_category NOT REGEXP '^[a-z0-9-]+$' THEN 1 END) as category_needs_conversion,
            COUNT(CASE WHEN ion_network IS NOT NULL AND ion_network NOT REGEXP '^[a-z0-9-]+$' THEN 1 END) as network_needs_conversion
        FROM IONLocalVideos
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total Videos: " . $stats['total'] . "\n";
    echo "Categories needing conversion: " . $stats['category_needs_conversion'] . "\n";
    echo "Networks needing conversion: " . $stats['network_needs_conversion'] . "\n\n";
    
    if ($stats['category_needs_conversion'] == 0 && $stats['network_needs_conversion'] == 0) {
        echo "✅ All videos already have slug format. No conversion needed!\n";
        exit(0);
    }
    
    // === STEP 2: Convert Categories ===
    echo "🔄 Converting categories to slugs...\n";
    
    $stmt = $pdo->query("
        SELECT DISTINCT ion_category 
        FROM IONLocalVideos 
        WHERE ion_category IS NOT NULL 
        AND ion_category NOT REGEXP '^[a-z0-9-]+$'
        ORDER BY ion_category
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $categoryConversions = [];
    $categoryFallbacks = [];
    
    foreach ($categories as $categoryName) {
        // Try multiple variations to find matching IONNetworks entry
        $variations = [
            $categoryName,                              // Exact match
            $categoryName . '™ Network',               // ION Local → ION Local™ Network
            $categoryName . ' Network',                 // ION Local → ION Local Network
            $categoryName . '™',                        // ION Local → ION Local™
        ];
        
        $result = null;
        foreach ($variations as $variation) {
            $stmt = $pdo->prepare("SELECT slug FROM IONNetworks WHERE network_name = ? LIMIT 1");
            $stmt->execute([$variation]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['slug'])) {
                break; // Found a match
            }
        }
        
        if ($result && !empty($result['slug'])) {
            $slug = strtolower($result['slug']);
            $categoryConversions[$categoryName] = $slug;
            echo "  ✅ '{$categoryName}' → '{$slug}'\n";
        } else {
            // Fallback: create slug from name
            $slug = strtolower(str_replace([' ', '™', '®', ' on ION'], ['-', '', '', ''], $categoryName));
            $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
            $categoryFallbacks[$categoryName] = $slug;
            echo "  ⚠️  '{$categoryName}' → '{$slug}' (fallback - not in IONNetworks)\n";
        }
    }
    
    echo "\n";
    
    // === STEP 3: Convert Networks ===
    echo "🔄 Converting networks to slugs...\n";
    
    $stmt = $pdo->query("
        SELECT DISTINCT ion_network 
        FROM IONLocalVideos 
        WHERE ion_network IS NOT NULL 
        AND ion_network NOT REGEXP '^[a-z0-9-]+$'
        ORDER BY ion_network
    ");
    $networks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $networkConversions = [];
    $networkFallbacks = [];
    
    foreach ($networks as $networkName) {
        // Try multiple variations to find matching IONNetworks entry
        $variations = [
            $networkName,                              // Exact match
            $networkName . '™ Network',               // ION Sports → ION Sports™ Network
            $networkName . ' Network',                 // ION Sports → ION Sports Network
            $networkName . '™',                        // ION Sports → ION Sports™
        ];
        
        $result = null;
        foreach ($variations as $variation) {
            $stmt = $pdo->prepare("SELECT slug FROM IONNetworks WHERE network_name = ? LIMIT 1");
            $stmt->execute([$variation]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['slug'])) {
                break; // Found a match
            }
        }
        
        if ($result && !empty($result['slug'])) {
            $slug = strtolower($result['slug']);
            $networkConversions[$networkName] = $slug;
            echo "  ✅ '{$networkName}' → '{$slug}'\n";
        } else {
            // Fallback: create slug from name
            $slug = strtolower(str_replace([' ', '™', '®', ' on ION'], ['-', '', '', ''], $networkName));
            $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
            $networkFallbacks[$networkName] = $slug;
            echo "  ⚠️  '{$networkName}' → '{$slug}' (fallback - not in IONNetworks)\n";
        }
    }
    
    echo "\n";
    
    // === STEP 4: Confirm before updating ===
    echo "📝 Summary:\n";
    echo "  Categories with IONNetworks match: " . count($categoryConversions) . "\n";
    echo "  Categories using fallback: " . count($categoryFallbacks) . "\n";
    echo "  Networks with IONNetworks match: " . count($networkConversions) . "\n";
    echo "  Networks using fallback: " . count($networkFallbacks) . "\n\n";
    
    echo "⚠️  This will update " . ($stats['category_needs_conversion'] + $stats['network_needs_conversion']) . " video field(s).\n";
    echo "Continue? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirm = trim(strtolower($line));
    fclose($handle);
    
    if ($confirm !== 'yes') {
        echo "\n❌ Cancelled by user.\n";
        exit(0);
    }
    
    echo "\n";
    
    // === STEP 5: Apply Updates ===
    echo "🔧 Applying updates...\n\n";
    
    $pdo->beginTransaction();
    
    $categoryUpdates = 0;
    $networkUpdates = 0;
    
    try {
        // Update categories
        $allCategoryMappings = array_merge($categoryConversions, $categoryFallbacks);
        foreach ($allCategoryMappings as $oldName => $newSlug) {
            $stmt = $pdo->prepare("
                UPDATE IONLocalVideos 
                SET ion_category = ? 
                WHERE ion_category = ?
            ");
            $stmt->execute([$newSlug, $oldName]);
            $rowCount = $stmt->rowCount();
            $categoryUpdates += $rowCount;
            echo "  ✅ Updated {$rowCount} video(s): '{$oldName}' → '{$newSlug}'\n";
        }
        
        // Update networks
        $allNetworkMappings = array_merge($networkConversions, $networkFallbacks);
        foreach ($allNetworkMappings as $oldName => $newSlug) {
            $stmt = $pdo->prepare("
                UPDATE IONLocalVideos 
                SET ion_network = ? 
                WHERE ion_network = ?
            ");
            $stmt->execute([$newSlug, $oldName]);
            $rowCount = $stmt->rowCount();
            $networkUpdates += $rowCount;
            echo "  ✅ Updated {$rowCount} video(s): '{$oldName}' → '{$newSlug}'\n";
        }
        
        $pdo->commit();
        
        echo "\n";
        echo "==============================================\n";
        echo "  ✅ CONVERSION COMPLETE\n";
        echo "==============================================\n";
        echo "Category fields updated: {$categoryUpdates}\n";
        echo "Network fields updated: {$networkUpdates}\n";
        echo "Total updates: " . ($categoryUpdates + $networkUpdates) . "\n\n";
        
        // === STEP 6: Verify Results ===
        echo "📊 Verifying results...\n";
        
        $stmt = $pdo->query("
            SELECT 
                COUNT(CASE WHEN ion_category IS NOT NULL AND ion_category NOT REGEXP '^[a-z0-9-]+$' THEN 1 END) as category_still_needs_conversion,
                COUNT(CASE WHEN ion_network IS NOT NULL AND ion_network NOT REGEXP '^[a-z0-9-]+$' THEN 1 END) as network_still_needs_conversion
            FROM IONLocalVideos
        ");
        $afterStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($afterStats['category_still_needs_conversion'] == 0 && $afterStats['network_still_needs_conversion'] == 0) {
            echo "✅ All videos now use slug format!\n\n";
        } else {
            echo "⚠️  Some videos still need conversion:\n";
            echo "   Categories: " . $afterStats['category_still_needs_conversion'] . "\n";
            echo "   Networks: " . $afterStats['network_still_needs_conversion'] . "\n\n";
        }
        
        // === STEP 7: Show Sample Results ===
        echo "📋 Sample converted videos:\n";
        $stmt = $pdo->query("
            SELECT id, title, ion_category, ion_network 
            FROM IONLocalVideos 
            WHERE ion_category IS NOT NULL OR ion_network IS NOT NULL
            ORDER BY id DESC 
            LIMIT 10
        ");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($samples as $video) {
            echo "  ID {$video['id']}: {$video['title']}\n";
            echo "    Category: " . ($video['ion_category'] ?? 'NULL') . "\n";
            echo "    Network: " . ($video['ion_network'] ?? 'NULL') . "\n\n";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
