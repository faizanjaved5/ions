<?php
// Example payload structure:
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
    "headerButtons" => [
        "upload" => [
            "label" => "Upload",
            "link" => "/upload",
            "visible" => true
        ],
        "signIn" => [
            "label" => "Sign In",
            "link" => "/signin",
            "visible" => true
        ]
    ]
];


// Normalize base URL
$__ion_base = isset($ION_NAVBAR_BASE_URL) ? rtrim($ION_NAVBAR_BASE_URL, "/") . "/" : "";
?>

<!-- ION Navbar Embed: fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<link rel="preload" as="style" href="<?php echo htmlspecialchars($__ion_base, ENT_QUOTES); ?>ion-navbar.css">

<!-- ION Navbar Embed: styles injected by JS (no CSS file) -->

<!-- Container is optional; if omitted, the script will create one at the top of <body> -->
<div id="ion-navbar-root"></div>

<!-- ION Navbar Embed: script -->
<script>
    // Minimal globals expected by some libraries
    window.process = window.process || {
        env: {
            NODE_ENV: 'production'
        }
    };
    window.global = window.global || window;

    // Optional: Inline user/menu data provided by PHP
    <?php if (isset($ION_USER_MENU)) { ?>
        try {
            window.__ION_PROFILE_DATA = JSON.parse('<?php echo json_encode($ION_USER_MENU, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>');
        } catch (e) {}
    <?php } ?>

        // CRITICAL: Initialize theme BEFORE React renders to prevent flickering
        // This must run before any React components mount
        (function() {
            // Theme utility functions (inline to avoid dependency issues)
            const STORAGE_KEY = 'ion-theme';
            const COOKIE_KEY = 'theme';
            const DEFAULT_THEME = 'dark';

            function getInitialTheme() {
                // Try localStorage first
                if (typeof window !== 'undefined') {
                    const stored = localStorage.getItem(STORAGE_KEY);
                    if (stored === 'light' || stored === 'dark') {
                        return stored;
                    }

                    // Try cookie as fallback
                    const cookieMatch = document.cookie.match(new RegExp('(^| )' + COOKIE_KEY + '=([^;]+)'));
                    if (cookieMatch && (cookieMatch[2] === 'light' || cookieMatch[2] === 'dark')) {
                        return cookieMatch[2];
                    }
                }
                return DEFAULT_THEME;
            }

            function setTheme(theme) {
                // Set data-theme attribute on body
                document.body.setAttribute('data-theme', theme);

                // Save to localStorage
                localStorage.setItem(STORAGE_KEY, theme);

                // Save to cookie (for PHP compatibility)
                document.cookie = COOKIE_KEY + '=' + theme + '; path=/; max-age=31536000';
            }

            function toggleTheme() {
                const currentTheme = document.body.getAttribute('data-theme') || getInitialTheme();
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                setTheme(newTheme);
                return newTheme;
            }

            // Initialize theme immediately
            const theme = getInitialTheme();
            setTheme(theme);

            // Expose globally for other scripts
            window.IONTheme = {
                getTheme: () => document.body.getAttribute('data-theme') || getInitialTheme(),
                setTheme: setTheme,
                toggleTheme: toggleTheme,
                initTheme: () => setTheme(getInitialTheme()),
                getInitialTheme: getInitialTheme
            };
            window.toggleTheme = toggleTheme;
            window.IONToggleTheme = toggleTheme;
            window.switchTheme = toggleTheme;

            console.log('🎨 ION Theme initialized:', theme);
        })();
</script>
<script src="<?php echo htmlspecialchars($__ion_base, ENT_QUOTES); ?>ion-navbar.iife.js"></script>
<script>
    (function() {
        if (window.IONNavbar && typeof window.IONNavbar.mount === 'function') {
            window.IONNavbar.mount('#ion-navbar-root', {
                useShadowDom: true,
                cssHref: '<?php echo htmlspecialchars($__ion_base, ENT_QUOTES); ?>ion-navbar.css'
            });
        }
    })();
</script>