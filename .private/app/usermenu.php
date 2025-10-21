<?php
// User Menu Component
// This file should be included in all sections to ensure consistent user menu

if (!isset($user_photo_url) || !isset($_SESSION['user_email'])) {
    // If variables aren't set, try to get them
    if (isset($_SESSION['user_email'])) {
        $user_email = $_SESSION['user_email'];
        if (!isset($user_data)) {
            // Try to get database connection
            if (isset($wpdb)) {
                $user_data = $wpdb->get_row("SELECT photo_url, user_role, preferences, fullname, user_id, profile_name, handle, phone, dob, location, user_url, about FROM IONEERS WHERE email = %s", $user_email);
            } elseif (isset($db)) {
                $user_data = $db->get_row("SELECT photo_url, user_role, preferences, fullname, user_id, profile_name, handle, phone, dob, location, user_url, about FROM IONEERS WHERE email = %s", $user_email);
            } else {
                // Try to include database connection
                try {
                    require_once __DIR__ . '/../config/database.php';
                    $user_data = $db->get_row("SELECT photo_url, user_role, preferences, fullname, user_id, profile_name, handle, phone, dob, location, user_url, about FROM IONEERS WHERE email = %s", $user_email);
                } catch (Exception $e) {
                    // Fallback: create minimal user data
                    $user_data = (object) [
                        'photo_url' => null,
                        'user_role' => 'Guest',
                        'fullname' => null,
                        'user_id' => null,
                        'profile_name' => null,
                        'handle' => null,
                        'phone' => null,
                        'dob' => null,
                        'location' => null,
                        'user_url' => null,
                        'about' => null
                    ];
                }
            }
            $user_photo_url = $user_data->photo_url ?? null;
            $user_fullname = $user_data->fullname ?? null;
        }
    }
}

$user_email = $_SESSION['user_email'] ?? '';
$avatar_initials = !empty($user_email) ? substr($user_email, 0, 2) : '??';
?>

<!-- User menu positioned separately in top right -->
<div class="user-menu header-user-menu">
    <?php if (!empty($user_photo_url)): ?>
        <img src="<?= htmlspecialchars($user_photo_url) ?>" 
             alt="User Avatar" 
             class="user-avatar" 
             id="userAvatar">
    <?php else: ?>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($avatar_initials) ?>&background=6366f1&color=fff&rounded=false&size=32" 
             alt="User Avatar" 
             class="user-avatar" 
             id="userAvatar">
    <?php endif; ?>
    <div class="user-dropdown" id="userDropdown">
        <div class="dropdown-header">
            <?php if (!empty($user_fullname)): ?>
                <p class="user-fullname"><?= htmlspecialchars($user_fullname) ?></p>
            <?php endif; ?>
            <p class="user-email"><?= htmlspecialchars($_SESSION['user_email']) ?></p>
        </div>
        <button id="update-my-profile" class="dropdown-action-btn">
            <span class="menu-icon">✏️</span>
            <span>Update My Profile</span>
        </button>
        <a href="preferences.php">
            <span class="menu-icon">⚙️</span>
            <span>UI Preferences</span>
        </a>
        <div class="dropdown-divider"></div>
        <div class="avatar-section">
            <p class="section-title">Profile Picture</p>
            <button id="sync-google-avatar" class="dropdown-action-btn">
                <span class="btn-icon">🔄</span>
                <span>Sync from Google</span>
            </button>
            <button id="update-avatar-manual" class="dropdown-action-btn">
                <span class="btn-icon">🖼️</span>
                <span>Custom URL</span>
            </button>
        </div>
        <div class="dropdown-divider"></div>
        <a href="?refresh_countries=true">
            <span class="menu-icon">🗑️</span>
            <span>Clear Cache</span>
        </a>
        <a href="/login/logout.php">
            <span class="menu-icon">🚪</span>
            <span>Log Out</span>
        </a>
    </div>
</div>

<!-- User Menu JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User menu dropdown functionality
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    
    console.log('User menu initializing...', { userAvatar, userDropdown });
    
    if (userAvatar && userDropdown) {
        console.log('User menu elements found, adding click handlers');
        
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('User avatar clicked, toggling dropdown');
            userDropdown.classList.toggle('show');
            console.log('Dropdown classes:', userDropdown.className);
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
        
        // Handle Update My Profile button click
        const updateProfileBtn = document.getElementById('update-my-profile');
        if (updateProfileBtn) {
            updateProfileBtn.addEventListener('click', function() {
                // Close the dropdown
                userDropdown.classList.remove('show');
                
                // Get current user data from PHP variables
                const currentUserData = {
                    user_id: '<?= $user_data->user_id ?? '' ?>',
                    fullname: '<?= htmlspecialchars($user_data->fullname ?? '') ?>',
                    email: '<?= htmlspecialchars($_SESSION['user_email']) ?>',
                    profile_name: '<?= htmlspecialchars($user_data->profile_name ?? '') ?>',
                    handle: '<?= htmlspecialchars($user_data->handle ?? '') ?>',
                    phone: '<?= htmlspecialchars($user_data->phone ?? '') ?>',
                    dob: '<?= htmlspecialchars($user_data->dob ?? '') ?>',
                    location: '<?= htmlspecialchars($user_data->location ?? '') ?>',
                    user_url: '<?= htmlspecialchars($user_data->user_url ?? '') ?>',
                    about: '<?= addslashes($user_data->about ?? '') ?>',
                    photo_url: '<?= htmlspecialchars($user_data->photo_url ?? '') ?>',
                    user_role: '<?= htmlspecialchars($user_data->user_role ?? '') ?>',
                    status: 'active'
                };
                
                // Open the edit dialog in self-edit mode
                if (window.IONProfile) {
                    window.IONProfile.open(currentUserData, true); // true = self-edit mode
                } else if (typeof openEditUserDialog === 'function') {
                    openEditUserDialog(currentUserData, true); // fallback for backward compatibility
                } else {
                    console.error('Profile dialog not available');
                }
            });
        }
        
        console.log('User menu initialized successfully');
    } else {
        console.error('User menu elements not found:', { 
            userAvatar: !!userAvatar, 
            userDropdown: !!userDropdown 
        });
    }
});
</script>