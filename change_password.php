<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php';
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($status, $message) {
    http_response_code($status);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Authorization check
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    sendResponse(401, 'Authorization token not found');
}

$token = $matches[1];
$userData = validateJWT($token);

if (!$userData) {
    sendResponse(401, 'Invalid or expired token');
}

// Read request JSON
$input = json_decode(file_get_contents("php://input"), true);

$current = $input['current'] ?? '';
$new = $input['new'] ?? '';
$confirm = $input['confirm'] ?? '';

if (!$current || !$new || !$confirm) {
    sendResponse(400, 'All fields are required');
}
if ($new !== $confirm) {
    sendResponse(400, 'New password and confirm password do not match');
}

try {
    // Fetch current password hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userData['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($current, $row['password'])) {
        sendResponse(401, 'Current password is incorrect');
    }

    // Update new password
    $newHash = password_hash($new, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$newHash, $userData['id']]);

    sendResponse(200, 'Password updated successfully');

} catch (PDOException $e) {
    sendResponse(500, 'Server error');
}
