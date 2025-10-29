<?php
$dir = __DIR__;
$css = 'ion-navbar.css';
$js = 'ion-navbar.js';
$cssPath = '';
$jsPath = '';
if (isset($ION_NAVBAR_BASE_URL)) {
    $cssPath = $ION_NAVBAR_BASE_URL . $css;
    $jsPath = $ION_NAVBAR_BASE_URL . $js;
} else {
    $cssPath = $css;
    $jsPath = $js;
    $ION_NAVBAR_BASE_URL = '/';
}

$cssVer = time();
$jsVer = time();
$cssHref = $cssPath . '?v=' . $cssVer;
$jsSrc = $jsPath . '?v=' . $jsVer;

$ION_USER_MENU = [
    "isLoggedIn" => ((isset($_SESSION['logged_in']) && $_SESSION['logged_in']) || (isset($_SESSION['authenticated']) && $_SESSION['authenticated'])) ? true : false,
    "user" => [
        "name" => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
        "email" => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '',
        "avatar" => isset($_SESSION['photo_url']) ? $_SESSION['photo_url'] : '',
        "notifications" => [
            [
                "id" => 1,
                "message" => "Your video 'Introduction to ION' has reached 1,000 views",
                "time" => "2 hours ago",
                "read" => false
            ],
            // [
            //     "id" => 2,
            //     "message" => "New comment on your post",
            //     "time" => "5 hours ago",
            //     "read" => false
            // ],
            // [
            //     "id" => 3,
            //     "message" => "Your profile was viewed 25 times today",
            //     "time" => "1 day ago",
            //     "read" => true
            // ]
        ],
        "menuItems" => [
            [
                "label" => "View Profile",
                "link" => isset($_SESSION['user_handle']) ? "/@" . $_SESSION['user_handle'] : "",
                "icon" => "User"
            ],
            [
                "label" => "Update Profile",
                "link" => "/profile/edit",
                "icon" => "UserCog"
            ],
            [
                "label" => "Creator Dashboard",
                "link" => "/app/creators.php",
                "icon" => "LayoutDashboard"
            ],
            [
                "label" => "My Videos",
                "link" => "/my-videos",
                "icon" => "Video"
            ],
            [
                "label" => "Preferences",
                "link" => "/app/preferences.php",
                "icon" => "Settings"
            ],
            [
                "label" => "Log Out",
                "link" => "/login/logout.php",
                "icon" => "LogOut"
            ]
        ]
    ],
];

// If host app provides $ION_USER_MENU, pass it inline to the navbar
$userDataJson = isset($ION_USER_MENU)
    ? json_encode($ION_USER_MENU, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    : null;
?>

<!-- Fonts for Bebas Neue (used in navbar labels) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

<!-- Preload navbar CSS with cache-busting -->
<link rel="preload" as="style" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>">

<div id="ion-navbar"></div>

<!-- Navbar bundle with cache-busting -->
<script src="<?= htmlspecialchars($jsSrc, ENT_QUOTES) ?>"></script>
<script>
  IonNavbar.init({
    target: '#ion-navbar',
    cssUrl: '<?= $cssHref ?>',
    <?php if ($userDataJson): ?>
    userData: <?= $userDataJson ?>,
    <?php else: ?>
    userDataUrl: '<?= $ION_NAVBAR_BASE_URL ?>userProfileData.json',
    <?php endif; ?>
    // Optional: external SVG sprite file containing symbols like #ion-archery
    // Put the sprite file next to this PHP (e.g., ion-sprite.svg) or host centrally
    spriteUrl: '<?= $ION_NAVBAR_BASE_URL ?>ion-sprite.svg',
    signInUrl: '/login/index.php',
    signOutUrl: '/login/logout.php',
    onSearch: (q) => location.href = '/search?q=' + encodeURIComponent(q),
    theme: 'dark'
  });
  // Optional: if PHP updates userProfileData.json post-login, trigger a refresh
  if (window.refreshUserState) window.refreshUserState();
  </script>
