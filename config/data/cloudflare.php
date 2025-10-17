<?php
/**
 * Cloudflare Domain Manager (DB-driven)
 * - Reads Cloudflare API token from ../config/config.php
 * - Uses ../config/database.php for $pdo (PDO) connection
 * - Pulls domains from IONdomains table and tracks per-step state
 * - Batch UI and lightweight "API mode" for single-domain operations
 */

set_time_limit(0);
ini_set('memory_limit', '2048M');
ignore_user_abort(true);

// ======================================================================
// Load config + DB
// ======================================================================
$CONFIG_FILE = realpath(__DIR__ . '/../config/config.php');
$DB_FILE     = realpath(__DIR__ . '/../config/database.php');

if ($CONFIG_FILE && file_exists($CONFIG_FILE)) require_once $CONFIG_FILE;
if ($DB_FILE && file_exists($DB_FILE))         require_once $DB_FILE; // should expose $pdo (PDO)

function requirePDO() {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        die('PDO $pdo not found. Ensure ../config/database.php initializes $pdo (PDO).');
    }
}
requirePDO();

// Resolve Cloudflare API token from loaded config or env
$apiToken = $apiToken ?? null;
if (!$apiToken) {
    if (isset($config['cloudflare']['api_token'])) {
        $apiToken = $config['cloudflare']['api_token'];
    } elseif (isset($config['cloudflare_api']['token'])) {
        $apiToken = $config['cloudflare_api']['token'];
    } elseif (getenv('CLOUDFLARE_API_TOKEN')) {
        $apiToken = getenv('CLOUDFLARE_API_TOKEN');
    }
}
if (!$apiToken) {
    die('Cloudflare API token not found. Add it in ../config/config.php as $config["cloudflare"]["api_token"].');
}

// ======================================================================
// Basic runtime/config
// ======================================================================
$statusFile = 'IONstatus.txt';
$logFile    = 'IONcloudflare.log';

$aRecordIP  = '82.29.158.191'; // Hostinger IP
$mxRecords  = [
    ['value' => 'mx1.hostinger.com', 'priority' => 5],
    ['value' => 'mx2.hostinger.com', 'priority' => 10],
];

$batchSize  = 250; // how many rows to process per run

// ======================================================================
// Output streaming helpers
// ======================================================================
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);
header('X-Accel-Buffering: no');
header('Content-Type: text/html; charset=utf-8');

function printLog($msg, $type = 'info') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine   = "[$timestamp] $msg\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND);
    echo "<div class='log $type'>" . htmlspecialchars($msg) . "</div>";
    echo str_repeat(" ", 2048);
    echo "<script>console.log('log update');</script>";
    @ob_flush(); @flush();
}

// ======================================================================
/** Cloudflare API request (with basic rate-limit surface headers) */
function cloudflareRequest($url, $payload = null, $method = 'POST', $retries = 5) {
    global $apiToken;
    $attempt = 0;
    $lastCode = 0;

    while ($attempt++ < $retries) {
        $headers = [];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $apiToken",
                "Content-Type: application/json"
            ],
            CURLOPT_HEADERFUNCTION => function($curl, $header_line) use (&$headers) {
                $headers[] = trim($header_line);
                return strlen($header_line);
            },
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($res, true);

        $lastCode = $code;
        $ratelimit_remaining = null;
        $ratelimit_reset     = null;
        foreach ($headers as $h) {
            if (stripos($h, 'x-ratelimit-remaining:') === 0) {
                $ratelimit_remaining = (int) trim(substr($h, strlen('x-ratelimit-remaining:')));
            } elseif (stripos($h, 'x-ratelimit-reset:') === 0) {
                $ratelimit_reset = (int) trim(substr($h, strlen('x-ratelimit-reset:')));
            }
        }
        if ($code !== 429) {
            return [$code, $json, $ratelimit_remaining, $ratelimit_reset];
        }
        // backoff on 429
        sleep(2);
    }
    return [$lastCode, null, null, null];
}

// ======================================================================
// DB helpers (PDO)
// ======================================================================
function db() { global $pdo; return $pdo; }

function fetchDomainsForRun($limit = 250) {
    $sql = "SELECT id, domain, COALESCE(redirect_url,'') AS redirect_url, COALESCE(status,'') AS status,
                   zone_id, name_servers, created_on,
                   speed_opt_applied, dns_a_set, mx_set, worker_routes_set, redirect_rule_set,
                   last_error
            FROM IONdomains
            ORDER BY id ASC
            LIMIT :lim";
    $st = db()->prepare($sql);
    $st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function getDomainRow($domain) {
    $st = db()->prepare("SELECT * FROM IONdomains WHERE domain = :d LIMIT 1");
    $st->execute([':d'=>$domain]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

function upsertDomainBasic($domain, $status = null, $redirectUrl = null) {
    $st = db()->prepare("
        INSERT INTO IONdomains (domain, status, redirect_url)
        VALUES (:d, :s, :r)
        ON DUPLICATE KEY UPDATE
            status = COALESCE(VALUES(status), status),
            redirect_url = COALESCE(VALUES(redirect_url), redirect_url)
    ");
    $st->execute([':d'=>$domain, ':s'=>$status, ':r'=>$redirectUrl]);
}

function markDomain($id, array $fields) {
    if (!$id) return;
    $sets = [];
    $params = [':id'=>$id];
    foreach ($fields as $k=>$v) {
        $sets[] = "`$k` = :$k";
        $params[":$k"] = $v;
    }
    if (!$sets) return;
    $sql = "UPDATE IONdomains SET ".implode(',', $sets)." WHERE id = :id LIMIT 1";
    db()->prepare($sql)->execute($params);
}

function markError($id, $message) {
    if (!$id) return;
    markDomain($id, ['last_error'=>$message]);
}

// ======================================================================
// Speed Optimization helpers
// ======================================================================
function cfSet($zoneID, $setting, $value) {
    $endpoint = "https://api.cloudflare.com/client/v4/zones/$zoneID/settings/$setting";
    $payload  = ['value' => $value];
    return cloudflareRequest($endpoint, $payload, 'PATCH');
}

function applySpeedOptimization($zoneID, $profile = 'aggressive') {
    $settings = [
        ['brotli',              'on',  'Brotli'],
        ['early_hints',         'on',  'Early Hints'],
        ['http3',               'on',  'HTTP/3'],
        ['0rtt',                'on',  '0-RTT'],
        ['always_use_https',    'on',  'Always Use HTTPS'],
    ];
    // Minify uses nested shape
    $allOk = true;

    // Minify
    $endpoint = "https://api.cloudflare.com/client/v4/zones/$zoneID/settings/minify";
    list($c, $r) = cloudflareRequest($endpoint, ['value'=>['css'=>'on','html'=>'on','js'=>'on']], 'PATCH');
    $ok = ($c===200) && (($r['success']??false)===true);
    if ($ok) printLog("✅ Speed: Auto Minify (HTML/CSS/JS) enabled", 'success');
    else {
        $msg = $r['errors'][0]['message'] ?? json_encode($r);
        printLog("⚠️ Speed: Minify failed (HTTP $c) - $msg", 'warning'); $allOk = false;
    }

    foreach ($settings as [$key, $value, $label]) {
        list($c, $r) = cfSet($zoneID, $key, $value);
        $ok = ($c===200) && (($r['success']??false)===true);
        if ($ok) printLog("✅ Speed: $label enabled", 'success');
        else {
            $msg = $r['errors'][0]['message'] ?? json_encode($r);
            printLog("⚠️ Speed: $label failed (HTTP $c) - $msg", 'warning');
            $allOk = false;
        }
    }
    return $allOk;
}

// ======================================================================
// Export helpers (kept from your original UI)
// ======================================================================
function exportAllCloudflareZonesToCSV() {
    $exportFile = 'IONDomainsExport.csv';
    $fp = fopen($exportFile, 'w');
    fputcsv($fp, ['Domain', 'Status', 'Name Servers', 'Created On']);
    $page = 1;
    do {
        list($code, $res) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones?per_page=50&page=$page", null, 'GET');
        if ($code === 200 && isset($res['result'])) {
            foreach ($res['result'] as $zone) {
                $domain  = $zone['name']         ?? '';
                $status  = $zone['status']       ?? '';
                $ns      = implode('; ', $zone['name_servers'] ?? []);
                $created = $zone['created_on']   ?? '';
                fputcsv($fp, [$domain, $status, $ns, $created]);
            }
            $totalPages = $res['result_info']['total_pages'] ?? 1;
            $page++;
            sleep(1);
        } else {
            $msg = isset($res['errors'][0]['message']) ? $res['errors'][0]['message'] : json_encode($res);
            printLog("Export failed: $msg", 'error');
            break;
        }
    } while ($page <= ($totalPages ?? 1));
    fclose($fp);
}

function exportCloudflareDomainsDetails() {
    $exportFile = 'cloudflare_domains_details.csv';
    $fp = fopen($exportFile, 'w');
    fputcsv($fp, ['Domain', 'Name Servers', 'Status', '1 Day Unique', '7 Days Unique', '30 Days Unique', 'SSL Enabled', 'DNS Records', 'Redirect URL']);
    $page = 1;
    do {
        list($code, $res) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones?per_page=50&page=$page", null, 'GET');
        if ($code === 200 && isset($res['result'])) {
            foreach ($res['result'] as $zone) {
                $domain = $zone['name'] ?? '';
                $name_servers = implode(', ', $zone['name_servers'] ?? []);
                $status = $zone['status'] ?? '';
                $zone_id = $zone['id'] ?? '';

                $now = gmdate('Y-m-d\TH:i:s\Z');
                $one  = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 day'));
                $seven= gmdate('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
                $thir = gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days'));

                list($a1,)  = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/analytics/dashboard?since=$one&until=$now", null, 'GET');
                $vis1  = ($a1['result']['totals']['uniques']['all'] ?? 'Error');

                list($a7,)  = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/analytics/dashboard?since=$seven&until=$now", null, 'GET');
                $vis7  = ($a7['result']['totals']['uniques']['all'] ?? 'Error');

                list($a30,) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/analytics/dashboard?since=$thir&until=$now", null, 'GET');
                $vis30 = ($a30['result']['totals']['uniques']['all'] ?? 'Error');

                list($sslC, $sslR) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/ssl/universal/settings", null, 'GET');
                $ssl_status = ($sslC===200 && ($sslR['result']['enabled']??false)) ? 'Enabled' : 'Disabled';

                list($dnsC, $dnsR) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/dns_records", null, 'GET');
                $dns_status = ($dnsC===200 && count($dnsR['result']??[])>0) ? 'Active' : 'No Records';

                // ruleset fetch (best-effort)
                $redirect_url = 'None';
                list($rsC,$rsR) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/phases/http_request_dynamic_redirect/entrypoint", null, 'GET');
                if ($rsC===200 && isset($rsR['result']['id'])) {
                    $rid = $rsR['result']['id'];
                    list($rC,$rR) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$rid", null, 'GET');
                    if ($rC===200 && isset($rR['result']['rules'][0]['action_parameters']['from_value']['target_url']['expression'])) {
                        $redirect_url = $rR['result']['rules'][0]['action_parameters']['from_value']['target_url']['expression'];
                    }
                }

                fputcsv($fp, [$domain, $name_servers, $status, $vis1, $vis7, $vis30, $ssl_status, $dns_status, $redirect_url]);
            }
            $totalPages = $res['result_info']['total_pages'] ?? 1;
            $page++;
            sleep(1);
        } else {
            $msg = isset($res['errors'][0]['message']) ? $res['errors'][0]['message'] : json_encode($res);
            printLog("Detailed export failed: $msg", 'error');
            break;
        }
    } while ($page <= ($totalPages ?? 1));
    fclose($fp);
}

// ======================================================================
// Simple “API mode” (single-domain operations for other pages)
// ======================================================================
if (isset($_GET['op']) && isset($_GET['domain'])) {
    $op     = strtolower(trim($_GET['op']));
    $domain = strtolower(trim($_GET['domain']));

    // Find or add zone
    list($zCode, $zRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones?name=$domain", null, 'GET');
    $zoneID = $zRes['result'][0]['id'] ?? null;

    if (!$zoneID && $op === 'add_zone') {
        list($zAddCode, $zAddRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones", ['name'=>$domain, 'jump_start'=>true], 'POST');
        $zoneID = $zAddRes['result']['id'] ?? null;
        if (!$zoneID) {
            http_response_code(400);
            $reason = $zAddRes['errors'][0]['message'] ?? json_encode($zAddRes);
            exit("Failed to add zone: $reason");
        }
    }
    if (!$zoneID) {
        http_response_code(404);
        exit("Zone not found for $domain");
    }

    $row = getDomainRow($domain);
    if (!$row) { upsertDomainBasic($domain); $row = getDomainRow($domain); }

    switch ($op) {
        case 'speed':
        case 'speed_opt':
            $ok = applySpeedOptimization($zoneID, 'aggressive');
            if ($ok) markDomain($row['id'], ['speed_opt_applied'=>1]);
            exit($ok ? "OK" : "PARTIAL");

        case 'update_a':
            $ip = $_GET['ip'] ?? '192.0.2.1';
            list($dnsCode, $dnsRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=A&name=$domain", null, 'GET');
            $payload = ['type'=>'A','name'=>$domain,'content'=>$ip,'ttl'=>1,'proxied'=>true];
            if (isset($dnsRes['result'][0])) {
                $rid = $dnsRes['result'][0]['id'];
                list($c, $r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid", $payload, 'PUT');
            } else {
                list($c, $r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records", $payload, 'POST');
            }
            if (($c===200) && ($r['success']??false)) { markDomain($row['id'], ['dns_a_set'=>1]); exit("OK"); }
            exit("ERR");

        case 'update_mx':
            global $mxRecords;
            $allOK = true;
            foreach ($mxRecords as $mx) {
                list($dnsCode, $dnsRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=MX&name=$domain&content=" . urlencode($mx['value']), null, 'GET');
                $payload = ['type'=>'MX','name'=>$domain,'content'=>$mx['value'],'priority'=>$mx['priority'],'ttl'=>3600];
                if (isset($dnsRes['result'][0])) {
                    $rid = $dnsRes['result'][0]['id'];
                    list($c, $r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid", $payload, 'PUT');
                } else {
                    list($c, $r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records", $payload, 'POST');
                }
                $allOK = $allOK && ($c===200) && ($r['success']??false);
            }
            if ($allOK) markDomain($row['id'], ['mx_set'=>1]);
            exit($allOK ? "OK" : "PARTIAL");

        case 'cname_apex_to_ions':
            // apex CNAME → ions.com (proxied)
            $payload = ['type'=>'CNAME','name'=>$domain,'content'=>'ions.com','ttl'=>3600,'proxied'=>true];
            list($dnsCode,$dnsRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=CNAME&name=$domain", null, 'GET');
            if (isset($dnsRes['result'][0])) {
                $rid = $dnsRes['result'][0]['id'];
                list($c,$r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid", $payload, 'PUT');
            } else {
                list($c,$r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records", $payload, 'POST');
            }
            exit(($c===200 && ($r['success']??false)) ? "OK" : "ERR");
        
        case 'cname_www_to_apex':
            // www CNAME → apex (proxied)
            $www = "www.$domain";
            $payload = ['type'=>'CNAME','name'=>$www,'content'=>$domain,'ttl'=>3600,'proxied'=>true];
            list($dnsCode,$dnsRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=CNAME&name=$www", null, 'GET');
            if (isset($dnsRes['result'][0])) {
                $rid = $dnsRes['result'][0]['id'];
                list($c,$r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid", $payload, 'PUT');
            } else {
                list($c,$r) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records", $payload, 'POST');
            }
            exit(($c===200 && ($r['success']??false)) ? "OK" : "ERR");
        
        case 'worker_map':
            // add/update worker routes for this domain
            $script = $_GET['script'] ?? 'ion-domains';
            $p1 = "$domain/*";
            $p2 = "*.$domain/*";
            list($routesCode,$routesRes)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes", null, 'GET');
            $r1=$r2=null; if ($routesCode===200 && isset($routesRes['result'])) {
                foreach ($routesRes['result'] as $route) {
                    if ($route['pattern']===$p1) $r1=$route['id'];
                    if ($route['pattern']===$p2) $r2=$route['id'];
                }
            }
            $pl1=['pattern'=>$p1,'script'=>$script];
            $pl2=['pattern'=>$p2,'script'=>$script];
            $ok1=$ok2=false;
            if ($r1) { list($c1,$rr1)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes/$r1",$pl1,'PUT'); $ok1=($c1===200 && ($rr1['success']??false)); }
            else     { list($c1,$rr1)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes",$pl1,'POST'); $ok1=($c1===200 && ($rr1['success']??false)); }
            if ($r2) { list($c2,$rr2)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes/$r2",$pl2,'PUT'); $ok2=($c2===200 && ($rr2['success']??false)); }
            else     { list($c2,$rr2)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes",$pl2,'POST'); $ok2=($c2===200 && ($rr2['success']??false)); }
            exit(($ok1&&$ok2) ? "OK" : "PARTIAL");
        
        case 'redirect_rule':
            // create/update dynamic redirect (apex & www) to ?to=https://target.tld
            $to = $_GET['to'] ?? '';
            if ($to === '') { http_response_code(400); exit('Missing ?to='); }
            list($rsCode,$rsRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/rulesets/phases/http_request_dynamic_redirect/entrypoint", null, 'GET');
            $rulesetID = $rsRes['result']['id'] ?? null;
            if (!$rulesetID) {
                $createPayload = ['name'=>'Redirect Rules','kind'=>'zone','phase'=>'http_request_dynamic_redirect'];
                list($createCode,$createRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/rulesets",$createPayload,'POST');
                $rulesetID = $createRes['result']['id'] ?? null;
                if (!$rulesetID) exit('ERR');
            }
            $expr = "concat(\"$to\", http.request.uri.path)";
            $updatePayload = [
                'rules' => [[
                    'expression' => "(http.host eq \"$domain\" or http.host eq \"www.$domain\")",
                    'description'=> "Redirect for $domain (apex/www)",
                    'action'     => 'redirect',
                    'action_parameters' => [
                        'from_value' => [
                            'status_code'=> 301,
                            'target_url' => ['expression'=>$expr],
                            'preserve_query_string' => true
                        ]
                    ],
                    'enabled' => true
                ]]
            ];
            list($uCode,$uRes) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/rulesets/$rulesetID",$updatePayload,'PUT');
            exit(($uCode===200 && ($uRes['success']??false)) ? "OK" : "ERR");            

        default:
            http_response_code(400);
            exit("Unknown op");
    }
}

// ======================================================================
// Export triggers (kept)
// ======================================================================
if (isset($_GET['export']) && $_GET['export']==='1') {
    exportAllCloudflareZonesToCSV();
    echo "<!DOCTYPE html><html><head><title>Cloudflare Export</title><style>
        body { font-family: system-ui, Arial; background:#f8f8f8; padding:20px; }
        .success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:15px;}
    </style></head><body>
    <div class='success'>✅ Export complete. <a href='IONDomainsExport.csv' download>Download CSV</a></div></body></html>";
    exit;
}
if (isset($_GET['export_details']) && $_GET['export_details']==='1') {
    exportCloudflareDomainsDetails();
    echo "<!DOCTYPE html><html><head><title>Cloudflare Detailed Export</title><style>
        body { font-family: system-ui, Arial; background:#f8f8f8; padding:20px; }
        .success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:15px;}
    </style></head><body>
    <div class='success'>✅ Detailed export complete. <a href='cloudflare_domains_details.csv' download>Download CSV</a></div></body></html>";
    exit;
}

// ======================================================================
// Start/Stop controls
// ======================================================================
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['action']) && in_array($_POST['action'], ['start','stop'])) {
        file_put_contents($statusFile, $_POST['action']==='start' ? 'running' : 'stopped');
        file_put_contents('cloudflare_selected_actions.json', json_encode($_POST['actions'] ?? []));
        exit;
    }
}
if (isset($_GET['ping'])) exit('pong');

$isRunning = file_exists($statusFile) && trim(file_get_contents($statusFile)) === 'running';

// ======================================================================
// Idle UI
// ======================================================================
if (!$isRunning) {
    $prevActions = json_decode(@file_get_contents('cloudflare_selected_actions.json'), true) ?? [];
    echo "<!DOCTYPE html><html><head><title>Cloudflare Manager</title><style>
        body { font-family: system-ui, Arial; background:#f8f8f8; padding:20px; }
        .log { margin:5px 0; padding:10px; border-radius:5px; }
        .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .info    { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
        .warning { background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
        button { padding:10px 20px; font-size:16px; border:none; border-radius:5px; color:#fff; cursor:pointer; }
        a.btn { text-decoration:none; }
    </style></head><body>";

    echo "<p><a class='btn' href='?export=1'>
        <button style='background:#0073e6'>📤 Download Cloudflare Domains CSV</button></a></p>";
    echo "<p><a class='btn' href='?export_details=1'>
        <button style='background:#0073e6'>📤 Download Detailed Cloudflare Domains CSV</button></a></p>";

    echo "<h2>📂 Cloudflare Domain Manager</h2>";
    echo "<form id='mainForm' method='POST'>
        <label><input type='checkbox' name='actions[]' value='add_zones' ".(in_array('add_zones',$prevActions)?'checked':'')."> Add Zones to Cloudflare</label><br>
        <label><input type='checkbox' name='actions[]' value='update_a' ".(in_array('update_a',$prevActions)?'checked':'')."> Add / Update A Record (IP: {$aRecordIP})</label><br>
        <label><input type='checkbox' name='actions[]' value='update_mx' ".(in_array('update_mx',$prevActions)?'checked':'')."> Add / Update MX Records</label>
        <ul style='margin-left:20px;'>";
    foreach ($mxRecords as $mx) echo "<li><code>{$mx['value']} (Priority {$mx['priority']})</code></li>";
    echo "</ul>
        <label><input type='checkbox' name='actions[]' value='setup_redirect' ".(in_array('setup_redirect',$prevActions)?'checked':'')."> Setup Redirect based on mapping</label><br>
        <label><input type='checkbox' name='actions[]' value='automate_redirect' ".(in_array('automate_redirect',$prevActions)?'checked':'')."> Automate Domain Redirection</label><br>
        <label><input type='checkbox' name='actions[]' value='speed_opt' ".(in_array('speed_opt',$prevActions)?'checked':'')."> Apply Speed / Performance Optimizations</label><br>
        <br><button id='toggleBtn' style='background:green'>🚀 Proceed</button>
        <input type='hidden' name='action' value='start'>
    </form>
    <script>
        function keepAlive(){ setInterval(()=>fetch('?ping='+Date.now()), 30000); }
        document.getElementById('toggleBtn').addEventListener('click', function(e){
            e.preventDefault();
            const form = document.getElementById('mainForm');
            fetch('', { method:'POST', body: new FormData(form) })
                .then(()=>location.reload());
        });
        keepAlive();
    </script></body></html>";
    exit;
}

// ======================================================================
// Processing view
// ======================================================================
$selectedActions = json_decode(@file_get_contents('cloudflare_selected_actions.json'), true) ?? [];

echo "<!DOCTYPE html><html><head><title>Cloudflare Execution</title><style>
    body { font-family: system-ui, Arial; background:#f8f8f8; padding:20px; position:relative; }
    .log { padding:10px; margin:5px 0; border-radius:5px; }
    .success{ background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .error  { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .info   { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
    .warning{ background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
    #stopButton{ background:#c0392b; color:#fff; border:none; padding:10px 16px; border-radius:5px; cursor:pointer;}
    #toggleAll{ position:absolute; top:20px; right:20px; background:#007bff; color:#fff; border:none; padding:10px 15px; cursor:pointer; border-radius:5px; }
    details { margin-bottom: 16px; background:#fff; border:1px solid #ddd; border-radius:8px; padding:6px 10px; }
    summary { font-weight: 600; cursor: pointer; }
</style></head><body>
<button id='stopButton' onclick='stopProcess()'>🛑 Stop</button>
<button id='toggleAll' onclick='toggleAllDetails()'>Expand/Collapse All</button>
<script>
    function stopProcess() {
        if (!confirm('Stop the current process?')) return;
        fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=stop' })
            .then(()=>{ document.getElementById('stopButton').disabled = true; document.getElementById('stopButton').textContent = 'Stopping...'; });
    }
    function toggleAllDetails(){
        var details = document.querySelectorAll('details');
        var allOpen = Array.from(details).every(d=>d.open);
        details.forEach(d=>d.open = !allOpen);
    }
</script>";

$domains = fetchDomainsForRun($batchSize);

$successCount = 0;
$skippedCount = 0;
$processedThisRun = 0;

$alreadyProcessed = [];
foreach ($domains as $i => &$entry) {
    if (trim(@file_get_contents($statusFile)) !== 'running') break;
    $domain = strtolower(trim($entry['domain'] ?? ''));
    if ($domain === '') { echo "</details>"; continue; }

    if (in_array($domain, $alreadyProcessed, true)) {
        printLog("[$i] ⚠️ Duplicate domain detected, skipping: $domain", 'info');
        continue;
    }
    $alreadyProcessed[] = $domain;
    if (trim(@file_get_contents($statusFile)) !== 'running') break;

    $domain = strtolower(trim($entry['domain']));
    echo "<details><summary>[$i] Domain: <a href='https://$domain' target='_blank'>$domain</a></summary>";

    // Get or Add Zone
    list($zCode, $zRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones?name=$domain", null, 'GET');
    if ($remaining !== null && $remaining < 10) {
        $sleepTime = max(0, $reset - time() + 1);
        printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
    }
    $zoneID = $zRes['result'][0]['id'] ?? null;

    if (!$zoneID && in_array('add_zones', $selectedActions)) {
        // Check pending
        list($pCode, $pRes, $pRem, $pReset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones?status=pending&per_page=1", null, 'GET');
        $pendingCount = $pRes['result_info']['total_count'] ?? 0;
        while ($pendingCount >= 40) {
            printLog("[$i] Too many pending zones ($pendingCount). Sleeping 60s...", 'warning');
            sleep(60);
            list($pCode, $pRes, $pRem, $pReset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones?status=pending&per_page=1", null, 'GET');
            $pendingCount = $pRes['result_info']['total_count'] ?? 0;
        }

        list($zAddCode, $zAddRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones", ['name'=>$domain, 'jump_start'=>true], 'POST');
        if ($remaining !== null && $remaining < 10) {
            $sleepTime = max(0, $reset - time() + 1);
            printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
        }
        if (($zAddRes['success'] ?? false) && isset($zAddRes['result']['id'])) {
            $zoneID = $zAddRes['result']['id'];
            printLog("[$i] ✅ Zone added: $domain", 'success');

            // small wait
            $tries = 0;
            while ($tries++ < 6) {
                list($chkCode, $chkRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID", null, 'GET');
                if ($remaining !== null && $remaining < 10) {
                    $sleepTime = max(0, $reset - time() + 1);
                    printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
                }
                $chkStatus = $chkRes['result']['status'] ?? '';
                if ($chkStatus === 'active' || $chkStatus === 'pending') break;
                printLog("[$i] ⏳ Waiting for zone availability: $domain", 'info');
                sleep(5);
            }
        } elseif ($zCode == 429) {
            $errorCode = $zRes['errors'][0]['code'] ?? 0;
            $errorMsg  = $zRes['errors'][0]['message'] ?? 'Unknown';
            printLog("[$i] ⚠️ Rate limited adding zone: $domain (HTTP 429, Code $errorCode): $errorMsg", 'warning');
            if ($errorCode == 1117 || stripos($errorMsg, 'rate limit for adding or deleting zones') !== false) {
                printLog("[$i] Specific zone-add rate limit hit. Sleeping 3600s…", 'warning');
                sleep(3600);
            } else {
                sleep(10);
            }
            $i--; // retry this domain
            echo "</details>";
            continue;
        } else {
                    $reason = isset($zAddRes['errors']) ? json_encode($zAddRes['errors']) : 'Unknown reason';
                    printLog("[$i] ❌ Failed to add zone: $domain (HTTP $zAddCode): $reason", 'error');
                    echo "</details>";
                    continue;
                }
    } elseif ($zoneID) {
        printLog("[$i] ℹ️ Zone exists: $domain", 'info');
    } else {
        printLog("[$i] ❌ Zone not found and not added: $domain", 'error');
        echo "</details>";
        continue;
    }

    // Fetch name servers + persist
    $nameServers = '';
    list($nsCode, $nsRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID", null, 'GET');
    if ($remaining !== null && $remaining < 10) {
        $sleepTime = max(0, $reset - time() + 1);
        printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
    }
    if ($nsCode === 200 && isset($nsRes['result']['name_servers'])) {
        $nameServers = implode(', ', $nsRes['result']['name_servers']);
        printLog("[$i] ℹ️ Name servers: $nameServers", 'info');
        $row = getDomainRow($domain);
        if ($row) {
            $save = [
                'zone_id'      => $zoneID,
                'name_servers' => $nameServers
            ];
            if (isset($nsRes['result']['created_on'])) {
                $save['created_on'] = date('Y-m-d H:i:s', strtotime($nsRes['result']['created_on']));
            }
            markDomain($row['id'], $save);
        } else {
            upsertDomainBasic($domain);
        }
    } else {
        $reason = isset($nsRes['errors']) ? json_encode($nsRes['errors']) : 'Unknown reason';
        printLog("[$i] ⚠️ Failed to fetch name servers (HTTP $nsCode): $reason", 'warning');
    }

    // STEP: Speed / Performance
    if ($zoneID && in_array('speed_opt', $selectedActions)) {
        printLog("[$i] ⚙️ Applying speed/performance settings...", 'info');
        $ok = applySpeedOptimization($zoneID, 'aggressive');
        if ($ok) { $row = getDomainRow($domain); if ($row) markDomain($row['id'], ['speed_opt_applied'=>1]); }
    }

    // STEP: A record
    if ($zoneID && in_array('update_a', $selectedActions)) {
        list($dnsCode, $dnsRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=A&name=$domain", null, 'GET');
        if ($remaining !== null && $remaining < 10) {
            $sleepTime = max(0, $reset - time() + 1);
            printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
        }
        $payload = ['type'=>'A','name'=>$domain,'content'=>$aRecordIP,'ttl'=>3600,'proxied'=>false];
        if (isset($dnsRes['result'][0])) {
            $recordID = $dnsRes['result'][0]['id'];
            list($aCode, $aRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$recordID", $payload, 'PUT');
            $action = 'updated';
        } else {
            list($aCode, $aRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records", $payload, 'POST');
            $action = 'added';
        }
        if ($remaining !== null && $remaining < 10) {
            $sleepTime = max(0, $reset - time() + 1);
            printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
        }
        if ($aCode === 200 && ($aRes['success'] ?? false)) {
            printLog("[$i] ✅ A record $action: $domain → $aRecordIP", 'success');
            $row = getDomainRow($domain); if ($row) markDomain($row['id'], ['dns_a_set'=>1]);
        } else {
            $reason = $aRes['errors'][0]['message'] ?? json_encode($aRes);
            printLog("[$i] ⚠️ A record failed - $reason", 'warning');
            $row = getDomainRow($domain); if ($row) markError($row['id'], "A record failed: $reason");
        }
    }

    // STEP: MX
    if ($zoneID && in_array('update_mx', $selectedActions)) {
        $mxAllOk = true;
        foreach ($mxRecords as $mx) {
            list($dnsCode, $dnsRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=MX&name=$domain&content=" . urlencode($mx['value']), null, 'GET');
            if ($remaining !== null && $remaining < 10) {
                $sleepTime = max(0, $reset - time() + 1);
                printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
            }
            $payload = ['type'=>'MX','name'=>$domain,'content'=>$mx['value'],'priority'=>$mx['priority'],'ttl'=>3600];
            if (isset($dnsRes['result'][0])) {
                $recordID = $dnsRes['result'][0]['id'];
                list($mxCode, $mxRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$recordID", $payload, 'PUT');
                $action = 'updated';
            } else {
                list($mxCode, $mxRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records", $payload, 'POST');
                $action = 'added';
            }
            if ($remaining !== null && $remaining < 10) {
                $sleepTime = max(0, $reset - time() + 1);
                printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime);
            }
            if ($mxCode === 200 && ($mxRes['success'] ?? false)) {
                printLog("[$i] ✅ MX $action: {$mx['value']} (prio {$mx['priority']})", 'success');
            } else {
                $reason = $mxRes['errors'][0]['message'] ?? json_encode($mxRes);
                printLog("[$i] ❌ MX failed: {$mx['value']} - $reason", 'error');
                $mxAllOk = false;
            }
        }
        if ($mxAllOk) { $row = getDomainRow($domain); if ($row) markDomain($row['id'], ['mx_set'=>1]); }
    }

    // STEP: Redirect + SSL + CNAMEs (unchanged logic, with DB marking)
    if ($zoneID && in_array('setup_redirect', $selectedActions) && !empty($entry['redirect_url'])) {
        // Universal SSL
        list($sslCode, $sslRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/ssl/universal/settings", ['enabled'=>true], 'PATCH');
        if ($remaining !== null && $remaining < 10) { $sleepTime = max(0,$reset-time()+1); printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime); }
        if ($sslCode===200 && ($sslRes['success']??false)) printLog("[$i] ✅ Universal SSL enabled", 'success');
        else printLog("[$i] ❌ Enable Universal SSL failed", 'error');

        // SSL mode flexible
        list($modeCode,$modeRes,$remaining,$reset)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/settings/ssl", ['value'=>'flexible'], 'PATCH');
        if ($remaining !== null && $remaining < 10) { $sleepTime = max(0,$reset-time()+1); printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime); }
        if ($modeCode===200 && ($modeRes['success']??false)) printLog("[$i] ✅ SSL mode set to flexible", 'success');

        // Min TLS 1.2
        list($minTlsCode,$minTlsRes,$remaining,$reset)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/settings/min_tls_version", ['value'=>'1.2'],'PATCH');
        if ($remaining !== null && $remaining < 10) { $sleepTime = max(0,$reset-time()+1); printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime); }
        if ($minTlsCode===200 && ($minTlsRes['success']??false)) printLog("[$i] ✅ Min TLS set to 1.2", 'success');

        // Always HTTPS
        list($httpsCode,$httpsRes,$remaining,$reset)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/settings/always_use_https", ['value'=>'on'], 'PATCH');
        if ($remaining !== null && $remaining < 10) { $sleepTime = max(0,$reset-time()+1); printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime); }
        if ($httpsCode===200 && ($httpsRes['success']??false)) printLog("[$i] ✅ Always Use HTTPS enabled", 'success');

        // Delete conflicting apex records
        list($confCode, $confRes, $remaining, $reset) = cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?name=$domain&type=A,AAAA,CNAME", null, 'GET');
        if ($remaining !== null && $remaining < 10) { $sleepTime = max(0,$reset-time()+1); printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime); }
        if ($confCode===200 && isset($confRes['result'])) {
            foreach ($confRes['result'] as $record) {
                $rid = $record['id'];
                list($delCode,$delRes,$remaining,$reset)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid", null,'DELETE');
                if ($remaining !== null && $remaining < 10) { $sleepTime = max(0,$reset-time()+1); printLog("Rate limit low. Sleeping for $sleepTime seconds.", 'warning'); sleep($sleepTime); }
                if ($delCode===200 && ($delRes['success']??false)) printLog("[$i] ✅ Deleted conflicting {$record['type']} apex", 'success');
            }
        }

        // CNAME apex → ions.com (proxied)
        list($dnsCode,$dnsRes,$remaining,$reset)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=CNAME&name=$domain", null, 'GET');
        $payload = ['type'=>'CNAME','name'=>$domain,'content'=>'ions.com','ttl'=>3600,'proxied'=>true];
        if (isset($dnsRes['result'][0])) {
            $rid=$dnsRes['result'][0]['id'];
            list($c,$r,$re,$rs)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid",$payload,'PUT'); $act='updated';
        } else {
            list($c,$r,$re,$rs)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records",$payload,'POST'); $act='added';
        }
        if (($c===200)&&($r['success']??false)) printLog("[$i] ✅ CNAME apex $act → ions.com", 'success');

        // CNAME www → apex (proxied)
        $wwwName = "www.$domain";
        list($wCode,$wRes,$re,$rs)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=CNAME&name=$wwwName", null, 'GET');
        $wPayload = ['type'=>'CNAME','name'=>$wwwName,'content'=>$domain,'ttl'=>3600,'proxied'=>true];
        if (isset($wRes['result'][0])) {
            $wid=$wRes['result'][0]['id'];
            list($wc,$wr)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$wid",$wPayload,'PUT'); $wact='updated';
        } else {
            list($wc,$wr)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records",$wPayload,'POST'); $wact='added';
        }
        if (($wc===200)&&($wr['success']??false)) printLog("[$i] ✅ CNAME www $wact → apex", 'success');

        // Redirect rules
        list($rsCode,$rsRes,$re,$rs)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/rulesets/phases/http_request_dynamic_redirect/entrypoint", null, 'GET');
        $rulesetID = $rsRes['result']['id'] ?? null;
        if (!$rulesetID) {
            $createPayload = ['name'=>'Redirect Rules','kind'=>'zone','phase'=>'http_request_dynamic_redirect'];
            list($createCode,$createRes)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/rulesets",$createPayload,'POST');
            if (($createCode===200)&&($createRes['success']??false)) {
                $rulesetID = $createRes['result']['id'];
                printLog("[$i] ✅ Created redirect ruleset", 'success');
            } else {
                printLog("[$i] ❌ Could not create redirect ruleset", 'error');
            }
        }
        if ($rulesetID) {
            $redirectExpression = "concat(\"{$entry['redirect_url']}\", http.request.uri.path)";
            $updatePayload = [
                'rules' => [[
                    'expression' => "(http.host eq \"$domain\" or http.host eq \"www.$domain\")",
                    'description'=> "Redirect for $domain (apex/www)",
                    'action'     => 'redirect',
                    'action_parameters' => [
                        'from_value' => [
                            'status_code'=> 301,
                            'target_url' => ['expression'=>$redirectExpression],
                            'preserve_query_string' => true
                        ]
                    ],
                    'enabled' => true
                ]]
            ];
            list($uCode,$uRes)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/rulesets/$rulesetID",$updatePayload,'PUT');
            if (($uCode===200)&&($uRes['success']??false)) {
                printLog("[$i] ✅ Redirect rule set to {$entry['redirect_url']}", 'success');
                $row = getDomainRow($domain); if ($row) markDomain($row['id'], ['redirect_rule_set'=>1]);
            } else {
                printLog("[$i] ❌ Redirect rule update failed", 'error');
            }
        }
    }

    // STEP: Automate domain redirection (as in your original)
    if ($zoneID && in_array('automate_redirect', $selectedActions)) {
        // SSL Strict
        list($sslCode,$sslRes,$re,$rs)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/settings/ssl", ['value'=>'strict'],'PATCH');
        if (($sslCode===200)&&($sslRes['success']??false)) printLog("[$i] ✅ SSL/TLS set to Full (Strict)", 'success');

        // A apex proxied to 192.0.2.1
        list($dnsCode,$dnsRes,$re,$rs)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=A&name=$domain", null, 'GET');
        $aPayload = ['type'=>'A','name'=>$domain,'content'=>'192.0.2.1','ttl'=>1,'proxied'=>true];
        if (isset($dnsRes['result'][0])) { $rid=$dnsRes['result'][0]['id']; list($ac,$ar)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$rid",$aPayload,'PUT'); $aAct='updated'; }
        else { list($ac,$ar)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records",$aPayload,'POST'); $aAct='added'; }
        if (($ac===200)&&($ar['success']??false)) { printLog("[$i] ✅ A $aAct: $domain → 192.0.2.1 (proxied)", 'success'); $row=getDomainRow($domain); if($row) markDomain($row['id'], ['dns_a_set'=>1]); }

        // CNAME www -> apex (proxied)
        $wwwName = "www.$domain";
        list($wDnsCode,$wDnsRes)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records?type=CNAME&name=$wwwName", null, 'GET');
        $cnPayload = ['type'=>'CNAME','name'=>$wwwName,'content'=>$domain,'ttl'=>1,'proxied'=>true];
        if (isset($wDnsRes['result'][0])) { $wid=$wDnsRes['result'][0]['id']; list($wc,$wr)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records/$wid",$cnPayload,'PUT'); $wAct='updated'; }
        else { list($wc,$wr)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/dns_records",$cnPayload,'POST'); $wAct='added'; }
        if (($wc===200)&&($wr['success']??false)) printLog("[$i] ✅ CNAME $wAct: $wwwName → $domain (proxied)", 'success');

        // Worker routes ion-domains
        $script = 'ion-domains';
        $pattern1 = "$domain/*";
        $pattern2 = "*.$domain/*";

        list($routesCode,$routesRes)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes", null, 'GET');
        $existingRoute1 = $existingRoute2 = null;
        if ($routesCode===200 && isset($routesRes['result'])) {
            foreach ($routesRes['result'] as $route) {
                if ($route['pattern'] === $pattern1) $existingRoute1 = $route['id'];
                if ($route['pattern'] === $pattern2) $existingRoute2 = $route['id'];
            }
        }

        $routePayload1 = ['pattern'=>$pattern1, 'script'=>$script];
        if ($existingRoute1) { list($rCode1,$rRes1)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes/$existingRoute1",$routePayload1,'PUT'); $rAct1='updated'; }
        else { list($rCode1,$rRes1)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes",$routePayload1,'POST'); $rAct1='added'; }

        $routePayload2 = ['pattern'=>$pattern2, 'script'=>$script];
        if ($existingRoute2) { list($rCode2,$rRes2)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes/$existingRoute2",$routePayload2,'PUT'); $rAct2='updated'; }
        else { list($rCode2,$rRes2)=cloudflareRequest("https://api.cloudflare.com/client/v4/zones/$zoneID/workers/routes",$routePayload2,'POST'); $rAct2='added'; }

        if (($rCode1===200)&&($rRes1['success']??false)) printLog("[$i] ✅ Worker route $rAct1: $pattern1 → $script", 'success');
        if (($rCode2===200)&&($rRes2['success']??false)) printLog("[$i] ✅ Worker route $rAct2: $pattern2 → $script", 'success');
        if (($rCode1===200)&&($rRes1['success']??false)&&($rCode2===200)&&($rRes2['success']??false)) {
            $row=getDomainRow($domain); if($row) markDomain($row['id'], ['worker_routes_set'=>1]);
        }
    }

    // Mark domain processed
    $row = getDomainRow($domain);
    if ($row) markDomain($row['id'], ['status'=>'added']);

    echo "</details>";

    $successCount++;
    if ($i % 5 === 0) echo str_repeat(' ', 2048);
    sleep(10);

    if (++$processedThisRun >= $batchSize) {
        printLog("Batch limit reached ($batchSize). Rerun for next batch.", 'info');
        break;
    }
}

// Final
printLog("🎉 Batch done!", 'info');
printLog("✅ Summary: $successCount processed, $skippedCount skipped.", 'info');
echo "</body></html>";
