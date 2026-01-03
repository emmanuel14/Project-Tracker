<?php
// api/test.php - Diagnostic file to test your setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$diagnostics = [];

// 1. Check PHP version
$diagnostics['php_version'] = phpversion();

// 2. Check if MySQLi is installed
$diagnostics['mysqli_installed'] = extension_loaded('mysqli') ? 'Yes' : 'No';

// 3. Test database connection
$host = 'localhost';
$user = 'root';
$password = '';
$db = 'project_tracker';

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    $diagnostics['database_connection'] = 'Failed: ' . $conn->connect_error;
} else {
    $diagnostics['database_connection'] = 'Success';
    
    // 4. Check if tables exist
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    $diagnostics['database_tables'] = $tables;
    
    // 5. Check users table structure
    $result = $conn->query("DESCRIBE users");
    $users_columns = [];
    while ($row = $result->fetch_row()) {
        $users_columns[] = $row[0];
    }
    $diagnostics['users_table_columns'] = $users_columns;
    
    $conn->close();
}

// 6. Check file paths
$diagnostics['current_file'] = __FILE__;
$diagnostics['config_database_exists'] = file_exists(__DIR__ . '/../config/Database.php') ? 'Yes' : 'No';
$diagnostics['config_constants_exists'] = file_exists(__DIR__ . '/../config/Constants.php') ? 'Yes' : 'No';
$diagnostics['config_headers_exists'] = file_exists(__DIR__ . '/../config/Headers.php') ? 'Yes' : 'No';
$diagnostics['config_jwt_exists'] = file_exists(__DIR__ . '/../config/JWT.php') ? 'Yes' : 'No';

// 7. Check request method
$diagnostics['request_method'] = $_SERVER['REQUEST_METHOD'];
$diagnostics['request_uri'] = $_SERVER['REQUEST_URI'];

// 8. Check POST/GET data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $diagnostics['raw_input'] = $input;
    $diagnostics['json_decode_test'] = json_decode($input, true);
}

// Return JSON
echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>