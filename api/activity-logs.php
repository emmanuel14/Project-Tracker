<?php
// api/activity-logs.php - Fixed Activity Logs Endpoint
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/Headers.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Constants.php';
require_once __DIR__ . '/../config/JWT.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed. Only GET is supported']);
        exit();
    }
    
    $user = verifyToken();
    
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can view activity logs']);
        exit();
    }
    
    $db = new Database();
    $conn = $db->connect();
    
    $query = "SELECT 
        a.id, 
        a.action, 
        a.description, 
        a.created_at,
        u.username,
        p.title as project_title
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN projects p ON a.project_id = p.id 
    ORDER BY a.created_at DESC 
    LIMIT 100";
    
    $result = $conn->query($query);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    http_response_code(200);
    echo json_encode(['data' => $logs]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>