<?php
// api/projects.php - Complete Projects CRUD with debugging
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
    // Verify config files exist
    $db_file = __DIR__ . '/../config/Database.php';
    $const_file = __DIR__ . '/../config/Constants.php';
    $jwt_file = __DIR__ . '/../config/JWT.php';
    
    if (!file_exists($db_file)) {
        throw new Exception('Database.php not found at: ' . $db_file);
    }
    if (!file_exists($const_file)) {
        throw new Exception('Constants.php not found');
    }
    if (!file_exists($jwt_file)) {
        throw new Exception('JWT.php not found');
    }
    
    // Include config files
    require_once $db_file;
    require_once $const_file;
    require_once $jwt_file;
    
    // Verify user is authenticated
    $user = verifyToken();
    
    // Connect to database
    $db = new Database();
    $conn = $db->connect();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $project_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    // Route requests
    if ($method === 'GET') {
        if ($project_id) {
            handleGetProject($conn, $user, $project_id);
        } else {
            handleGetAllProjects($conn, $user);
        }
    } elseif ($method === 'POST') {
        handleCreateProject($conn, $user);
    } elseif ($method === 'PUT') {
        if (!$project_id) {
            throw new Exception('Project ID required for PUT');
        }
        handleUpdateProject($conn, $user, $project_id);
    } elseif ($method === 'DELETE') {
        if (!$project_id) {
            throw new Exception('Project ID required for DELETE');
        }
        handleDeleteProject($conn, $user, $project_id);
    } else {
        throw new Exception('Method not allowed');
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
    $http_code = 400;
}

// Clear buffer and send response
ob_end_clean();
http_response_code($http_code);
echo json_encode($response);
exit();

// ============================================
// HANDLER FUNCTIONS
// ============================================

function handleGetAllProjects($conn, $user) {
    global $response, $http_code;
    
    try {
        if ($user['role'] === 'admin') {
            $query = "SELECT p.id, p.title, p.summary, p.status, p.created_by, p.date_created, u.username 
                      FROM projects p 
                      JOIN users u ON p.created_by = u.id 
                      ORDER BY p.date_created DESC";
        } else {
            $userId = (int)$user['user_id'];
            $query = "SELECT p.id, p.title, p.summary, p.status, p.created_by, p.date_created, u.username 
                      FROM projects p 
                      JOIN users u ON p.created_by = u.id 
                      WHERE p.created_by = $userId 
                      ORDER BY p.date_created DESC";
        }
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        
        $response = ['success' => true, 'data' => $projects];
        $http_code = 200;
        
    } catch (Exception $e) {
        throw $e;
    }
}

function handleGetProject($conn, $user, $project_id) {
    global $response, $http_code;
    
    try {
        $stmt = $conn->prepare("SELECT p.id, p.title, p.summary, p.status, p.created_by, p.date_created, u.username 
                                FROM projects p 
                                JOIN users u ON p.created_by = u.id 
                                WHERE p.id = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Project not found');
        }
        
        $project = $result->fetch_assoc();
        $stmt->close();
        
        if ($user['role'] !== 'admin' && $project['created_by'] != $user['user_id']) {
            throw new Exception('Unauthorized');
        }
        
        $response = ['success' => true, 'data' => $project];
        $http_code = 200;
        
    } catch (Exception $e) {
        throw $e;
    }
}

function handleCreateProject($conn, $user) {
    global $response, $http_code;
    
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!isset($data['title']) || !isset($data['summary'])) {
            throw new Exception('Missing required fields: title, summary');
        }
        
        $title = trim($data['title']);
        $summary = trim($data['summary']);
        $status = isset($data['status']) ? $data['status'] : 'Pending';
        $user_id = (int)$user['user_id'];
        
        if (empty($title) || empty($summary)) {
            throw new Exception('Title and summary cannot be empty');
        }
        
        $stmt = $conn->prepare("INSERT INTO projects (title, summary, status, created_by) VALUES (?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('sssi', $title, $summary, $status, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Insert failed: ' . $stmt->error);
        }
        
        $project_id = $conn->insert_id;
        
        // Log activity
        logActivity($conn, $user_id, 'Project created', $project_id, "Project '$title' created");
        
        $stmt->close();
        
        $response = ['success' => true, 'message' => 'Project created', 'project_id' => $project_id];
        $http_code = 201;
        
    } catch (Exception $e) {
        throw $e;
    }
}

function handleUpdateProject($conn, $user, $project_id) {
    global $response, $http_code;
    
    try {
        // Get current project
        $stmt = $conn->prepare("SELECT created_by FROM projects WHERE id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Project not found');
        }
        
        $project = $result->fetch_assoc();
        $stmt->close();
        
        // Check authorization
        if ($user['role'] !== 'admin' && $project['created_by'] != $user['user_id']) {
            throw new Exception('Unauthorized');
        }
        
        // Get update data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        $title = isset($data['title']) ? trim($data['title']) : null;
        $summary = isset($data['summary']) ? trim($data['summary']) : null;
        $status = isset($data['status']) ? $data['status'] : null;
        
        if (!$title && !$summary && !$status) {
            throw new Exception('No fields to update');
        }
        
        // Build dynamic update query
        $updates = [];
        $params = [];
        $types = '';
        
        if ($title) {
            $updates[] = 'title = ?';
            $params[] = $title;
            $types .= 's';
        }
        if ($summary) {
            $updates[] = 'summary = ?';
            $params[] = $summary;
            $types .= 's';
        }
        if ($status) {
            $updates[] = 'status = ?';
            $params[] = $status;
            $types .= 's';
        }
        
        $params[] = $project_id;
        $types .= 'i';
        
        $query = "UPDATE projects SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Update failed: ' . $stmt->error);
        }
        
        logActivity($conn, $user['user_id'], 'Project updated', $project_id, "Project updated");
        
        $stmt->close();
        
        $response = ['success' => true, 'message' => 'Project updated'];
        $http_code = 200;
        
    } catch (Exception $e) {
        throw $e;
    }
}

function handleDeleteProject($conn, $user, $project_id) {
    global $response, $http_code;
    
    try {
        // Get project
        $stmt = $conn->prepare("SELECT created_by FROM projects WHERE id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Project not found');
        }
        
        $project = $result->fetch_assoc();
        $stmt->close();
        
        // Check authorization
        if ($user['role'] !== 'admin' && $project['created_by'] != $user['user_id']) {
            throw new Exception('Unauthorized');
        }
        
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param('i', $project_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Delete failed: ' . $stmt->error);
        }
        
        logActivity($conn, $user['user_id'], 'Project deleted', $project_id, "Project deleted");
        
        $stmt->close();
        
        $response = ['success' => true, 'message' => 'Project deleted'];
        $http_code = 200;
        
    } catch (Exception $e) {
        throw $e;
    }
}

function logActivity($conn, $user_id, $action, $project_id, $description) {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, project_id, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $user_id, $action, $project_id, $description);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Log errors are not critical
    }
}
?>