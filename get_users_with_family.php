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

$status = 400;
$message = '';
$data = null;

try {
    // Get auth token
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $status = 401;
        throw new Exception('Authorization token not found');
    }

    $token = $matches[1];
    $userData = validateJWT($token);
    if (!$userData) {
        $status = 401;
        throw new Exception('Invalid or expired token');
    }

    $currentUserId   = $userData['id'];
    $currentUserType = $userData['user_type'];

    // Get user_id from GET parameter
    if (!isset($_GET['id'])) {
        $status = 400;
        throw new Exception('User id is required');
    }
    $userId = (int)$_GET['id'];

    // Base query to get user
    $baseQuery = "
        SELECT 
            u.id, u.username, u.email, u.created_at,
            ud.user_type, ud.address, ud.district, ud.taluk, ud.state, ud.zipcode,
            ud.phone, ud.gender, ud.dob, ud.profile_photo, ud.status, ud.category,
            ud.notes, ud.member_id, ud.created_by,
            creator.username AS created_by_username
        FROM users u
        LEFT JOIN user_details ud ON u.id = ud.user_id
        LEFT JOIN users creator ON ud.created_by = creator.id
        WHERE u.id = ?
    ";

    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $status = 404;
        throw new Exception('User not found');
    }

    // Authorization: only admins or the user themselves can view
    if ($currentUserId !== $userId && !in_array($currentUserType, ['admin', 'district_admin', 'district_subadmin'])) {
        $status = 403;
        throw new Exception('Unauthorized access');
    }

    // Fetch family members for this user
    $familyStmt = $pdo->prepare("
        SELECT id, user_id, name, relation, gender, dob, education,
               education_year, current_year, current_status, joining_year, final_year, course_duration, claim_type
        FROM family_members
        WHERE user_id = ?
        ORDER BY id
    ");
    $familyStmt->execute([$userId]);
    $familyMembers = $familyStmt->fetchAll(PDO::FETCH_ASSOC);

    $user['family_members'] = $familyMembers;

    $status = 200;
    $message = 'User with family members fetched successfully';
    $data = $user;

} catch (Exception $e) {
    if ($status === 200) $status = 500;
    $message = $e->getMessage();
}

http_response_code($status);
echo json_encode([
    'status'  => $status,
    'message' => $message,
    'data'    => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
