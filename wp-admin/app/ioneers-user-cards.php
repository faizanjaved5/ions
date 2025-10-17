<?php
// ioneers-user-cards.php
// Dynamic user cards layout

foreach ($users as $user):
    $role_class = strtolower($user->user_role ?? 'guest');
    $status = strtolower($user->status ?? 'active');
    $status_class = $status === 'active' || empty($status) ? 'active' : $status;
    $last_login = $user->last_login ? date('M j, Y g:i A', strtotime($user->last_login)) : 'Never';
    $joined = date('M j, Y g:i A', strtotime($user->created_at));
    $user_filter_class = $role_class;
    $user_status_filter = $status_class;
    $user_video_count = isset($user->video_count) ? (int)$user->video_count : 0;
    if ($user_video_count == 0) $video_class = 'count-0';
    elseif ($user_video_count <= 10) $video_class = 'count-low';
    elseif ($user_video_count < 100) $video_class = 'count-medium';
    else $video_class = 'count-high';
?>
                    <div class="user-card" data-role="<?= $user_filter_class ?>" data-status="<?= $user_status_filter ?>" data-user-id="<?= $user->user_id ?>" data-fullname="<?= h($user->fullname ?: $user->email) ?>" data-email="<?= h($user->email) ?>" data-profile-name="<?= h($user->profile_name ?? '') ?>" data-handle="<?= h($user->handle ?? '') ?>" data-phone="<?= h($user->phone ?? '') ?>" data-dob="<?= h($user->dob ?? '') ?>" data-location="<?= h($user->location ?? '') ?>" data-user-url="<?= h($user->user_url ?? '') ?>" data-about="<?= htmlspecialchars($user->about ?? '', ENT_QUOTES, 'UTF-8') ?>" data-photo-url="<?= h($user->photo_url ?? '') ?>" data-user-role="<?= h($user->user_role ?? 'Guest') ?>" data-status="<?= h($status) ?>" onclick="openEditUserDialogFromCard(this)" style="cursor: pointer;">
        <div class="user-card__content">
            <div class="user-header">
            <?php if ($user->photo_url): ?>
                <img src="<?= h($user->photo_url) ?>" alt="<?= h($user->fullname) ?>" class="user-avatar-large">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode(substr($user->email, 0, 2)) ?>&background=6366f1&color=fff&rounded=false&size=80" 
                     alt="User Avatar" class="user-avatar-large">
            <?php endif; ?>
           
            <div class="user-info">
                <h3><?= h($user->fullname ?: $user->email) ?></h3>
                <div class="role-status-row">
                    <div class="role-dropdown-container">
                        <?php 
                        // Owners can change anyone, Admins can change anyone except Owners
                        $can_change_role = ($user_role === 'Owner') || ($user_role === 'Admin' && $user->user_role !== 'Owner');
                        if ($can_change_role): 
                        ?>
                            <div class="user-role role-<?= $role_class ?> clickable" onclick="event.stopPropagation(); toggleRoleDropdown('<?= $user->user_id ?>', '<?= $user->user_role ?>')" id="role-<?= $user->user_id ?>">
                                <?= h($user->user_role ?: 'Guest') ?>
                                <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6,9 12,15 18,9"></polyline>
                                </svg>
                            </div>
                            <div class="role-dropdown" id="role-dropdown-<?= $user->user_id ?>" style="display: none;">
                                <?php if ($user_role === 'Owner'): ?>
                                    <div class="role-option" onclick="event.stopPropagation(); changeUserRole('<?= $user->user_id ?>', 'Owner')">Owner</div>
                                <?php endif; ?>
                                <div class="role-option" onclick="event.stopPropagation(); changeUserRole('<?= $user->user_id ?>', 'Admin')">Admin</div>
                                <div class="role-option" onclick="event.stopPropagation(); changeUserRole('<?= $user->user_id ?>', 'Member')">Member</div>
                                <div class="role-option" onclick="event.stopPropagation(); changeUserRole('<?= $user->user_id ?>', 'Viewer')">Viewer</div>
                                <div class="role-option" onclick="event.stopPropagation(); changeUserRole('<?= $user->user_id ?>', 'Creator')">Creator</div>
                                <div class="role-option" onclick="event.stopPropagation(); changeUserRole('<?= $user->user_id ?>', 'Guest')">Guest</div>
                            </div>
                        <?php else: ?>
                            <div class="user-role role-<?= $role_class ?>">
                                <?= h($user->user_role ?: 'Guest') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                   
                    <div class="status-container">
                        <?php 
                        // Same permission logic for status changes
                        $can_change_status = ($user_role === 'Owner') || ($user_role === 'Admin' && $user->user_role !== 'Owner');
                        if ($can_change_status): 
                        ?>
                            <div class="user-status status-<?= $status_class ?> clickable" onclick="event.stopPropagation(); toggleStatusDropdown('<?= $user->user_id ?>', '<?= $status ?>')" id="status-<?= $user->user_id ?>">
                                <?= ucfirst($status) ?>
                                <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6,9 12,15 18,9"></polyline>
                                </svg>
                            </div>
                            <div class="status-dropdown" id="status-dropdown-<?= $user->user_id ?>" style="display: none;">
                                <div class="status-option" onclick="event.stopPropagation(); changeUserStatus('<?= $user->user_id ?>', 'active')">Active</div>
                                <div class="status-option" onclick="event.stopPropagation(); changeUserStatus('<?= $user->user_id ?>', 'inactive')">Inactive</div>
                                <div class="status-option" onclick="event.stopPropagation(); changeUserStatus('<?= $user->user_id ?>', 'blocked')">Blocked</div>
                            </div>
                        <?php else: ?>
                            <div class="user-status status-<?= $status_class ?>">
                                <?= ucfirst($status) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="details-with-oauth">
            <div class="user-details">
                <p><strong>📧</strong> <?= h($user->email) ?></p>
                <p><strong>🕐</strong> Last login: <?= h($last_login) ?></p>
                <p><strong>📅</strong> Joined on: <?= h($joined) ?></p>
            </div>
            <!-- OAuth Icons positioned vertically on the right -->
            <div class="oauth-icons-container">
                <?php if ($user->google_id): ?>
                    <div class="social-icon google" title="Connected via Google">
                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/google.svg" alt="Google">
                    </div>
                <?php endif; ?>
                <?php if ($user->discord_user_id): ?>
                    <div class="social-icon discord" title="Connected via Discord">
                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/discord.svg" alt="Discord">
                    </div>
                <?php endif; ?>
                <?php if ($user->linkedin_id): ?>
                    <div class="social-icon linkedin" title="Connected via LinkedIn">
                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/linkedin.svg" alt="LinkedIn">
                    </div>
                <?php endif; ?>
                <?php if ($user->meta_facebook_id): ?>
                    <div class="social-icon facebook" title="Connected via Facebook">
                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/facebook.svg" alt="Facebook">
                    </div>
                <?php endif; ?>
                <?php if ($user->x_user_id): ?>
                    <div class="social-icon x" title="Connected via X (Twitter)">
                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/x.svg" alt="X">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="user-actions">
            <?php
            // Show the permissions/capabilities for THIS card's role
            $card_user_role = $user->user_role; // The role of this card's user
            $is_own_card = ($user->user_id == $user_unique_id); // Is this the logged-in user's own card?
            
            // Display the standard permissions for each role type
            switch (trim($card_user_role)) {
                case 'Owner':
                    // Owner cards show full permissions
                    echo '<button class="action-btn btn-view">View All</button>';
                    echo '<button class="action-btn btn-edit">Add & Edit</button>';
                    echo '<button class="action-btn btn-delete">Delete Any</button>';
                    echo '<button class="action-btn btn-manage">Manage</button>';
                    break;
                    
                case 'Admin':
                    // Admin cards show admin permissions
                    echo '<button class="action-btn btn-view">View All</button>';
                    echo '<button class="action-btn btn-edit">Add & Edit</button>';
                    echo '<button class="action-btn btn-delete">Delete</button>';
                    echo '<button class="action-btn btn-manage">Manage</button>';
                    break;
                    
                case 'Member':
                    // Member cards show member permissions
                    echo '<button class="action-btn btn-view">View All</button>';
                    echo '<button class="action-btn btn-edit">Add & Edit Own</button>';
                    echo '<button class="action-btn btn-delete">Delete Own</button>';
                    break;
                    
                case 'Creator':
                    // Creator cards show creator permissions
                    echo '<button class="action-btn btn-view">View Own</button>';
                    echo '<button class="action-btn btn-edit">Add & Edit Own</button>';
                    echo '<button class="action-btn btn-delete">Delete Own</button>';
                    break;
                    
                case 'Viewer':
                    // Viewer cards show viewer permissions
                    echo '<button class="action-btn btn-view">View All</button>';
                    break;
                    
                default: // Guest or unknown
                    echo '<button class="action-btn btn-view">View Only</button>';
                    break;
            }
            ?>
        </div>
        </div> <!-- Close user-card__content -->
       
        <!-- Video count footer with color coding -->
        <?php
        // Determine video count color class based on count
        $video_count_class = 'video-count--none';
        if ($user_video_count >= 50) {
            $video_count_class = 'video-count--high';
        } elseif ($user_video_count >= 11) {
            $video_count_class = 'video-count--medium';
        } elseif ($user_video_count >= 1) {
            $video_count_class = 'video-count--low';
        }
        

        ?>
        <div class="user-card__footer <?= $video_count_class ?>" id="video-footer-<?= $user->user_id ?>">
            <div class="video-count__info">
                <span class="video-count__icon">📹</span>
                <span class="video-count__number" id="video-count-<?= $user->user_id ?>"><?= number_format($user_video_count) ?></span>
                <span> CREAT<b>ION</b>S</span>
            </div>
            <?php if ($user->handle): ?>
                <div class="profile-link-container">
                    <?php
                    $profile_visibility = $user->profile_visibility ?? 'Private';
                    $icon_path = '';
                    $icon_color = '';
                    
                    switch ($profile_visibility) {
                        case 'Public':
                            $icon_path = 'assets/icons/profile-public.svg';
                            $icon_color = 'profile-public';
                            break;
                        case 'Private':
                            $icon_path = 'assets/icons/profile-private.svg';
                            $icon_color = 'profile-private';
                            break;
                        case 'Restricted':
                            $icon_path = 'assets/icons/profile-restricted.svg';
                            $icon_color = 'profile-restricted';
                            break;
                        default:
                            $icon_path = 'assets/icons/profile-private.svg';
                            $icon_color = 'profile-private';
                    }
                    ?>
                    <a href="https://ions.com/@<?= h($user->handle) ?>" 
                       class="profile-link <?= $icon_color ?>" 
                       target="_blank" 
                       title="View <?= h($user->fullname ?: $user->email) ?>'s profile (<?= $profile_visibility ?>)">
                        <img src="<?= $icon_path ?>" alt="Profile" class="profile-icon">
                    </a>
                </div>
            <?php else: ?>
                <!-- Debug: Show when handle is missing -->
                <div class="profile-link-container">
                    <span style="color: red; font-size: 12px;">No handle</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>