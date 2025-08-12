<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'u345095192_dailycalendar';
    private $username = 'u345095192_dailycalendar';
    private $password = 'Daily@788';
    private $conn;

    public function connect() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
}

$database = new Database();
$pdo = $database->connect();
?>