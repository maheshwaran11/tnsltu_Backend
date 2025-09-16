<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';
require 'db.php';

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

// ✅ Only Admin
if ($userData['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 403, 'message' => 'Access denied']);
    exit;
}

// 📥 Input Payload
$data = json_decode(file_get_contents("php://input"), true);

$order_id         = $data['order_id'] ?? null;
$detail_id        = $data['detail_id'] ?? null;
$status           = $data['status'] ?? null;
$approved_quantity = isset($data['approved_quantity']) ? intval($data['approved_quantity']) : null;

if (!$order_id || !$detail_id || !in_array($status, ['approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Invalid payload']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 🔹 Update order_details row
    $stmt = $pdo->prepare("UPDATE order_details SET approved_quantity = ?, status = ? WHERE id = ? AND order_id = ?");
    $stmt->execute([$approved_quantity, $status, $detail_id, $order_id]);

    // 🔹 Recalculate parent order status (if all items are approved/rejected)
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as pending_count FROM order_details WHERE order_id = ? AND status = 'pending'");
    $checkStmt->execute([$order_id]);
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($row['pending_count'] == 0) {
        // No pending items left → mark order as closed
        $updateOrder = $pdo->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $updateOrder->execute([$order_id]);
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'message' => "Item updated successfully",
        'order_id' => $order_id,
        'detail_id' => $detail_id,
        'status' => $status,
        'approved_quantity' => $approved_quantity
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to update item',
        'error' => $e->getMessage()
    ]);
}
