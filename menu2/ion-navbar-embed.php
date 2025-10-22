<?php
// Optional: Set $ION_NAVBAR_BASE_URL before including this file to control where assets are loaded from.
// Example:
//   $ION_NAVBAR_BASE_URL = "/static/ion-navbar/"; // must be a web-accessible URL
//   require_once __DIR__ . "/ion-navbar-embed.php";

// Normalize base URL
$__ion_base = isset($ION_NAVBAR_BASE_URL) ? rtrim($ION_NAVBAR_BASE_URL, "/") . "/" : "";

// Get user info if available (expect parent to set $is_viewer_logged_in and $current_viewer)
$__ion_user_logged_in = isset($is_viewer_logged_in) && $is_viewer_logged_in;
$__ion_user = isset($current_viewer) ? $current_viewer : null;
?>

<!-- ION Navbar Embed: fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<link rel="preload" as="style" href="<?php echo htmlspecialchars($__ion_base, ENT_QUOTES); ?>ion-navbar.css">

<!-- ION Navbar Embed: User Menu Styles -->
<style>
/* User Menu Integration with Navbar */
#ion-navbar-root {
    position: relative;
}

/* Ensure navbar and user menu are in the same container */
.ion-navbar-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #2c3135;
    height: 70px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10000;
}

#ion-navbar-root {
    flex: 1;
    height: 100%;
    position: relative !important;
    background: transparent !important;
}

.ion-user-menu-overlay {
    display: flex;
    align-items: center;
    padding: 0 50px 0 20px;
    height: 100%;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .ion-user-menu-overlay {
        padding: 0 15px 0 10px;
    }
}

.ion-user-menu {
    position: relative;
}

.ion-user-avatar-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 2px solid rgba(245, 158, 11, 0.3);
    background: transparent;
    padding: 0;
    cursor: pointer;
    transition: all 0.2s ease;
    overflow: hidden;
}

.ion-user-avatar-btn:hover {
    border-color: #f59e0b;
    transform: scale(1.05);
}

.ion-user-avatar-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ion-user-avatar-btn .avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.ion-user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--panel, #1a1d21);
    backdrop-filter: blur(12px);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    min-width: 240px;
    padding: 12px;
    display: none;
    z-index: 1000;
    border: 1px solid var(--ring, rgba(255, 255, 255, 0.1));
}

.ion-user-dropdown.active {
    display: block;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ion-user-info {
    padding: 12px;
    background: var(--chip, #2c3135);
    border-radius: 8px;
    margin-bottom: 8px;
}

.ion-user-name {
    font-weight: 600;
    color: var(--text, #ffffff);
    margin-bottom: 4px;
    font-size: 15px;
}

.ion-user-email {
    color: var(--muted, #8b92a0);
    font-size: 13px;
}

.ion-user-menu-divider {
    height: 1px;
    background: var(--ring, rgba(255, 255, 255, 0.1));
    margin: 8px 0;
}

.ion-user-menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    color: var(--text, #ffffff);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 14px;
    opacity: 0.8;
}

.ion-user-menu-item:hover {
    background: var(--chip, #2c3135);
    opacity: 1;
}

.ion-user-menu-item.ion-logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.ion-user-menu-item svg {
    flex-shrink: 0;
}
</style>

<!-- Unified navbar container -->
<div class="ion-navbar-container">
    <div id="ion-navbar-root"></div>
    
    <!-- User Menu (integrated in same container) -->
    <?php if ($__ion_user_logged_in && $__ion_user): ?>
    <div class="ion-user-menu-overlay">
        <div class="ion-user-menu">
        <button class="ion-user-avatar-btn" onclick="toggleUserMenu()" title="Account menu" aria-label="Open user menu">
            <?php if (!empty($__ion_user->photo_url)): ?>
                <img src="<?= htmlspecialchars($__ion_user->photo_url) ?>" alt="<?= htmlspecialchars($__ion_user->fullname ?: $__ion_user->handle) ?>">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <?= strtoupper(substr($__ion_user->handle ?: $__ion_user->email, 0, 1)) ?>
                </div>
            <?php endif; ?>
        </button>
        <div class="ion-user-dropdown" id="ionUserDropdown">
            <div class="ion-user-info">
                <div class="ion-user-name"><?= htmlspecialchars($__ion_user->fullname ?: $__ion_user->handle) ?></div>
                <div class="ion-user-email"><?= htmlspecialchars($__ion_user->email) ?></div>
            </div>
            <div class="ion-user-menu-divider"></div>
            <a href="/@<?= htmlspecialchars($__ion_user->handle) ?>" class="ion-user-menu-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>View Profile</span>
            </a>
            <a href="/app/creators.php" class="ion-user-menu-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
                <span>Creator Dashboard</span>
            </a>
            <a href="/app/directory.php" class="ion-user-menu-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>My Videos</span>
            </a>
            <div class="ion-user-menu-divider"></div>
            <a href="/login/logout.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="ion-user-menu-item ion-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Log Out</span>
            </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add spacing below fixed navbar -->
<div style="height: 70px;"></div>

<script>
// User menu toggle functionality
function toggleUserMenu() {
    const dropdown = document.getElementById('ionUserDropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('ionUserDropdown');
    const userMenu = document.querySelector('.ion-user-menu');
    
    if (dropdown && userMenu && !userMenu.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});
</script>

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