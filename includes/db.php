<?php
// Enhanced db.php with better error handling
class Database {
    private $host = 'localhost';
    private $dbname = 'u345095192_dailycalendar';
    private $username = 'u345095192_dailycalendar';
    private $password = 'Daily@788';
    private $conn;

    public function connect() {
        if ($this->conn === null) {
            try {
                // Disable error display to prevent HTML output in API responses
                ini_set('display_errors', 0);
                error_reporting(0);
                
                $this->conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                
                // Test the connection
                $this->conn->query("SELECT 1");
                
            } catch (PDOException $e) {
                // Log the error instead of displaying it
                error_log("Database connection failed: " . $e->getMessage());
                
                // Throw exception to be caught by API handlers
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
    
    public function testConnection() {
        try {
            $conn = $this->connect();
            echo "Database connection successful!";
            return true;
        } catch (Exception $e) {
            echo "Database connection failed: " . $e->getMessage();
            return false;
        }
    }
}

$database = new Database();
$pdo = $database->connect();

// Uncomment this line temporarily to test your connection
// $database->testConnection();
?>