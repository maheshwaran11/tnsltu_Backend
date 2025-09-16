<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';
require 'db.php';

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

// 📥 Get input payload
$data = json_decode(file_get_contents("php://input"), true);

$user_id      = $data['user_id'] ?? null;
$product_name = trim($data['product_name'] ?? '');
$quantity     = intval($data['quantity'] ?? 0);

if (!$user_id || !$product_name || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Missing or invalid fields']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert into orders
    $stmt1 = $pdo->prepare("
        INSERT INTO orders (user_id, status, created_by, created_at)
        VALUES (?, 'pending', ?, NOW())
    ");
    $stmt1->execute([$user_id, $currentUserId]);

    $order_id = $pdo->lastInsertId();

    // Insert into order_details
    $stmt2 = $pdo->prepare("
        INSERT INTO order_details (order_id, product_name, quantity)
        VALUES (?, ?, ?)
    ");
    $stmt2->execute([$order_id, $product_name, $quantity]);

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'status' => 201,
        'message' => 'Order created successfully',
        'data' => [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'product_name' => $product_name,
            'quantity' => $quantity,
            'status' => 'pending'
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Order creation failed',
        'error' => $e->getMessage()
    ]);
}
