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
            $currentUserId = $userData['id'];
            $currentUserType = $userData['user_type'];

            $district = '';
            if (in_array($currentUserType, ['district_admin', 'district_subadmin'])) {
                $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
                $districtStmt->execute([$currentUserId]);
                $district = $districtStmt->fetchColumn();
            }

            $baseQuery = "
                SELECT 
                    u.id, u.username, u.email, u.created_at,
                    ud.user_type, ud.address, ud.district, ud.taluk, ud.state, ud.zipcode,
                    ud.phone, ud.gender, ud.dob, ud.profile_photo, ud.status, ud.category, 
                    ud.notes, ud.member_id, ud.name, ud.created_by,
                    creator.username AS created_by_username
                FROM users u 
                LEFT JOIN user_details ud ON u.id = ud.user_id
                LEFT JOIN users creator ON ud.created_by = creator.id
            ";

            if ($currentUserType === 'admin') {
                $stmt = $pdo->query($baseQuery . " WHERE ud.user_type IN ('user', 'district_admin', 'district_subadmin') ORDER BY u.id DESC");
            } elseif (in_array($currentUserType, ['district_admin', 'district_subadmin']) && $district) {
                $stmt = $pdo->prepare($baseQuery . " WHERE ud.user_type IN ('user', 'district_subadmin') AND ud.district = ? ORDER BY u.id DESC");
                $stmt->execute([$district]);
            } else {
                $status = 403;
                $message = 'Unauthorized access';
                http_response_code($status);
                echo json_encode(['status' => $status, 'message' => $message, 'data' => null]);
                exit;
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch family members
            $familyStmt = $pdo->query("
                SELECT 
                    id, user_id, name, relation, gender, dob, education, 
                    education_year, current_year, claim_type, current_status
                FROM family_members
                ORDER BY user_id, id
            ");
            $familyMembers = $familyStmt->fetchAll(PDO::FETCH_ASSOC);

            // Group family members by user_id
            $familyByUser = [];
            foreach ($familyMembers as $fm) {
                $familyByUser[$fm['user_id']][] = $fm;
            }

            // Merge into users
            foreach ($users as &$user) {
            

                $userId = $user['id'];
                $user['family_members'] = $familyByUser[$userId] ?? [];
            }

            $status = 200;
            $message = 'Users with family members fetched successfully';
            $data = $users;

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
