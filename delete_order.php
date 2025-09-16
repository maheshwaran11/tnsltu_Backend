<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';
require 'db.php'; // DB connection

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
    echo json_encode(['status' => 401, 'message' => 'Authorization token not found']);
    exit;
}

$token = $matches[1];
$userData = validateJWT($token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired token']);
    exit;
}

$currentUserId = $userData['id'];
$userRole = $userData['user_type'] ?? 'user';

$data = json_decode(file_get_contents("php://input"));
$order_id = $data->order_id ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Order ID is required']);
    exit;
}

try {
    // ✅ Check ownership if not admin
    if ($userRole !== 'admin') {
        $checkStmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $checkStmt->execute([$order_id]);
        $order = $checkStmt->fetch();

        if (!$order || $order['user_id'] != $currentUserId) {
            http_response_code(403);
            echo json_encode(['status' => 403, 'message' => 'Not authorized to delete this order']);
            exit;
        }
    }

    // 🔁 Transaction to delete order & items
    $pdo->beginTransaction();

    $stmt1 = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
    $stmt1->execute([$order_id]);

    $stmt2 = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt2->execute([$order_id]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['status' => 200, 'message' => 'Order deleted successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Failed to delete order', 'error' => $e->getMessage()]);
}
