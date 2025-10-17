<?php
// Database configuration - replace WordPress dependency
class IONDatabase {
    private $pdo;
    public $last_error = '';
    public $last_query = '';
    public $insert_id = 0;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            // Load database configuration
            $config_file = __DIR__ . '/config.php';
            if (!file_exists($config_file)) {
                throw new Exception('Database configuration file not found. Please copy database-config.sample.php to database-config.php and update with your credentials.');
            }
            
            $config = require $config_file;
            $host     = $config['host'];
            $dbname   = $config['dbname'];
            $username = $config['username'];
            $password = $config['password'];
            $charset  = $config['charset'] ?? 'utf8mb4';
            
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed');
        }
    }
    
    // Replace $wpdb->prepare()
    public function prepare($sql, ...$args) {
        $this->last_query = $sql;
        
        // Convert WordPress %s, %d format to PDO format
        $sql = str_replace('%s', '?', $sql);
        $sql = str_replace('%d', '?', $sql);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!empty($args)) {
                $stmt->execute($args);
            }
            return $stmt;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            error_log('SQL Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Replace $wpdb->get_results()
    public function get_results($sql, ...$args) {
        if (!empty($args)) {
            $stmt = $this->prepare($sql, ...$args);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
        return [];
    }
    
    // Replace $wpdb->get_var()
    public function get_var($sql, ...$args) {
        if (!empty($args)) {
            $stmt = $this->prepare($sql, ...$args);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        
        if ($stmt) {
            return $stmt->fetchColumn();
        }
        return null;
    }
    
    // Replace $wpdb->get_row()
    public function get_row($sql, ...$args) {
        if (!empty($args)) {
            $stmt = $this->prepare($sql, ...$args);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_OBJ);
        }
        return null;
    }
    
    // Replace $wpdb->get_col()
    public function get_col($sql, ...$args) {
        if (!empty($args)) {
            $stmt = $this->prepare($sql, ...$args);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        return [];
    }
    
    // Replace $wpdb->insert()
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));
            $this->insert_id = $this->pdo->lastInsertId();
            return $result;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            error_log('Insert Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Replace $wpdb->update()
    public function update($table, $data, $where) {
        $set_clauses = array_map(function($col) { return "{$col} = ?"; }, array_keys($data));
        $where_clauses = array_map(function($col) { return "{$col} = ?"; }, array_keys($where));
        
        $sql = "UPDATE {$table} SET " . implode(', ', $set_clauses) . " WHERE " . implode(' AND ', $where_clauses);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array_merge(array_values($data), array_values($where)));
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            error_log('Update Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Replace $wpdb->delete()
    public function delete($table, $where) {
        $where_clauses = array_map(function($col) { return "{$col} = ?"; }, array_keys($where));
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where_clauses);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array_values($where));
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            error_log('Delete Error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Replace $wpdb->esc_like()
    public function esc_like($text) {
        return addcslashes($text, '%_\\');
    }
    
    // Replace $wpdb->suppress_errors()
    public function suppress_errors($suppress = true) {
        // For now, just return the current setting
        return false;
    }
}

// Helper functions to replace WordPress functions
function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function wp_send_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function current_time($format) {
    if ($format === 'mysql') {
        return date('Y-m-d H:i:s');
    }
    return date($format);
}

// Simple transient replacement using file cache
function get_transient($key) {
    $file = $_SERVER['DOCUMENT_ROOT'] . '/cache/' . md5($key) . '.cache';
    if (file_exists($file)) {
        $data = unserialize(file_get_contents($file));
        if ($data['expires'] > time()) {
            return $data['value'];
        } else {
            unlink($file);
        }
    }
    return false;
}

function set_transient($key, $value, $expiration) {
    $cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/cache/';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $file = $cache_dir . md5($key) . '.cache';
    $data = [
        'value' => $value,
        'expires' => time() + $expiration
    ];
    file_put_contents($file, serialize($data));
}

// Constants
define('HOUR_IN_SECONDS', 3600);
define('WEEK_IN_SECONDS', 604800);

// Initialize database connection
global $db;
$db = new IONDatabase(); 