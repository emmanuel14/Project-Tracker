<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


ob_start();

$response = ['error' => 'Unknown error'];
$http_code = 500;

try {
  
    $db_file = __DIR__ . '/../config/Database.php';
    $const_file = __DIR__ . '/../config/Constants.php';
    $jwt_file = __DIR__ . '/../config/JWT.php';
    
    if (!file_exists($db_file)) {
        throw new Exception('Database.php not found at: ' . $db_file);
    }
    if (!file_exists($const_file)) {
        throw new Exception('Constants.php not found at: ' . $const_file);
    }
    if (!file_exists($jwt_file)) {
        throw new Exception('JWT.php not found at: ' . $jwt_file);
    }
    

    require_once $db_file;
    require_once $const_file;
    require_once $jwt_file;
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Determine action
    $action = '';
    if (strpos($_SERVER['REQUEST_URI'], 'register') !== false || (isset($data) && isset($data['email']))) {
        $action = 'register';
    } elseif (strpos($_SERVER['REQUEST_URI'], 'login') !== false || (isset($data) && !isset($data['email']))) {
        $action = 'login';
    }
    
    if ($method !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    if (empty($action)) {
        throw new Exception('Cannot determine action. Check if request has username/email and password.');
    }
    
    if ($action === 'register') {
        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            throw new Exception('Missing fields. Required: username, email, password');
        }
        
        $username = trim($data['username']);
        $email = trim($data['email']);
        $password = $data['password'];
        
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception('Fields cannot be empty');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }
        
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Username or email already exists');
        }
        $stmt->close();
        
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $role = 'user';
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Insert prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('ssss', $username, $email, $hashed, $role);
        
        if (!$stmt->execute()) {
            throw new Exception('Insert failed: ' . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
        $response = ['success' => true, 'message' => 'User registered successfully'];
        $http_code = 201;
        
    } elseif ($action === 'login') {
        if (!isset($data['username']) || !isset($data['password'])) {
            throw new Exception('Missing fields. Required: username, password');
        }
        
        $username = trim($data['username']);
        $password = $data['password'];
        
        if (empty($username) || empty($password)) {
            throw new Exception('Fields cannot be empty');
        }
        
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception('Select prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            throw new Exception('Invalid credentials');
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Invalid credentials');
        }
        
        $jwt = new JWT(JWT_SECRET);
        $token = $jwt->encode([
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);
        
        $conn->close();
        
        $response = [
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
        $http_code = 200;
    }
    
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
    $http_code = 400;
}

// Clear any buffered output
ob_end_clean();

// Send response
http_response_code($http_code);
echo json_encode($response);
exit();
?>