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

$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

$status = 400;
$message = '';
$data = null;

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
            $currentUserId   = $userData['id'];
            $currentUserType = $userData['user_type'];

            $sql = "";
            $params = [];

            if ($currentUserType === 'admin') {
                // Admin → fetch all counts
                $sql = "
                    SELECT 
                        COUNT(*) AS total_users,
                        COUNT(CASE WHEN ud.status = 'active' THEN 1 END) AS active_users,
                        COUNT(CASE WHEN ud.status = 'inactive' THEN 1 END) AS inactive_users,
                        COUNT(CASE WHEN ud.user_type = 'admin' THEN 1 END) AS admin_users,
                        COUNT(CASE WHEN ud.user_type = 'district_admin' THEN 1 END) AS district_admins,
                        COUNT(CASE WHEN ud.user_type = 'taluk_admin' THEN 1 END) AS taluk_admins,
                        COUNT(CASE WHEN ud.user_type = 'district_subadmin' THEN 1 END) AS district_subadmins,
                        COUNT(CASE WHEN ud.user_type = 'taluk_subadmin' THEN 1 END) AS taluk_subadmins,
                        COUNT(CASE WHEN ud.user_type = 'user' THEN 1 END) AS normal_users
                    FROM users u
                    LEFT JOIN user_details ud ON u.id = ud.user_id
                ";
            } elseif (in_array($currentUserType, ['district_admin','district_subadmin'])) {
                // District admins → prefer created_by, fallback to district

                // First check count by created_by
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM users u
                    LEFT JOIN user_details ud ON u.id = ud.user_id
                    WHERE ud.user_type IN ('user', 'district_subadmin') AND ud.created_by = ?
                ");
                $checkStmt->execute([$currentUserId]);
                $createdByCount = $checkStmt->fetchColumn();

                if ($createdByCount > 0) {
                    $sql = "
                        SELECT COUNT(*) AS normal_users
                        FROM users u
                        LEFT JOIN user_details ud ON u.id = ud.user_id
                        WHERE ud.user_type IN ('user', 'district_subadmin') AND ud.created_by = ?
                    ";
                    $params = [$currentUserId];
                } else {
                    // fallback → district-based count
                    $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
                    $districtStmt->execute([$currentUserId]);
                    $district = $districtStmt->fetchColumn();

                    $sql = "
                        SELECT COUNT(*) AS normal_users
                        FROM users u
                        LEFT JOIN user_details ud ON u.id = ud.user_id
                        WHERE ud.user_type IN ('user', 'district_subadmin') AND ud.district = ?
                    ";
                    $params = [$district];
                }
            } elseif (in_array($currentUserType, ['taluk_admin','taluk_subadmin'])) {
                // District admins → prefer created_by, fallback to district

                // First check count by created_by
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM users u
                    LEFT JOIN user_details ud ON u.id = ud.user_id
                    WHERE ud.user_type IN ('user') AND ud.created_by = ?
                ");
                $checkStmt->execute([$currentUserId]);
                $createdByCount = $checkStmt->fetchColumn();

                if ($createdByCount > 0) {
                    $sql = "
                        SELECT COUNT(*) AS normal_users
                        FROM users u
                        LEFT JOIN user_details ud ON u.id = ud.user_id
                        WHERE ud.user_type IN ('user',) AND ud.created_by = ?
                    ";
                    $params = [$currentUserId];
                } else {
                    // fallback → district-based count
                    $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
                    $districtStmt->execute([$currentUserId]);
                    $district = $districtStmt->fetchColumn();

                    $talukStmt = $pdo->prepare("SELECT taluk FROM user_details WHERE user_id = ?");
                    $talukStmt->execute([$currentUserId]);
                    $taluk = $talukStmt->fetchColumn();

                    $sql = "
                        SELECT COUNT(*) AS normal_users
                        FROM users u
                        LEFT JOIN user_details ud ON u.id = ud.user_id
                        WHERE ud.user_type IN ('user') AND ud.district = ? AND ud.taluk = ?
                    ";
                    $params = [$district, $taluk];
                }
            } else {
                $status = 403;
                $message = 'Unauthorized access';
                http_response_code($status);
                echo json_encode(['status' => $status, 'message' => $message, 'data' => null]);
                exit;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $status = 200;
            $message = 'User statistics fetched successfully';
            $data = $stats;

        } catch (PDOException $e) {
            $status = 500;
            $message = 'Server error: ' . $e->getMessage();
        }
    }
}

http_response_code($status);
echo json_encode([
    'status'  => $status,
    'message' => $message,
    'data'    => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
