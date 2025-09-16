<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php';
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the Authorization token
$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

$status = 400;
$message = '';
$data = null;

// Token validation
if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $status = 401;
    $message = 'Authorization token not found';
} else {
    $token = $matches[1];
    $userData = validateJWT($token);
    if (!$userData) {
        $status = 401;
        $message = 'Invalid or expired token';
    } else {
        // ✅ Check if ID is passed in the query
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 400,
                'message' => 'Missing or invalid user ID'
            ]);
            exit;
        }

        $userId = (int) $_GET['id'];

        try {
            // Get user info
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.username, u.email, ud.user_type, ud.address, ud.address_tamil, ud.district, 
                    ud.taluk, ud.state, ud.zipcode, ud.phone, ud.gender, ud.dob, 
                    ud.profile_photo, ud.status, ud.category, ud.notes, ud.member_id, ud.name, ud.relation_name, ud.relation_type, ud.card_type, ud.card_status, ud.subscription_number, ud.donation_number
                FROM users u 
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $status = 200;
                $message = 'User fetched successfully';
                $data = $user;
            } else {
                $status = 404;
                $message = 'User not found';
            }

        } catch (PDOException $e) {
            $status = 500;
            $message = 'Server error';
        }
    }
}

http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
]);
