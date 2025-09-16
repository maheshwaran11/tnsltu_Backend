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

            $query = "
            SELECT 
                    u.id, u.username, u.email, 
                    ud.user_type, ud.address, ud.address_tamil, ud.district, ud.taluk, ud.state, ud.zipcode,
                    ud.phone, ud.gender, ud.dob, ud.profile_photo, ud.status, ud.category, 
                    ud.notes, ud.member_id, ud.name, ud.relation_name, ud.relation_type, ud.subscription_number, ud.donation_number, ud.card_type, ud.card_status, ud.created_by,
                    creator.username AS created_by_username
                FROM users u 
                LEFT JOIN user_details ud ON u.id = ud.user_id
                LEFT JOIN users creator ON ud.created_by = creator.id
            ";

            // print_r($query);


            if ($currentUserType === 'admin') {
                $stmt = $pdo->query($query . " ORDER BY u.id DESC;");
            } elseif (in_array($currentUserType, ['district_admin', 'district_subadmin']) && $district) {
                $stmt = $pdo->prepare($query . " WHERE ud.created_by = ? ORDER BY u.id DESC;");
                // print_r($query . " WHERE ud.created_by = ? ORDER BY u.id DESC;");
                // print_r($currentUserId);
                $stmt->execute([$currentUserId]);
            } else {
                $status = 403;
                $message = 'Unauthorized access';
                http_response_code($status);
                echo json_encode(['status' => $status, 'message' => $message, 'data' => null]);
                exit;
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $serial = 1;
            
            foreach ($users as &$user) {
                $user['serial_no'] = $serial++;
                // Calculate membership duration
                if (!empty($user['registration_date'])) {
                    $start = new DateTime($user['registration_date']);
                    $now = new DateTime();
                    $interval = $start->diff($now);
                    $user['membership_duration'] = $interval->y . ' years, ' . $interval->m . ' months';
                } else {
                    $user['membership_duration'] = 'N/A';
                }
            }
            
            $totalCount = count($users);

            $status = 200;
            $message = 'Users fetched successfully';
            $data = $users;

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
    'data' => $data,
    'total' => $totalCount
]);
