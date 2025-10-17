<?php
declare(strict_types=1);
require_once __DIR__ . '/wp_bridge.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $split = isset($_GET['split']) && (int)$_GET['split'] === 1;

    if ($split) {
        // export all menus to separate files
        $outDir = dirname(WP_BRIDGE_CACHE_DIR) . '/wp_bridge/menus';
        if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }

        $menus = wpb_get_all_menus();
        $files = [];
        foreach ($menus as $m) {
            $name = $m['name'] ?? ('menu-' . ($m['id'] ?? 'unknown'));
            $id   = $m['id'] ?? 0;
            $slug = wpb_slugify($name) ?: ('menu-' . $id);
            $file = $outDir . '/' . $slug . '-' . $id . '.json';
            $payload = [
                'id'        => $id,
                'name'      => $name,
                'locations' => $m['locations'] ?? [],
                'items'     => $m['items'] ?? [],
                'exported'  => date('c'),
                'mode'      => WP_BRIDGE_MODE,
            ];
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $files[] = [
                'id'   => $id,
                'name' => $name,
                'file' => $file,
                'url'  => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file),
            ];
        }
        echo json_encode(['ok' => true, 'split' => true, 'count' => count($files), 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // default (non-split): return all menus in one JSON
    $locationsParam = isset($_GET['locations']) && $_GET['locations'] !== '' ? explode(',', $_GET['locations']) : [];
    $menus = empty($locationsParam) ? wpb_get_all_menus() : wpb_get_menus(array_map('trim', $locationsParam));

    echo json_encode(['mode' => WP_BRIDGE_MODE, 'menus' => $menus], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
