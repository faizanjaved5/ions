<?php
// Optional: Set $ION_NAVBAR_BASE_URL before including this file to control where assets are loaded from.
// Example:
//   $ION_NAVBAR_BASE_URL = "/static/ion-navbar/"; // must be a web-accessible URL
//   require_once __DIR__ . "/ion-navbar-embed.php";

// Normalize base URL
$__ion_base = isset($ION_NAVBAR_BASE_URL) ? rtrim($ION_NAVBAR_BASE_URL, "/") . "/" : "";
?>

<!-- ION Navbar Embed: fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

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