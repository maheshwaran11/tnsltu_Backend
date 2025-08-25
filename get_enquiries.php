<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php'; // contains $pdo connection
require 'jwt.php';   // for validateJWT()

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

$status = 400;
$message = '';
$data = [];

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
        try {
            $currentUserId = $userData['id'];
            $currentUserType = $userData['user_type'];

            // Get district for district-level admins
            $district = '';
            if (in_array($currentUserType, ['district_admin', 'district_subadmin'])) {
                $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
                $districtStmt->execute([$currentUserId]);
                $district = $districtStmt->fetchColumn();
            }

            $baseQuery = "
                SELECT id, name, phone, email, district, taluk, service, message, status, isRead, created_on
                FROM enquiries
            ";

            if ($currentUserType === 'admin') {
                $stmt = $pdo->prepare($baseQuery . " ORDER BY created_on DESC");
                $stmt->execute();
            } elseif (in_array($currentUserType, ['district_admin', 'district_subadmin']) && $district) {
                $stmt = $pdo->prepare($baseQuery . " WHERE district = ? ORDER BY created_on DESC");
                $stmt->execute([$district]);
            } else {
                $status = 403;
                $message = 'Unauthorized access';
                http_response_code($status);
                echo json_encode([
                    'status' => $status,
                    'message' => $message,
                    'data' => null
                ]);
                exit;
            }

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $status = 200;
            $message = count($data) ? 'Notifications retrieved successfully' : 'No notifications found';

        } catch (PDOException $e) {
            $status = 500;
            $message = 'Internal server error';
            error_log($e->getMessage()); // log DB error privately
        }
    }
}

http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
