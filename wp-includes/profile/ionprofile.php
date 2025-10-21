<?php
/**
 * ION Profile Page Renderer
 * Route: /@{handle}  → iondynamic.php?route=profile&handle={handle}
 *
 * Data:
 *  - Users:   IONEERS          (user_id PK, email, fullname, profile_name, handle, photo_url, created_at, user_role, status, preferences, login_count, location, slug, about, etc.)
 *  - Videos:  IONLocalVideos   (id, user_id, title, thumbnail, video_link, published_at, view_count, status, visibility, layout, source, etc.)
 *
 * Behavior:
 *  - Looks up user by handle (case-insensitive).
 *  - Renders profile header and a grid of the user's Public + Approved videos.
 *  - Uses view_count from IONLocalVideos for display (no schema change).
 *  - Safe fallbacks if optional fields are missing.
 */

declare(strict_types=1);

// ---- Bootstrap / Config ----------------------------------------------------
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Configuration missing." . $configPath;
    exit;
}
$config = require $configPath;

// Database connection using config keys (host, dbname, username, password)
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

// ---- Input -----------------------------------------------------------------
$handle = $_GET['handle'] ?? '';
$handle = trim($handle);

if ($handle === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $handle)) {
    http_response_code(404);
    echo "Profile not found.";
    exit;
}

// ---- Query: User -----------------------------------------------------------
$sqlUser = "SELECT user_id, email, fullname, profile_name, handle, photo_url, created_at, user_role, status, preferences, location, slug, about
            FROM IONEERS
            WHERE handle = :handle
            LIMIT 1";
$stmt = $pdo->prepare($sqlUser);
$stmt->execute([':handle' => $handle]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo "Profile not found.";
    exit;
}

// Optional derived values
$displayName = $user['profile_name'] ?: ($user['fullname'] ?: explode('@', $user['email'])[0]);
$joinedDate  = $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : '';
$avatar      = $user['photo_url'] ?: 'https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($displayName) . '&size=256';

// Get location and slug for linking
$location = $user['location'] ?? '';
$slug = $user['slug'] ?? '';

// Get about text (prefer the new about field, fallback to preferences bio)
$aboutText = '';
if (!empty($user['about'])) {
    $aboutText = $user['about'];
} elseif (!empty($user['preferences'])) {
    try {
        $prefs = json_decode($user['preferences'], true, 512, JSON_THROW_ON_ERROR);
        if (!empty($prefs['bio'])) {
            $aboutText = (string)$prefs['bio'];
        }
    } catch (Throwable $e) {}
}

// Fallback to default text if no about content
if (empty($aboutText)) {
    $aboutText = "Creator of thoughtful tutorials and deep-dives into modern web tooling.";
}


echo "<!-- All user fields: " . htmlspecialchars(print_r($user, true)) . " -->";

// ---- Query: Videos (Public + Approved) ------------------------------------
$sqlVid = "SELECT id, slug, title, thumbnail, video_link, published_at, view_count, layout, source, status, visibility
           FROM IONLocalVideos
           WHERE user_id = :uid
             AND visibility = 'Public'
             AND status = 'Approved'
           ORDER BY COALESCE(published_at, date_added) DESC
           LIMIT 100";
$stmt = $pdo->prepare($sqlVid);
$stmt->execute([':uid' => $user['user_id']]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Simple helpers
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function views_str($n): string {
    $n = (int)$n;
    if ($n >= 1000000) return number_format($n/1000000, 1) . 'M views';
    if ($n >= 1000)    return number_format($n/1000, 1) . 'k views';
    return $n . ' views';
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo '@' . h($user['handle']) . ' — ION'; ?></title>
<style>
    :root { --bg:#0f1216; --panel:#151a21; --muted:#8a94a6; --text:#e9eef7; --chip:#202733; --ring:#2c3442; }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;background:var(--bg);color:var(--text)}
    .wrap{max-width:1200px;margin:0 auto;padding:32px 20px}
    .header{display:grid;grid-template-columns:300px 1fr;gap:40px;align-items:start;margin-top:10px}
    .avatar{width:300px;height:300px;border-radius:16px;object-fit:cover;border:1px solid var(--ring);background:#0b0e12}
    .name{font-size:28px;font-weight:700}
    .sub{font-size:13px;color:var(--muted);display:flex;gap:12px;align-items:center}
    .badge{display:inline-flex;padding:3px 8px;background:var(--chip);border:1px solid var(--ring);border-radius:999px;font-size:12px;color:#cbd5e1}
    .bio{margin-top:10px;color:#c9d3e1;font-size:14px;line-height:1.5}
    .bio strong{color:#fff;font-weight:600}
    .bio em{color:#a8b3c0;font-style:italic}
    .bio u{text-decoration:underline}
    .bio ul,.bio ol{margin:8px 0;padding-left:20px}
    .bio li{margin:4px 0}
    .bio a{color:#60a5fa;text-decoration:none}
    .bio a:hover{text-decoration:underline}
    .left-column{display:flex;flex-direction:column;gap:20px}
    .right-column{flex:1}
    .about{background:var(--panel);border:1px solid var(--ring);padding:14px 16px;border-radius:14px;min-width:260px}
    .about h3{margin:0 0 8px 0;font-size:14px;font-weight:700}
    .about .row{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-top:1px solid var(--ring)}
    .about .row:first-of-type{border-top:none;padding-top:0}
    .location-link{color:var(--text);text-decoration:none;transition:color 0.2s ease}
    .location-link:hover{color:#60a5fa}
    .grid{display:grid;grid-template-columns:1fr;gap:18px;margin-top:26px}
    .card{background:var(--panel);border:1px solid var(--ring);border-radius:16px;overflow:hidden}
    .thumb{aspect-ratio:16/9;width:100%;object-fit:cover;background:#0b0e12;display:block}
    .meta{padding:10px 12px}
    .title{font-size:14px;line-height:1.35;margin:0 0 8px 0;color:#e6edf8}
    .row{display:flex;gap:12px;align-items:center;color:var(--muted);font-size:12px}
    .row .dot{width:4px;height:4px;border-radius:50%;background:#3c4555}
    .ion-mark{position:absolute;right:60px;top:18px}
    .topbar{display:flex;align-items:center;gap:16px}
    @media (max-width:1024px){ .grid{grid-template-columns:repeat(2,1fr);} .about{display:none;} }
    @media (max-width:640px){ .header{grid-template-columns:1fr;grid-auto-rows:auto} .topbar{justify-content:flex-start} .grid{grid-template-columns:1fr;} }
    a.card, a.title { text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">

    
    <div class="topbar">
        <a href="https://ions.com"><img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Logo" class="ion-mark" style="height:100px"></a>
    </div>

    <section class="header">
        <div class="left-column">
            <img class="avatar" src="<?php echo h($avatar); ?>" alt="<?php echo h($displayName); ?>">
            <aside class="about">
                <h3>About @<?php echo h($user['handle']); ?></h3>
                <div class="row"><span>Full name</span><span><?php echo h($user['fullname'] ?: $displayName); ?></span></div>
                <div class="row">
                    <span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <?php if ($location && $slug): ?>
                            <a href="https://ions.com/<?php echo h($slug); ?>" class="location-link" target="_blank">
                                <?php echo h($location); ?>
                            </a>
                        <?php elseif ($location): ?>
                            <?php echo h($location); ?>
                        <?php else: ?>
                            Remote
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($joinedDate): ?>
                <div class="row"><span>Joined</span><span><?php echo h($joinedDate); ?></span></div>
                <?php endif; ?>
            </aside>
        </div>
        <div class="right-column">
            <div class="name"><?php echo h($displayName); ?></div>
            <div class="sub">
                <span>@<?php echo h($user['handle']); ?></span>
                <?php if ($user['user_role']): ?><span class="dot"></span><span class="badge"><?php echo h($user['user_role']); ?></span><?php endif; ?>
            </div>
            <div class="bio"><?php echo $aboutText; ?></div>
        </div>
    </section>

    <h2 style="margin:26px 0 8px 0;font-size:16px;">Videos</h2>

    <section class="grid">
        <?php if (!$videos): ?>
            <div class="card" style="padding:20px">
                <div style="color:#b6c0cf;font-size:14px;">No public videos yet.</div>
            </div>
        <?php else: foreach ($videos as $v):
            $thumb = $v['thumbnail'] ?: '/assets/placeholders/video-16x9.png';
            $pub   = $v['published_at'] ? date('Y-m-d', strtotime($v['published_at'])) : '';
            $link  = $v['video_link'] ?: '#';
        ?>
        <a class="card" href="<?php echo h($link); ?>">
            <img class="thumb" src="<?php echo h($thumb); ?>" alt="<?php echo h($v['title']); ?>">
            <div class="meta">
                <h3 class="title"><?php echo h($v['title']); ?></h3>
                <div class="row">
                    <span><?php echo h(views_str($v['view_count'] ?? 0)); ?></span>
                    <?php if ($pub): ?><span class="dot"></span><span><?php echo h($pub); ?></span><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </section>
</div>
</body>
</html>