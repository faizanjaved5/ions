# Architecture Overview

## MVC Structure

This application follows a clean MVC (Model-View-Controller) architecture pattern.

### File Organization

```
ion/
├── index.php              ← Controller (Business Logic)
├── config.php             ← Configuration
├── menu.css               ← Styles (ION Networks Theme)
├── views/
│   └── menu_view.php      ← View (Presentation Layer)
└── omar/                  ← Original implementation (preserved)
```

## Components

### 1. Controller: `index.php`

**Responsibilities:**
- Database connection management
- Data fetching and processing
- Business logic
- Data preparation for view

**Key Functions:**
```php
getDbConnection()           // Establishes PDO connection
getParentMenus($pdo)        // Fetches menu groups
getMenuItems($pdo, ...)     // Fetches menu items
buildMenuTree($pdo, ...)    // Builds hierarchical structure
renderMenuChildren(...)     // Helper for rendering nested items
```

**Data Preparation:**
```php
// Prepares structured data
$menus = [
    [
        'id' => 110,
        'name' => 'ION Local',
        'items' => [
            [
                'id' => 1,
                'label' => 'Home',
                'url' => '/',
                'target' => '_self',
                'children' => [...]
            ],
            // ... more items
        ]
    ],
    // ... more menus
];

// Passes to view
$pageTitle = 'ION Menu System';
$logoUrl = '/ion/omar/menu/ion-logo-gold.png';
require_once __DIR__ . '/views/menu_view.php';
```

### 2. View: `views/menu_view.php`

**Responsibilities:**
- HTML structure and layout
- ION Networks navigation UI
- Menu item rendering
- User interactions (search, mobile menu, theme toggle)

**Receives from Controller:**
- `$menus` - Array of menu structures
- `$pageTitle` - Page title
- `$logoUrl` - Logo image path

**Features:**
- ION Networks navigation header
- Dark/Light theme toggle
- Responsive design
- Mobile menu
- Search functionality
- Hierarchical menu display

### 3. Model: Database Structure

**Tables:**

**`ion_menus`** (Parent menus)
```sql
- id (Primary Key)
- name (Menu group name)
```

**`IONmenu`** (Menu items)
```sql
- id (Primary Key)
- menu_id (Foreign Key → ion_menus.id)
- label (Display text)
- url (Link URL)
- target (Link target)
- classes (CSS classes)
- parent (Parent item ID, 0 = top-level)
- position (Display order)
- object_id (Reference ID)
- object (Object type)
```

## Data Flow

```
┌─────────────────────────────────────────────────────┐
│ 1. User Request                                     │
│    http://localhost/ion/                            │
└──────────────────┬──────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────┐
│ 2. Controller (index.php)                           │
│    - Connects to database                           │
│    - Fetches parent menus                           │
│    - Builds hierarchical menu tree                  │
│    - Prepares variables:                            │
│      • $menus (structured data)                     │
│      • $pageTitle                                   │
│      • $logoUrl                                     │
└──────────────────┬──────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────┐
│ 3. View (views/menu_view.php)                       │
│    - Receives data from controller                  │
│    - Renders ION Networks navigation                │
│    - Displays menu items hierarchically             │
│    - Handles UI interactions (JS)                   │
└──────────────────┬──────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────┐
│ 4. Response                                         │
│    Complete HTML page with ION Networks theme       │
└─────────────────────────────────────────────────────┘
```

## Benefits of This Architecture

### 1. Separation of Concerns
- **Business logic** is isolated in controller
- **Presentation** is isolated in view
- **Data access** uses consistent PDO methods

### 2. Maintainability
- Update UI without touching database code
- Change data structure without modifying presentation
- Easy to debug (clear separation)

### 3. Reusability
- View can be reused with different data sources
- Functions can be reused in other controllers
- Consistent patterns throughout

### 4. Testability
- Business logic can be tested independently
- View can be tested with mock data
- Database queries are centralized

## Example: Adding a New Feature

### Scenario: Add "Featured" Badge to Menu Items

**Step 1: Update Database (Model)**
```sql
ALTER TABLE IONmenu ADD COLUMN is_featured TINYINT(1) DEFAULT 0;
```

**Step 2: Update Controller (index.php)**
```php
function buildMenuTree($pdo, $menuId, $parentId = 0) {
    // ...existing code...
    $menuItem = [
        'id' => $item['id'],
        'label' => $item['label'],
        'url' => $item['url'],
        'target' => $item['target'],
        'classes' => $item['classes'],
        'is_featured' => $item['is_featured'] ?? 0,  // ← Add this
        'children' => []
    ];
    // ...rest of code...
}
```

**Step 3: Update View (views/menu_view.php)**
```php
<a href="<?php echo htmlspecialchars($item['url']); ?>" 
   class="menu-item-link">
    <?php echo htmlspecialchars($item['label']); ?>
    <?php if (!empty($item['is_featured'])): ?>
        <span class="featured-badge">★ Featured</span>
    <?php endif; ?>
</a>
```

**Step 4: Add CSS (menu.css)**
```css
.featured-badge {
    background: var(--ion-gold);
    color: var(--ion-darker);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}
```

Done! The feature is implemented with clear separation of concerns.

## Design Principles Used

1. **DRY (Don't Repeat Yourself)**: Reusable functions for common operations
2. **Single Responsibility**: Each file has one clear purpose
3. **Open/Closed**: Easy to extend without modifying existing code
4. **Separation of Concerns**: Logic, data, and presentation are separated

## Security Considerations

1. **SQL Injection**: All queries use PDO prepared statements
2. **XSS Prevention**: All output uses `htmlspecialchars()`
3. **Error Handling**: Try-catch blocks for database errors
4. **Input Validation**: Form inputs are validated and sanitized

## Performance Optimizations

1. **Single Database Connection**: Reused throughout application
2. **Efficient Queries**: Ordered queries with proper indexing
3. **CSS Variables**: Dynamic theming without JavaScript overhead
4. **Minimal DOM Manipulation**: Efficient JavaScript event handling

## Future Enhancements

Possible improvements maintaining this architecture:

1. **Caching**: Add caching layer in controller
2. **API Endpoints**: Create JSON endpoints using same data functions
3. **Multiple Views**: Create alternative views (JSON, XML, etc.)
4. **Template Engine**: Integrate Twig or similar for more powerful templating
5. **Route Handling**: Add URL routing for better navigation

