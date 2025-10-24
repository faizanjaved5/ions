<?php
/**
 * config/database.php
 *
 * Minimal WordPress-independent DB wrapper using PDO.
 * - Loads credentials from config.php in the same directory (expected to return an array):
 *     return [
 *       'host'     => 'localhost',
 *       'dbname'   => 'your_db',
 *       'username' => 'your_user',
 *       'password' => 'your_pass',
 *       'charset'  => 'utf8mb4', // optional
 *     ];
 * - ENV fallbacks: DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET
 * - Exposes IONDatabase::getPDO() for libraries that need raw PDO.
 */

declare(strict_types=1);

class IONDatabase {
    /** @var PDO|null */
    private $pdo = null;

    /** @var string */
    public $last_error = '';

    /** @var string */
    public $last_query = '';

    /** @var int|string */
    public $insert_id = 0;
    
    /** @var int */
    public $num_rows = 0;

    public function __construct(?array $configOverride = null) {
        $this->connect($configOverride);
    }

    public function isConnected(): bool {
        if ($this->pdo === null) return false;
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            error_log('[IONDatabase] Connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return the underlying PDO handle.
     */
    public function getPDO(): PDO {
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('PDO is not initialized.');
        }
        return $this->pdo;
    }

    /**
     * Connect using config.php or ENV as fallback.
     */
    private function connect(?array $configOverride = null): void {
        $host = $dbname = $username = $password = $charset = null;

        try {
            // 1) Config passed in
            if (is_array($configOverride) && !empty($configOverride)) {
                $host     = $configOverride['host']     ?? null;
                $dbname   = $configOverride['dbname']   ?? null;
                $username = $configOverride['username'] ?? null;
                $password = $configOverride['password'] ?? null;
                $charset  = $configOverride['charset']  ?? 'utf8mb4';
            }

            // 2) config.php (same dir) — only if not overridden
            if (!$host || !$dbname || !$username) {
                $config_file = __DIR__ . '/config.php';
                if (is_file($config_file)) {
                    $cfg = require $config_file; // expected: array
                    if (is_array($cfg)) {
                        $host     = $host     ?: ($cfg['host']     ?? null);
                        $dbname   = $dbname   ?: ($cfg['dbname']   ?? null);
                        $username = $username ?: ($cfg['username'] ?? null);
                        $password = $password ?? ($cfg['password'] ?? null);
                        $charset  = $charset  ?: ($cfg['charset']  ?? 'utf8mb4');
                    }
                }
            }

            // 3) ENV fallback
            $host     = $host     ?: getenv('DB_HOST');
            $dbname   = $dbname   ?: getenv('DB_NAME');
            $username = $username ?: getenv('DB_USER');
            $password = $password ?? getenv('DB_PASSWORD'); // allow empty string
            $charset  = $charset  ?: (getenv('DB_CHARSET') ?: 'utf8mb4');

            if (!$host || !$dbname || !$username) {
                throw new RuntimeException('DB credentials are incomplete. Provide host, dbname, username.');
            }

            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->pdo = new PDO($dsn, (string)$username, (string)($password ?? ''), $options);
        } catch (Throwable $e) {
            $this->pdo = null;
            $this->last_error = $e->getMessage();
            error_log(sprintf(
                '[IONDatabase] Connect failed: %s | host=%s dbname=%s user=%s',
                $e->getMessage(),
                $host ?: 'undefined',
                $dbname ?: 'undefined',
                $username ?: 'undefined'
            ));
            throw new RuntimeException('Failed to connect to database.');
        }
    }

    /**
     * WordPress-style prepare that returns a formatted SQL string (not executed).
     * Note: Prefer bound params via execute() where possible.
     */
    public function prepare(string $sql, ...$args): string {
        $this->ensurePDO();
        $this->last_query = $sql;
        $this->last_error = '';

        // Replace WP placeholders with PDO's ?
        $sql = str_replace(['%s', '%d'], '?', $sql);

        if (empty($args)) return $sql;

        $quoted_args = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $quoted_elements = array_map(function ($item) {
                    if (is_string($item))  return $this->pdo->quote($item);
                    if (is_numeric($item)) return (string)$item;
                    if (is_null($item))    return 'NULL';
                    return $this->pdo->quote((string)$item);
                }, $arg);
                $quoted_args[] = implode(',', $quoted_elements);
            } elseif (is_string($arg))  {
                $quoted_args[] = $this->pdo->quote($arg);
            } elseif (is_numeric($arg)) {
                $quoted_args[] = (string)$arg;
            } elseif (is_null($arg))    {
                $quoted_args[] = 'NULL';
            } else {
                $quoted_args[] = $this->pdo->quote((string)$arg);
            }
        }

        $prepared = $sql;
        foreach ($quoted_args as $arg) {
            $prepared = preg_replace('/\?/', $arg, $prepared, 1);
        }
        return $prepared;
    }

    /**
     * Execute a prepared statement with bound params.
     */
    private function execute(string $sql, ...$args) {
        $this->ensurePDO();
        $this->last_query = $sql;
        $this->last_error = '';

        $sql = str_replace(['%s', '%d'], '?', $sql);

        try {
            $stmt = $this->pdo->prepare($sql);

            // Flatten arrays (for IN (...) style usage)
            $flat_args = [];
            foreach ($args as $arg) {
                if (is_array($arg)) $flat_args = array_merge($flat_args, $arg);
                else $flat_args[] = $arg;
            }

            $stmt->execute($flat_args);
            return $stmt;
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log('[IONDatabase] SQL Execute Error: ' . $e->getMessage() . ' | SQL=' . $sql);
            return false;
        }
    }

    // WP-like query helpers

    public function get_results(string $sql, ...$args): array {
        try {
            $stmt = $this->execute($sql, ...$args);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : [];
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            return [];
        }
    }

    public function get_var(string $sql, ...$args) {
        try {
            $stmt = $this->execute($sql, ...$args);
            return $stmt ? $stmt->fetchColumn() : null;
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    public function get_row(string $sql, ...$args) {
        try {
            $stmt = $this->execute($sql, ...$args);
            return $stmt ? $stmt->fetch(PDO::FETCH_OBJ) : null;
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    public function get_col(string $sql, ...$args): array {
        $stmt = $this->execute($sql, ...$args);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    public function insert(string $table, array $data): bool {
        $this->ensurePDO();
        if (empty($data)) return false;
        $cols = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES ({$placeholders})";
        try {
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute(array_values($data));
            $this->insert_id = $this->pdo->lastInsertId();
            return $ok;
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log('[IONDatabase] Insert Error: ' . $e->getMessage());
            return false;
        }
    }

    public function update(string $table, array $data, array $where): bool {
        $this->ensurePDO();
        if (empty($data) || empty($where)) return false;

        $set = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($data)));
        $w   = implode(' AND ', array_map(fn($c) => "{$c} = ?", array_keys($where)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$w}";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array_merge(array_values($data), array_values($where)));
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log('[IONDatabase] Update Error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $table, array $where): bool {
        $this->ensurePDO();
        if (empty($where)) return false;

        $w   = implode(' AND ', array_map(fn($c) => "{$c} = ?", array_keys($where)));
        $sql = "DELETE FROM {$table} WHERE {$w}";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array_values($where));
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log('[IONDatabase] Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    public function esc_like(string $text): string {
        return addcslashes($text, '%_\\');
    }

    public function suppress_errors(bool $suppress = true): bool {
        // no-op kept for API parity
        return false;
    }

    /**
     * Direct query with optional parameter binding support.
     * If parameters are provided, uses prepared statements.
     * Otherwise, executes raw SQL (for e.g., FULLTEXT or administrative ops).
     */
    public function query(string $sql, ...$args) {
        $this->ensurePDO();
        
        // If parameters provided, use prepared statement
        if (!empty($args)) {
            try {
                $stmt = $this->execute($sql, ...$args);
                if ($stmt === false) {
                    return false;
                }
                
                // Set insert_id for INSERT queries
                $this->insert_id = $this->pdo->lastInsertId();
                $this->num_rows = $stmt->rowCount();
                
                return $stmt;
            } catch (Throwable $e) {
                $this->last_error = $e->getMessage();
                error_log('[IONDatabase] Query Error (prepared): ' . $e->getMessage());
                return false;
            }
        }
        
        // Raw query (no parameters)
        try {
            $result = $this->pdo->query($sql);
            if ($result !== false) {
                $this->insert_id = $this->pdo->lastInsertId();
                $this->num_rows = $result->rowCount();
            }
            return $result;
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log('[IONDatabase] Query Error: ' . $e->getMessage());
            return false;
        }
    }

    private function ensurePDO(): void {
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('Database connection not initialized.');
        }
    }
}

/**
 * Lightweight WP-like helpers (guarded to avoid redeclare if WP is loaded elsewhere)
 */
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim(strip_tags($str)) : $str;
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($data): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('current_time')) {
    function current_time($format) {
        if ($format === 'mysql') return date('Y-m-d H:i:s');
        return date($format);
    }
}

// File-based transients
if (!function_exists('get_transient')) {
    function get_transient(string $key) {
        $cacheDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? sys_get_temp_dir(), '/').'/cache';
        $file = $cacheDir . '/' . md5($key) . '.cache';
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $data = $raw !== false ? @unserialize($raw) : false;
            if (is_array($data) && isset($data['expires'], $data['value'])) {
                if ($data['expires'] > time()) return $data['value'];
                @unlink($file);
            }
        }
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration): void {
        $cacheDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? sys_get_temp_dir(), '/').'/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
        $file = $cacheDir . '/' . md5($key) . '.cache';
        $payload = ['value' => $value, 'expires' => time() + max(0, $expiration)];
        @file_put_contents($file, serialize($payload));
    }
}

// Constants (guarded)
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('WEEK_IN_SECONDS')) define('WEEK_IN_SECONDS', 604800);

// Initialize global instance for convenience
global $db;
if (!isset($db) || !($db instanceof IONDatabase)) {
    $db = new IONDatabase(); // uses config.php or ENV
}
?>