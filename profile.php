<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php';
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$status = 400;
$message = '';
$data = null;

// Read Authorization header
$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $status = 401;
    $message = 'Authorization token not found';
    http_response_code($status);
} else {
    $token = $matches[1];
    $userData = validateJWT($token);

    if (!$userData) {
        $status = 401;
        $message = 'Invalid or expired token';
        http_response_code($status);
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.username, u.email,
                    ud.user_type, ud.address, ud.district, ud.taluk, ud.state,
                    ud.zipcode, ud.phone, ud.gender, ud.dob, ud.profile_photo,
                    ud.status, ud.category, ud.notes, ud.name, ud.notes, ud.member_id, ud.created_at, ud.updated_at
                FROM users u
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$userData['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $status = 200;
                $message = 'User profile retrieved';
                $data = $user;
            } else {
                $status = 404;
                $message = 'User not found';
            }

            http_response_code($status);
        } catch (PDOException $e) {
            $status = 500;
            $message = 'Server error';
            http_response_code($status);
        }
    }
}

// Final JSON response
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
]);
