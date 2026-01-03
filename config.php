<?php


class Database {
    private $host = 'localhost';
    private $db = 'project_tracker';
    private $user = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->db);
        
        if ($this->conn->connect_error) {
            die('Connection Error: ' . $this->conn->connect_error);
        }
        
        $this->conn->set_charset('utf8mb4');
        return $this->conn;
    }
}

// config/Constants.php
define('JWT_SECRET', 'your-super-secret-key-change-this-in-production');
define('JWT_ALGO', 'HS256');
define('TOKEN_EXPIRY', 3600); // 1 hour

// config/CORS.php - Handle CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>