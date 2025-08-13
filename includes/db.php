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
                // Add error reporting for debugging
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
                
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
                // Log the error instead of displaying it in production
                error_log("Database connection failed: " . $e->getMessage());
                
                // For debugging, you can temporarily show the error
                die("Database connection failed. Check error logs.");
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