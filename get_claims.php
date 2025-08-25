<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php'; // Database connection using $pdo
require 'jwt.php';    // JWT validation function: validateJWT($token)

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
            $currentUserId = $userData['id'];
            $currentUserType = $userData['user_type'];

            $district = '';
            if (in_array($currentUserType, ['district_admin', 'district_subadmin'])) {
                $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
                $districtStmt->execute([$currentUserId]);
                $district = $districtStmt->fetchColumn();
            }

            // Build query to join family_members, users, and user_details
            $query = "
                SELECT 
                    fm.id AS claim_id,
                    fm.user_id,
                    fm.name AS member_name,
                    fm.relation,
                    fm.gender AS member_gender,
                    fm.dob AS member_dob,
                    fm.education,
                    fm.course_duration,
                    fm.education_year,
                    fm.current_year,
                    fm.joining_year,
                    fm.final_year,
                    fm.claim_type,
                    fm.current_status,
                    fm.created_at AS claim_created_at,
                    u.username,
                    u.email,
                    ud.user_type,
                    ud.address,
                    ud.district,
                    ud.taluk,
                    ud.state,
                    ud.zipcode,
                    ud.phone
                FROM family_members fm
                JOIN users u ON fm.user_id = u.id
                JOIN user_details ud ON fm.user_id = ud.user_id
            ";

            if ($currentUserType === 'admin') {
                $stmt = $pdo->query($query . " ORDER BY fm.id DESC");
            } elseif (in_array($currentUserType, ['district_admin', 'district_subadmin']) && $district) {
                $query .= " WHERE ud.district = ? ORDER BY fm.id DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$district]);
            } else {
                $status = 403;
                $message = 'Unauthorized access';
                http_response_code($status);
                echo json_encode(['status' => $status, 'message' => $message, 'data' => null]);
                exit;
            }

            $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $status = 200;
            $message = 'Claims fetched successfully';
            $data = $claims;

        } catch (PDOException $e) {
            $status = 500;
            $message = 'Server error: ' . $e->getMessage();
        }
    }
}

http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
