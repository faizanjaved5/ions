# PHP Integration Guide

## Embed Build (Shadow DOM)

Use the standalone embed bundle when integrating the navbar into PHP/Apache apps without React/Router. It mounts inside a Shadow DOM and reads user state from a JSON URL.

### Build

```bash
npm run build:navbar
```

This outputs `dist-navbar/ion-navbar.js` and `dist-navbar/ion-navbar.css`.

### PHP usage

```html
<link rel="preload" as="style" href="/assets/ion-navbar.css">
<div id="ion-navbar"></div>
<script src="/assets/ion-navbar.js"></script>
<script>
  IonNavbar.init({
    target: '#ion-navbar',
    cssUrl: '/assets/ion-navbar.css',
    userDataUrl: '/userProfileData.json',
    signInUrl: '/signin.php',
    signOutUrl: '/logout.php',
    uploadUrl: '/uploader.php',
    onSearch: (q) => location.href = '/search.php?q=' + encodeURIComponent(q),
    theme: 'dark',
  });
  // Optional: refresh after login/logout JSON updates
  // if (window.refreshUserState) window.refreshUserState();
</script>
```

This guide explains how to integrate the ION navbar with your PHP-based authentication system.

## How It Works

The navbar automatically updates when user login state changes. Your PHP backend updates the JSON file, and the React app detects and reflects these changes.

## Setup Instructions

### 1. Update JSON After Login

After a successful login in your PHP system, update `/src/data/userProfileData.json` with the logged-in user's information:

```php
<?php
// After successful login
$userData = [
    'isLoggedIn' => true,
    'user' => [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => $user->avatar_url,
        'notifications' => [
            // ... user's notifications
        ],
        'menuItems' => [
            ['label' => 'View Profile', 'link' => '/profile', 'icon' => 'User'],
            ['label' => 'Update Profile', 'link' => '/profile/edit', 'icon' => 'UserCog'],
            ['label' => 'Creator Dashboard', 'link' => '/dashboard', 'icon' => 'LayoutDashboard'],
            ['label' => 'My Videos', 'link' => '/my-videos', 'icon' => 'Video'],
            ['label' => 'Preferences', 'link' => '/preferences', 'icon' => 'Settings'],
            ['label' => 'Log Out', 'link' => '/logout', 'icon' => 'LogOut']
        ]
    ],
    'headerButtons' => [
        'upload' => [
            'label' => 'Upload',
            'link' => '/upload',
            'visible' => true
        ],
        'signIn' => [
            'label' => 'Sign In',
            'link' => '/signin',
            'visible' => true
        ]
    ]
];

file_put_contents('../src/data/userProfileData.json', json_encode($userData, JSON_PRETTY_PRINT));
?>
```

### 2. Trigger Menu Refresh

After updating the JSON, call the global refresh function to update the UI:

```html
<script>
    // Call this after your login process completes
    if (window.refreshUserState) {
        window.refreshUserState();
    }
</script>
```

### 3. Complete Login Example

```php
<?php
// login.php
session_start();

if ($_POST['login']) {
    // Verify credentials
    $user = authenticate($_POST['username'], $_POST['password']);
    
    if ($user) {
        $_SESSION['user_id'] = $user->id;
        
        // Update the JSON with user data
        $userData = [
            'isLoggedIn' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url,
                'notifications' => getUserNotifications($user->id),
                'menuItems' => [
                    ['label' => 'View Profile', 'link' => '/profile', 'icon' => 'User'],
                    ['label' => 'Update Profile', 'link' => '/profile/edit', 'icon' => 'UserCog'],
                    ['label' => 'Creator Dashboard', 'link' => '/dashboard', 'icon' => 'LayoutDashboard'],
                    ['label' => 'My Videos', 'link' => '/my-videos', 'icon' => 'Video'],
                    ['label' => 'Preferences', 'link' => '/preferences', 'icon' => 'Settings'],
                    ['label' => 'Log Out', 'link' => '/logout', 'icon' => 'LogOut']
                ]
            ],
            'headerButtons' => [
                'upload' => ['label' => 'Upload', 'link' => '/upload', 'visible' => true],
                'signIn' => ['label' => 'Sign In', 'link' => '/signin', 'visible' => true]
            ]
        ];
        
        file_put_contents('../src/data/userProfileData.json', json_encode($userData, JSON_PRETTY_PRINT));
        
        // Return success and trigger refresh
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
```

### 4. JavaScript Integration

If you're using AJAX for login:

```javascript
// After successful login AJAX call
fetch('/api/login.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Trigger the navbar to refresh
        if (window.refreshUserState) {
            window.refreshUserState();
        }
    }
});
```

### 5. Logout

For logout, update the JSON to logged-out state:

```php
<?php
// logout.php
session_destroy();

$userData = [
    'isLoggedIn' => false,
    'user' => [
        'name' => '',
        'email' => '',
        'avatar' => '',
        'notifications' => [],
        'menuItems' => []
    ],
    'headerButtons' => [
        'upload' => ['label' => 'Upload', 'link' => '/upload', 'visible' => true],
        'signIn' => ['label' => 'Sign In', 'link' => '/signin', 'visible' => true]
    ]
];

file_put_contents('../src/data/userProfileData.json', json_encode($userData, JSON_PRETTY_PRINT));
?>

<script>
    if (window.refreshUserState) {
        window.refreshUserState();
    }
</script>
```

## Available Lucide Icons

The `icon` field in menuItems uses Lucide React icons. Common icons include:

- `User` - User profile
- `UserCog` - Settings/Edit profile
- `LayoutDashboard` - Dashboard
- `Video` - Videos/Media
- `Settings` - Settings/Preferences
- `LogOut` - Logout
- `Bell` - Notifications
- `Home` - Home
- `Search` - Search
- `Upload` - Upload

See full list at: https://lucide.dev/icons/

## Notes

- The JSON file path must be accessible by your PHP scripts
- Ensure proper file permissions for PHP to write to the JSON file
- The `window.refreshUserState()` function is automatically available once the React app loads
- Changes are reflected immediately after calling `refreshUserState()`
