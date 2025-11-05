<?php
/**
 * Database Connection Utility
 * Handles PDO database connection with error handling and configuration
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403);
    exit('Direct access denied');
}

// Load environment variables
function loadEnvironment() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load environment variables
loadEnvironment();

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'zaphira_attendance');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    private $error;

    /**
     * Private constructor to prevent direct creation
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_PERSISTENT => false
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            $this->error = $e->getMessage();

            // Log error for debugging
            error_log("Database Connection Error: " . $this->error);

            // Return appropriate error response
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed',
                'error_code' => 'DB_CONNECTION_ERROR'
            ]);
            exit;
        }
    }

    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Get last error
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Check if connected to database
     */
    public function isConnected() {
        return $this->connection !== null;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollback();
    }

    /**
     * Execute prepared statement
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Database Query Error: " . $this->error . " SQL: " . $sql);
            return false;
        }
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $stmt = $this->execute("SHOW TABLES LIKE ?", [$tableName]);
        return $stmt && $stmt->rowCount() > 0;
    }

    /**
     * Get table info
     */
    public function getTableInfo($tableName) {
        $stmt = $this->execute("DESCRIBE `$tableName`");
        return $stmt ? $stmt->fetchAll() : false;
    }
}

// Helper function to get database instance
function getDB() {
    return Database::getInstance();
}

// Helper function to execute queries
function dbQuery($sql, $params = []) {
    $db = getDB();
    return $db->execute($sql, $params);
}

// Helper function to fetch single row
function dbFetch($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

// Helper function to fetch multiple rows
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : false;
}

// Helper function to get last insert ID
function dbLastInsertId() {
    $db = getDB();
    return $db->lastInsertId();
}

?>