<?php
declare(strict_types=1);
require_once __DIR__ . '/wp_bridge.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $outDir = dirname(WP_BRIDGE_CACHE_DIR) . '/wp_bridge/menus'; // ../cache/wp_bridge/menus
    if (!is_dir($outDir)) {
        if (!@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            throw new RuntimeException("Cannot create menus export dir: $outDir");
        }
    }

    $menus = wpb_get_all_menus();
    $written = [];

    foreach ($menus as $m) {
        $name = $m['name'] ?? ('menu-' . ($m['id'] ?? 'unknown'));
        $id   = $m['id'] ?? 0;
        $slug = wpb_slugify($name) ?: ('menu-' . $id);
        $file = $outDir . '/' . $slug . '-' . $id . '.json';

        // Make a compact payload
        $payload = [
            'id'        => $id,
            'name'      => $name,
            'locations' => $m['locations'] ?? [],
            'items'     => $m['items'] ?? [],
            'exported'  => date('c'),
            'mode'      => WP_BRIDGE_MODE,
        ];

        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $written[] = [
            'id'   => $id,
            'name' => $name,
            'file' => $file,
            'url'  => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file), // best-effort relative web path
        ];
    }

    echo json_encode([
        'ok'      => true,
        'count'   => count($written),
        'dir'     => $outDir,
        'files'   => $written,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
