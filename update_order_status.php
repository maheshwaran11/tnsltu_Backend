<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔐 Auth Header
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

// ✅ Only admin can update
if ($userData['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 403, 'message' => 'Only admin can update order status']);
    exit;
}

// 🔹 Get input payload
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['order_id'], $input['status'])) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Invalid payload']);
    exit;
}

$order_id = intval($input['order_id']);
$status = $input['status'];
$approved_quantity = isset($input['quantity']) ? intval($input['quantity']) : null;

$validStatuses = ['approved', 'rejected', 'pending'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Invalid status value']);
    exit;
}

try {
    // Update order status in orders table
    $stmt1 = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt1->execute([$status, $order_id]);

    // Update approved quantity in order_details if provided
    if ($approved_quantity !== null) {
        $stmt2 = $pdo->prepare("UPDATE order_details SET approved_quantity = ? WHERE order_id = ?");
        $stmt2->execute([$approved_quantity, $order_id]);
    }

    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'message' => 'Order status updated successfully',
        'data' => [
            'order_id' => $order_id,
            'approved_quantity' => $approved_quantity,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to update order status',
        'error' => $e->getMessage()
    ]);
}
