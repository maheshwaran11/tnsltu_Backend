<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

require 'config.php'; // Ensure $pdo is available
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

// Accept either username or email
$loginId = trim($data->email ?? ''); // can be username or email
$password = $data->password ?? '';

$status = 400;
$message = '';
$responseData = null;

if (!$loginId || !$password) {
    $message = 'Username/Email and password are required';
    http_response_code($status);
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id, u.username, u.email, u.password,
                ud.user_type
            FROM users u
            LEFT JOIN user_details ud ON u.id = ud.user_id
            WHERE u.username = ? OR u.email = ?
        ");
        $stmt->execute([$loginId, $loginId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $tokenPayload = [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'user_type' => $user['user_type'] ?? 'user'
            ];

            $token = createJWT($tokenPayload);

            $status = 200;
            $message = 'Login successful';
            $responseData = [
                'token' => $token,
                'user_type' => $tokenPayload['user_type']
            ];
        } else {
            $message = 'Invalid username/email or password';
        }

        http_response_code($status);
    } catch (PDOException $e) {
        $status = 500;
        $message = 'Server error';
        http_response_code($status);
    }
}

// Send JSON response
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $responseData
]);
