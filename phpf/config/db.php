<?php
// db.php - Enhanced with connection settings
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $host = "localhost";
        $db   = "muhasba1";
        $user = "root";
        $pass = "";
        $port = "3306";
        $charset = "utf8mb4";
        
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false, // Don't use persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
            
            // Set session variables for larger packets
            $this->connection->exec("SET SESSION wait_timeout = 600");
            $this->connection->exec("SET SESSION interactive_timeout = 600");
            
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return $stmt->fetchColumn() == 1;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Create global $pdo for backward compatibility
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Test connection
    if (!$db->testConnection()) {
        error_log("Database connection test failed");
    }
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>