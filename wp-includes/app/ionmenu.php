<?php
require_once '../config/database.php'; // Adjust path as needed

global $db;

// Create tables if not exists
$db->query("
    CREATE TABLE IF NOT EXISTS ion_menus (
        id INT PRIMARY KEY,
        name VARCHAR(255)
    )
");
$db->query("
    CREATE TABLE IF NOT EXISTS ion_menu_items (
        id INT PRIMARY KEY,
        menu_id INT,
        label VARCHAR(255),
        object_id INT,
        object VARCHAR(50),
        url VARCHAR(255),
        target VARCHAR(50),
        classes TEXT,
        parent INT DEFAULT 0,
        position INT DEFAULT 0
    )
");

// Handle import from menu.txt
if (isset($_GET['import'])) {
    $json_content = file_get_contents('./ionmenu.txt');
    $data = json_decode($json_content, true);

    // Truncate tables
    $db->query('TRUNCATE TABLE ion_menus');
    $db->query('TRUNCATE TABLE ion_menu_items');

    // Insert menus
    foreach ($data['menus'] as $menu) {
        $db->insert('ion_menus', [
            'id' => $menu['id'],
            'name' => $menu['name']
        ]);
        
        // Insert items recursively
        insert_items($menu['items'], 0, $menu['id'], 0);
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function insert_items($items, $parent, $menu_id, $position = 0) {
    global $db;
    foreach ($items as $item) {
        $classes = isset($item['classes'][0]) ? $item['classes'][0] : '';
        $db->insert('ion_menu_items', [
            'id' => $item['id'],
            'menu_id' => $menu_id,
            'label' => $item['label'],
            'object_id' => $item['object_id'],
            'object' => $item['object'],
            'url' => $item['url'],
            'target' => $item['target'],
            'classes' => $classes,
            'parent' => $parent,
            'position' => $position++
        ]);
        if (isset($item['children']) && !empty($item['children'])) {
            insert_items($item['children'], $item['id'], $menu_id, 0);
        }
    }
}

// Handle save hierarchy from drag and drop
if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $menu_id = $_POST['menu_id'];
    $json = json_decode($_POST['json'], true);
    save_nested($json, 0, $menu_id, 0);
    echo 'Hierarchy saved.';
    exit;
}

function save_nested($items, $parent, $menu_id, $position = 0) {
    global $db;
    foreach ($items as $item) {
        $db->update('ion_menu_items', [
            'parent' => $parent,
            'position' => $position
        ], [
            'id' => $item['id'],
            'menu_id' => $menu_id
        ]);
        $position++;
        if (isset($item['children'])) {
            save_nested($item['children'], $item['id'], $menu_id, 0);
        }
    }
}

// Build tree for JSON views
function build_tree($menu_id, $parent = 0) {
    global $db;
    $items = $db->get_results(
        'SELECT * FROM ion_menu_items WHERE menu_id = %d AND parent = %d ORDER BY position',
        $menu_id,
        $parent
    );
    foreach ($items as &$item) {
        $item = (array)$item; // Convert stdClass to array
        $item['children'] = build_tree($menu_id, $item['id']);
        if (empty($item['children'])) unset($item['children']);
    }
    return $items;
}

// Get menu name
function get_menu_name($menu_id) {
    global $db;
    return $db->get_var('SELECT name FROM ion_menus WHERE id = %d', $menu_id);
}

// Recursive show for editor view
function show_nested($menu_id, $parent = 0) {
    global $db;
    $search = isset($_GET['search']) ? '%' . $db->esc_like($_GET['search']) . '%' : null;
    $where = '';
    $args = [$menu_id, $parent];
    if ($search) {
        $where = ' AND (label LIKE %s OR url LIKE %s OR id LIKE %s)';
        $args[] = $search;
        $args[] = $search;
        $args[] = $search;
    }
    $items = $db->get_results("SELECT * FROM ion_menu_items WHERE menu_id = %d AND parent = %d $where ORDER BY position", ...$args);
    if (empty($items)) return;
    echo "<ol class='dd-list'>";
    foreach ($items as $item) {
        echo "<li class='dd-item' data-id='{$item->id}'>";
        echo "<div class='dd-handle'>{$item->label} (ID: {$item->id}) - URL: {$item->url}</div>";
        show_nested($menu_id, $item->id);
        echo "</li>";
    }
    echo "</ol>";
}

// Main page logic
$view = isset($_GET['view']) ? $_GET['view'] : 'editor';
$menu_id = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 110; // Default to first menu
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get all menus for selector
$menus = $db->get_results('SELECT * FROM ion_menus');
$no_menus = empty($menus);
if ($no_menus) {
    $menu_id = 0; // Invalid
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ION Menu Directory</title>
    <!-- Assume Nestable is downloaded and placed in same directory -->
    <link rel="stylesheet" href="nestable.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="nestable.js"></script>
    <style>
        /* Basic styles */
        body { font-family: Arial, sans-serif; }
        .header { background: purple; color: white; padding: 10px; text-align: center; }
        .search-bar { margin: 10px; }
        .buttons { margin: 10px; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        #feedback { border: 1px solid #eaeaea; padding: 10px; margin: 15px; }
        .message { margin: 10px; color: red; }
    </style>
</head>
<body>
    <div class="header">ION Menu Directory</div>
    <?php if ($no_menus): ?>
        <div class="message">No menus found in the database. Please import the data from the TXT file.</div>
    <?php else: ?>
    <form method="get">
        <select name="menu_id" onchange="this.form.submit()">
            <?php foreach ($menus as $m): ?>
                <option value="<?php echo $m->id; ?>" <?php if ($m->id == $menu_id) echo 'selected'; ?>><?php echo $m->name; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Search by ID, label, or URL part" value="<?php echo htmlspecialchars($search); ?>" class="search-bar">
        <button type="submit">Search</button>
    </form>
    <?php endif; ?>
    <div class="buttons">
        <a href="?import=1"><button>Import from TXT</button></a>
        <?php if (!$no_menus): ?>
        <a href="?menu_id=<?php echo $menu_id; ?>&view=editor&search=<?php echo urlencode($search); ?>"><button>Editor (Drag & Drop)</button></a>
        <a href="?menu_id=<?php echo $menu_id; ?>&view=list&search=<?php echo urlencode($search); ?>"><button>List (Tabular)</button></a>
        <a href="?menu_id=<?php echo $menu_id; ?>&view=json&search=<?php echo urlencode($search); ?>"><button>Expanded JSON</button></a>
        <a href="?menu_id=<?php echo $menu_id; ?>&view=compressed_json&search=<?php echo urlencode($search); ?>"><button>Compressed JSON</button></a>
        <?php endif; ?>
    </div>

    <?php if (!$no_menus): ?>
    <?php if ($view === 'editor'): ?>
        <div class="dd" id="nestable">
            <?php show_nested($menu_id); ?>
        </div>
        <div id="feedback"></div>
        <script>
            $(document).ready(function() {
                $('#nestable').nestable({ group: 1 }).on('change', function(e) {
                    var list = e.length ? e : $(e.target);
                    if (window.JSON) {
                        var json = JSON.stringify(list.nestable('serialize'));
                        $.post(window.location.href, { action: 'save', menu_id: <?php echo $menu_id; ?>, json: json }, function(response) {
                            $('#feedback').html(response);
                        });
                    }
                });
            });
        </script>
    <?php elseif ($view === 'list'): ?>
        <table>
            <tr><th>ID</th><th>Label</th><th>Parent</th><th>URL</th><th>Position</th><th>Classes</th></tr>
            <?php
            $where = '';
            $args = [$menu_id];
            if ($search) {
                $search_like = '%' . $db->esc_like($search) . '%';
                $where = ' AND (label LIKE %s OR url LIKE %s OR id LIKE %s)';
                $args[] = $search_like;
                $args[] = $search_like;
                $args[] = $search_like;
            }
            $items = $db->get_results("SELECT * FROM ion_menu_items WHERE menu_id = %d $where ORDER BY parent, position", ...$args);
            foreach ($items as $item) {
                echo "<tr><td>{$item->id}</td><td>{$item->label}</td><td>{$item->parent}</td><td>{$item->url}</td><td>{$item->position}</td><td>{$item->classes}</td></tr>";
            }
            ?>
        </table>
    <?php elseif ($view === 'json'): ?>
        <pre>
            <?php
            // For search in JSON, we build full tree and output (search not filtered in hierarchy)
            $tree = build_tree($menu_id);
            $output = ['id' => $menu_id, 'name' => get_menu_name($menu_id), 'items' => $tree];
            echo json_encode($output, JSON_PRETTY_PRINT);
            ?>
        </pre>
    <?php elseif ($view === 'compressed_json'): ?>
        <?php
        $tree = build_tree($menu_id);
        $output = ['id' => $menu_id, 'name' => get_menu_name($menu_id), 'items' => $tree];
        echo json_encode($output);
        ?>
    <?php endif; ?>
    <?php endif; ?>
</body>
</html>