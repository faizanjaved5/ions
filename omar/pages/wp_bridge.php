<?php
/**
 * WP Bridge Library (pages/wp_bridge.php)
 * - Uses your IONDatabase to resolve a PDO.
 * - MODE_GRAPHQL pulls rendered HTML (GraphQL/REST).
 * - MODE_DIRECTDB reads raw WP tables.
 */
declare(strict_types=1);

// ---------------------------
// Basic Config
// ---------------------------
define('WP_BRIDGE_MODE', 'MODE_GRAPHQL');       // 'MODE_GRAPHQL' or 'MODE_DIRECTDB'
define('WP_TABLE_PREFIX', 'wp_');               // adjust if custom prefix
define('WP_SITE_URL', 'https://iblog.bz');      // your WP site URL
define('WP_GRAPHQL_ENDPOINT', WP_SITE_URL . '/graphql');
define('WP_GRAPHQL_BEARER', '');                // optional bearer token
define('WP_REST_PAGES', WP_SITE_URL . '/wp-json/wp/v2/pages');

$WP_MENU_LOCATIONS = ['primary','footer'];

define('WP_BRIDGE_CACHE_DIR', __DIR__ . '/../cache/wp_bridge');
define('WP_BRIDGE_CACHE_TTL', 300); // 5 min

if (!is_dir(WP_BRIDGE_CACHE_DIR)) {
    @mkdir(WP_BRIDGE_CACHE_DIR, 0775, true);
}

// ---------------------------
// Load DB connection (definitive order)
// ---------------------------
// 1) Your app's DB wrapper (builds $db = new IONDatabase())
// 2) The raw config array it expects (database.php requires it itself, but harmless to include)
// 3) Optional WordPress wp-config.php (if you want DB_* constants as alternate path)
foreach ([
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../config/config.php',
] as $__p) {
    if (@is_file($__p)) { @require_once $__p; }
}

// ---------------------------
// Resolve PDO from IONDatabase / constants / env
// ---------------------------
function wpb_resolve_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    // a) Globals: $db (IONDatabase or PDO), $pdo, $conn
    foreach (['db','pdo','conn'] as $var) {
        if (isset($GLOBALS[$var])) {
            $candidate = $GLOBALS[$var];

            // IONDatabase instance
            if (is_object($candidate) && get_class($candidate) === 'IONDatabase') {
                if (method_exists($candidate, 'getPDO')) {
                    $inner = $candidate->getPDO();
                    if ($inner instanceof PDO) { $pdo = $inner; return $pdo; }
                }
                // Fallback via reflection if needed (kept for safety)
                try {
                    $ref = new ReflectionClass($candidate);
                    if ($ref->hasProperty('pdo')) {
                        $prop = $ref->getProperty('pdo');
                        $prop->setAccessible(true);
                        $inner = $prop->getValue($candidate);
                        if ($inner instanceof PDO) { $pdo = $inner; return $pdo; }
                    }
                } catch (Throwable $ignore) {}
            }

            // Raw PDO
            if ($candidate instanceof PDO) { $pdo = $candidate; return $pdo; }
        }
    }

    // b) WordPress-style constants
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    }

    // c) ENV fallback
    $eHost = getenv('DB_HOST'); $eName = getenv('DB_NAME'); $eUser = getenv('DB_USER'); $ePass = getenv('DB_PASSWORD');
    if ($eHost && $eName && $eUser) {
        $dsn = 'mysql:host=' . $eHost . ';dbname=' . $eName . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $eUser, $ePass ?: '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    }

    throw new RuntimeException("No PDO connection available. Ensure /config/database.php creates \$db = new IONDatabase().");
}



// Fetch ALL menus via GraphQL (fallback to DB if GraphQL empty)
function wpb_get_all_menus_graphql(): array {
    $cacheKey = 'menus_graphql_all';
    if ($cached = wpb_cache_get($cacheKey)) return $cached;

    $query = <<<'GQL'
query AllMenus {
  menus(first: 100) {
    nodes {
      databaseId
      name
      locations
      menuItems(first: 1000) {
        nodes {
          databaseId
          parentDatabaseId
          label
          path
          uri
          connectedNode { node {
            ... on Page { id uri slug title }
            ... on Post { id uri slug title }
            ... on Category { id uri slug name }
          } }
          cssClasses
          target
          url
        }
      }
    }
  }
}
GQL;

    $data = wpb_graphql_query($query, []) ?: [];
    $menus = [];
    if (!empty($data['menus']['nodes'])) {
        foreach ($data['menus']['nodes'] as $menu) {
            $menus[] = [
                'id'        => $menu['databaseId'],
                'name'      => $menu['name'],
                'locations' => $menu['locations'],
                'items'     => wpb_build_menu_tree_from_nodes($menu['menuItems']['nodes'] ?? []),
            ];
        }
    }
    wpb_cache_set($cacheKey, $menus);
    return $menus;
}

// Mode-aware "get ALL menus"
function wpb_get_all_menus(): array {
    if (WP_BRIDGE_MODE === 'MODE_GRAPHQL') {
        $menus = wpb_get_all_menus_graphql();
        if (!empty($menus)) return $menus;
        // fallback to DB for all
        $db = wpb_resolve_pdo();
        return wpb_get_menus_directdb($db, []); // returns all nav_menu terms already
    }
    $db = wpb_resolve_pdo();
    return wpb_get_menus_directdb($db, []);     // returns all nav_menu terms
}


// ---------------------------
// Helpers
// ---------------------------
function wpb_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 12): array {
    $ch = curl_init($url);
    $defaultHeaders = ['Content-Type: application/json'];
    if (!empty(WP_GRAPHQL_BEARER)) {
        $defaultHeaders[] = 'Authorization: Bearer ' . WP_GRAPHQL_BEARER;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'status' => $code ?: 0, 'error' => $err, 'body' => null];
    return ['ok' => ($code >= 200 && $code < 300), 'status' => $code, 'error' => null, 'body' => $res];
}

function wpb_http_get_json(string $url, int $timeout = 12): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'status' => $code ?: 0, 'error' => $err, 'body' => null];
    return ['ok' => ($code >= 200 && $code < 300), 'status' => $code, 'error' => null, 'body' => $res];
}

function wpb_cache_path(string $key): string {
    return WP_BRIDGE_CACHE_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.json';
}
function wpb_cache_get(string $key) {
    $path = wpb_cache_path($key);
    if (!file_exists($path)) return null;
    if (filemtime($path) + WP_BRIDGE_CACHE_TTL < time()) return null;
    return json_decode(file_get_contents($path), true);
}
function wpb_cache_set(string $key, $value): void {
    file_put_contents(wpb_cache_path($key), json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
function wpb_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\-]+/i', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}

// ---------------------------
// GraphQL
// ---------------------------
function wpb_graphql_query(string $query, array $vars = []): ?array {
    $resp = wpb_http_post_json(WP_GRAPHQL_ENDPOINT, ['query' => $query, 'variables' => $vars]);
    if (!$resp['ok'] || !$resp['body']) return null;
    $data = json_decode($resp['body'], true);
    if (!is_array($data) || isset($data['errors'])) return null;
    return $data['data'] ?? null;
}

function wpb_build_menu_tree_from_nodes(array $nodes): array {
    $byId = []; $tree = [];
    foreach ($nodes as $n) { $n['children'] = []; $byId[$n['databaseId']] = $n; }
    foreach ($byId as $id => &$n) {
        $pid = $n['parentDatabaseId'] ?? 0;
        if ($pid && isset($byId[$pid])) $byId[$pid]['children'][] = &$n;
        else $tree[] = &$n;
    }
    unset($n); return $tree;
}

function wpb_get_menus_graphql(array $locations): array {
    $cacheKey = 'menus_graphql_' . md5(json_encode($locations));
    if ($cached = wpb_cache_get($cacheKey)) return $cached;

    $query = <<<'GQL'
query MenusByLocations($locations:[MenuLocationEnum]) {
  menus(where:{location:$locations}) {
    nodes {
      databaseId
      name
      locations
      menuItems(first:1000) {
        nodes {
          databaseId
          parentDatabaseId
          label
          path
          uri
          connectedNode { node { ... on Page { id uri slug title } ... on Post { id uri slug title } ... on Category { id uri slug name } } }
          cssClasses
          target
          url
        }
      }
    }
  }
}
GQL;
    $data = wpb_graphql_query($query, ['locations' => $locations]) ?: [];
    $menus = [];
    if (!empty($data['menus']['nodes'])) {
        foreach ($data['menus']['nodes'] as $menu) {
            $menus[] = [
                'id'        => $menu['databaseId'],
                'name'      => $menu['name'],
                'locations' => $menu['locations'],
                'items'     => wpb_build_menu_tree_from_nodes($menu['menuItems']['nodes'] ?? []),
            ];
        }
    }
    wpb_cache_set($cacheKey, $menus);
    return $menus;
}

function wpb_get_page_rendered_graphql(string $slug): ?array {
    $cacheKey = 'page_html_graphql_' . $slug;
    if ($cached = wpb_cache_get($cacheKey)) return $cached;

    $query = <<<'GQL'
query PageBySlug($slug:ID!) {
  page(id:$slug, idType:URI) {
    databaseId slug uri title content date modified isFrontPage
  }
}
GQL;
    $data = wpb_graphql_query($query, ['slug' => $slug]);
    if (!empty($data['page'])) {
        $page = [
            'id'       => $data['page']['databaseId'],
            'slug'     => $data['page']['slug'],
            'uri'      => $data['page']['uri'],
            'title'    => $data['page']['title'],
            'html'     => $data['page']['content'],
            'date'     => $data['page']['date'],
            'modified' => $data['page']['modified'],
        ];
        wpb_cache_set($cacheKey, $page);
        return $page;
    }

    // REST fallback
    $rest = wpb_http_get_json(WP_REST_PAGES.'?_fields=title,content,slug,modified,date&per_page=1&slug='.urlencode($slug));
    if ($rest['ok'] && $rest['body']) {
        $arr = json_decode($rest['body'], true);
        if (!empty($arr[0])) {
            $p = $arr[0];
            $page = [
                'id'       => null,
                'slug'     => $p['slug'],
                'uri'      => '/'.$p['slug'].'/',
                'title'    => is_array($p['title']) ? ($p['title']['rendered'] ?? '') : $p['title'],
                'html'     => is_array($p['content']) ? ($p['content']['rendered'] ?? '') : $p['content'],
                'date'     => $p['date'] ?? null,
                'modified' => $p['modified'] ?? null,
            ];
            wpb_cache_set($cacheKey, $page);
            return $page;
        }
    }
    return null;
}

// ---------------------------
// Direct DB (raw)
// ---------------------------
function wpb_get_menus_directdb(PDO $db, array $locations): array {
    $cacheKey = 'menus_db_' . md5(json_encode($locations));
    if ($cached = wpb_cache_get($cacheKey)) return $cached;

    $prefix = WP_TABLE_PREFIX;
    $menus = [];

    $sqlMenus = "SELECT t.term_id, t.name
                 FROM {$prefix}terms t
                 JOIN {$prefix}term_taxonomy tt ON tt.term_id = t.term_id
                 WHERE tt.taxonomy = 'nav_menu'";
    $rows = $db->query($sqlMenus)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $termId = (int)$row['term_id'];
        $sqlItems = "SELECT p.ID, p.post_title, p.post_name, p.post_type,
                            pm1.meta_value AS _menu_item_menu_item_parent,
                            pm2.meta_value AS _menu_item_object_id,
                            pm3.meta_value AS _menu_item_object,
                            pm4.meta_value AS _menu_item_url,
                            pm5.meta_value AS _menu_item_target,
                            pm6.meta_value AS _menu_item_classes
                     FROM {$prefix}posts p
                     JOIN {$prefix}term_relationships tr ON tr.object_id = p.ID
                     JOIN {$prefix}postmeta pm1 ON pm1.post_id = p.ID AND pm1.meta_key = '_menu_item_menu_item_parent'
                     JOIN {$prefix}postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_menu_item_object_id'
                     JOIN {$prefix}postmeta pm3 ON pm3.post_id = p.ID AND pm3.meta_key = '_menu_item_object'
                     LEFT JOIN {$prefix}postmeta pm4 ON pm4.post_id = p.ID AND pm4.meta_key = '_menu_item_url'
                     LEFT JOIN {$prefix}postmeta pm5 ON pm5.post_id = p.ID AND pm5.meta_key = '_menu_item_target'
                     LEFT JOIN {$prefix}postmeta pm6 ON pm6.post_id = p.ID AND pm6.meta_key = '_menu_item_classes'
                     WHERE tr.term_taxonomy_id IN (
                           SELECT term_taxonomy_id FROM {$prefix}term_taxonomy WHERE term_id = :tid AND taxonomy='nav_menu'
                     )
                     AND p.post_type = 'nav_menu_item'
                     AND p.post_status = 'publish'
                     ORDER BY p.menu_order ASC, p.ID ASC";
        $stmt2 = $db->prepare($sqlItems);
        $stmt2->execute([':tid' => $termId]);
        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $byId = [];
        foreach ($items as $it) {
            $classes = trim((string)($it['_menu_item_classes'] ?? ''));
            $classes = $classes ? explode(' ', preg_replace('/\s+/', ' ', $classes)) : [];
            $byId[$it['ID']] = [
                'id'        => (int)$it['ID'],
                'label'     => $it['post_title'],
                'object_id' => (int)$it['_menu_item_object_id'],
                'object'    => $it['_menu_item_object'],
                'url'       => $it['_menu_item_url'] ?: null,
                'target'    => $it['_menu_item_target'] ?: null,
                'classes'   => $classes,
                'parent'    => (int)$it['_menu_item_menu_item_parent'],
                'children'  => [],
            ];
        }
        $tree = [];
        foreach ($byId as $id => &$node) {
            if ($node['parent'] && isset($byId[$node['parent']])) $byId[$node['parent']]['children'][] = &$node;
            else $tree[] = &$node;
        }
        unset($node);

        $menus[] = ['id' => $termId, 'name' => $row['name'], 'items' => $tree];
    }

    wpb_cache_set($cacheKey, $menus);
    return $menus;
}

function wpb_get_page_directdb(PDO $db, string $slug): ?array {
    $cacheKey = 'page_db_' . $slug;
    if ($cached = wpb_cache_get($cacheKey)) return $cached;

    $prefix = WP_TABLE_PREFIX;
    $sql = "SELECT ID, post_title, post_content, post_date, post_modified
            FROM {$prefix}posts
            WHERE post_type='page' AND post_status='publish' AND post_name=:slug
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) return null;

    $result = [
        'id'       => (int)$page['ID'],
        'slug'     => $slug,
        'uri'      => '/' . $slug . '/',
        'title'    => $page['post_title'],
        'html'     => $page['post_content'],
        'date'     => $page['post_date'],
        'modified' => $page['post_modified'],
    ];
    wpb_cache_set($cacheKey, $result);
    return $result;
}

// ---------------------------
// Public API
// ---------------------------
function wpb_get_menus(array $locations = []): array {
    global $WP_MENU_LOCATIONS;
    $locs = $locations ?: $WP_MENU_LOCATIONS;

    if (WP_BRIDGE_MODE === 'MODE_GRAPHQL') {
        $menus = wpb_get_menus_graphql($locs);
        if (!empty($menus)) return $menus;
        // fallback
        $db = wpb_resolve_pdo();
        return wpb_get_menus_directdb($db, $locs);
    }
    $db = wpb_resolve_pdo();
    return wpb_get_menus_directdb($db, $locs);
}

function wpb_get_page_by_slug(string $slug): ?array {
    if (WP_BRIDGE_MODE === 'MODE_GRAPHQL') {
        $page = wpb_get_page_rendered_graphql($slug);
        if ($page) return $page;
        $db = wpb_resolve_pdo();
        return wpb_get_page_directdb($db, $slug);
    }
    $db = wpb_resolve_pdo();
    return wpb_get_page_directdb($db, $slug);
}

function wpb_render_html_document(string $title, string $bodyHtml, array $options = []): void {
    $charset = $options['charset'] ?? 'utf-8';
    $baseHref = $options['base_href'] ?? '/';
    $styles = $options['styles'] ?? '';
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    echo "  <meta charset=\"" . htmlspecialchars($charset) . "\">\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "  <base href=\"" . htmlspecialchars($baseHref) . "\">\n";
    echo "  <title>" . htmlspecialchars($title) . "</title>\n";
    if ($styles) echo "  <style>{$styles}</style>\n";
    echo "</head>\n<body>\n";
    echo $bodyHtml;
    echo "\n</body>\n</html>";
}
