<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$currentUserId = $userData['id'];

// 🔹 Optional: allow admin to fetch another user’s orders by query param ?user_id=xx
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $currentUserId;

if ($user_id !== $currentUserId && $userData['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 403, 'message' => 'You can only view your own orders']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT o.id as order_id, o.user_id, o.status as order_status, o.created_at,
               od.id as detail_id, od.product_name, od.quantity, od.approved_quantity, od.status as detail_status
        FROM orders o
        JOIN order_details od ON o.id = od.order_id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by order_id
    $orders = [];
    foreach ($rows as $row) {
        $oid = $row['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'order_id' => $row['order_id'],
                'user_id' => $row['user_id'],
                'order_status' => $row['order_status'],
                'created_at' => $row['created_at'],
                'items' => []
            ];
        }

        $orders[$oid]['items'][] = [
            'detail_id' => $row['detail_id'],
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'approved_quantity' => $row['approved_quantity'],
            'status' => $row['detail_status']
        ];
    }

    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'message' => 'Orders fetched successfully',
        'data' => array_values($orders)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to fetch orders',
        'error' => $e->getMessage()
    ]);
}
