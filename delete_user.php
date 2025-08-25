<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php';
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Unauthorized']);
    exit;
}

$token = $matches[1];
$userData = validateJWT($token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired token']);
    exit;
}

parse_str($_SERVER['QUERY_STRING'], $queryParams);
$id = $queryParams['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'User ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Delete from user_details first (FK constraint)
    $pdo->prepare("DELETE FROM user_details WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $pdo->commit();

    echo json_encode(['status' => 200, 'message' => 'User deleted successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Delete failed', 'error' => $e->getMessage()]);
}
