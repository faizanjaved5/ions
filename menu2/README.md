# ION Menu Display System

A simple PHP implementation to display hierarchical menu items from the ION database with ION Networks themed navigation.

## Features

- 📋 Dynamic navigation bar populated from database
- 🎯 Traditional dropdown/mega menu on hover
- 🌳 Unlimited levels of menu nesting (parent → child → grandchild)
- 🎨 ION Networks themed UI (dark/light mode)
- ♻️ Recursive rendering of nested menu items
- 🔒 Secure database queries using PDO prepared statements
- 📱 Fully responsive design with mobile menu
- 🔍 Integrated search functionality
- 🎭 MVC pattern with separate view layer

## Files Structure

```
ion/
├── index.php           # Main controller - handles data fetching and business logic
├── config.php          # Database configuration
├── menu.css            # ION Networks themed styles
├── views/
│   └── menu_view.php   # Presentation layer - displays the menu UI
├── omar/               # Original menu implementation (preserved)
└── README.md
```

## Key Files

- **`index.php`** - Main controller that fetches menu data and loads the view
- **`config.php`** - Database configuration file
- **`views/menu_view.php`** - View template with ION Networks UI design
- **`menu.css`** - Complete styling for ION Networks theme

## Setup Instructions

### 1. Configure Database Connection

Edit `config.php` and update with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'ion');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 2. Database Structure

### Three-Level Menu Hierarchy

**Level 1: Parent Menus** (`ion_menus` table):
- Displayed in navigation bar
- Fields: `id`, `name`
- Example: "ION Local", "ION Networks"

**Level 2: Child Items** (`IONmenu` table, `parent=0`):
- Displayed in dropdown mega menu
- Top-level items under parent menu

**Level 3: Grandchild Items** (`IONmenu` table, `parent=item_id`):
- Nested items in mega menu
- Can nest infinitely

### IONmenu Table Fields:
- `id` - Primary key
- `menu_id` - Foreign key to ion_menus.id
- `parent` - 0 for top-level, else parent item ID
- `label` - Display text
- `url` - Link URL
- `target` - Link target (_self, _blank, etc.)
- `classes` - CSS classes
- `position` - Display order
- `object_id`, `object` - Reference fields

### 3. Access the Menu

Open your browser and navigate to:
```
http://localhost/ion/
```

or

```
http://localhost/ion/index.php
```

## How It Works

### Architecture (MVC Pattern)

**Controller (index.php)**:
1. Connects to database using PDO
2. Fetches parent menus from `ion_menus` table
3. Builds hierarchical menu tree with children
4. Prepares data and passes to view

**View (views/menu_view.php)**:
1. Receives menu data from controller
2. Renders ION Networks themed navigation header
3. **Displays parent menu items in navigation bar**
4. **Shows children in dropdown/mega menu on hover**
5. Handles search, mobile menu, and theme toggle UI

### Data Flow

1. **Fetch Parent Menus**: Retrieves all menu groups from `ion_menus` table
2. **Build Menu Tree**: For each menu, recursively builds complete menu tree structure
3. **Populate Navigation**: Parent menu items appear in navigation bar
4. **Hover Interaction**: Child menus appear in mega menu dropdown on hover
5. **Pass to View**: Sends structured data array to view template
6. **Render UI**: View displays data using ION Networks design system

### Navigation Behavior

**Desktop**:
1. Navigation bar shows parent menu names from `ion_menus` table
2. Hover over parent menu → Dropdown shows child items (parent=0)
3. Click to expand nested items → Shows grandchildren

**Mobile**:
- Tap hamburger icon for slide-out menu
- Tap items with chevron to expand/collapse
- Full menu hierarchy displayed

**Other Features**:
- **Search**: Click "SEARCH ION" to expand search bar
- **Theme**: Click sun/moon icon to toggle dark/light mode

## Functions (index.php)

- `getDbConnection()` - Establishes PDO database connection
- `getParentMenus($pdo)` - Fetches all parent menus from ion_menus
- `getMenuItems($pdo, $menuId, $parentId)` - Fetches menu items for specific menu and parent
- `buildMenuTree($pdo, $menuId, $parentId)` - Recursively builds complete menu tree with children
- `renderMenuChildren($children, $level)` - Helper function for rendering nested menu items in view
- `renderMobileMenuItems($items, $level, $parentId)` - Renders mobile menu with expandable sub-items

## Customization

### Styling

All CSS is in `menu.css` file. Key customization options:

**Color Scheme** (CSS variables in `:root`):
- `--ion-gold: #b28254` - Gold/bronze accent color
- `--ion-blue: #a4b3d0` - Blue text color
- `--ion-dark: #2a2f3a` - Dark background
- `--ion-darker: #161821` - Darker background

**Light Mode**: Automatically switches when user toggles theme (stored in localStorage)

**Responsive Design**:
- Desktop: Full navigation with mega menu
- Tablet: Simplified navigation
- Mobile: Hamburger menu with slide-out panel

### Logo

Change logo in `index.php`:
```php
$logoUrl = '/path/to/your/logo.png';
```

Or modify in `views/menu_view.php` for direct control.

### Menu Ordering

Menu items are ordered by:
1. `position` field (ascending)
2. `id` field (ascending)

### View Customization

Edit `views/menu_view.php` to customize:
- Navigation structure
- Page layout
- Content presentation
- Search functionality

## Security

- Uses PDO prepared statements to prevent SQL injection
- Escapes all output with `htmlspecialchars()`
- Validates database connection
- Safe error handling

## Requirements

- PHP 7.0 or higher
- MySQL/MariaDB
- PDO extension enabled

## Troubleshooting

**Connection Error**: Check database credentials in `config.php`

**No Menus Displayed**: Verify data exists in `ion_menus` table

**No Items Displayed**: Check that `IONmenu` table has items with matching `menu_id`

**Permission Error**: Ensure PHP has read access to files

## UI Features

### ION Networks Design

The view includes the complete ION Networks navigation design:

- **Dark/Light Theme Toggle**: Click the sun/moon icon to switch themes
- **Search Functionality**: Expandable search bar in navigation
- **Mobile Menu**: Full-featured slide-out menu for mobile devices
- **Responsive Logo**: ION Networks branded logo
- **Smooth Animations**: Professional transitions and hover effects
- **Bebas Neue Font**: Authentic ION Networks typography
- **Gold Accent Color**: Signature #b28254 gold/bronze throughout

### Navigation Features

- **Desktop Navigation**: Full horizontal menu bar with hover effects
- **Mobile Navigation**: Hamburger menu with slide-out panel
- **Search Integration**: Inline search that expands on click
- **Theme Persistence**: Theme choice saved to localStorage
- **Keyboard Navigation**: Full accessibility support with Escape key
- **Responsive Design**: Optimized for all screen sizes

## Development Notes

### MVC Architecture

This implementation follows MVC (Model-View-Controller) pattern:

- **Model**: Database queries and data structures (in index.php)
- **View**: Presentation layer (views/menu_view.php)
- **Controller**: Request handling and data preparation (index.php)

This separation allows for:
- Easy UI updates without touching business logic
- Reusable view templates
- Better code organization
- Simplified testing and maintenance

### Passing Data to View

The controller prepares data and passes it to the view:

```php
// In index.php
$menus = [/* structured menu data */];
$pageTitle = 'ION Menu System';
$logoUrl = '/path/to/logo.png';

// Load view with access to these variables
require_once __DIR__ . '/views/menu_view.php';
```

The view then accesses these variables directly:
```php
// In menu_view.php
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<?php foreach ($menus as $menu): ?>
    <!-- Render menu -->
<?php endforeach; ?>
```

