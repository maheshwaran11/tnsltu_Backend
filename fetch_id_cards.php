<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require './jwt.php';

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

            $district = '';
            if (in_array($currentUserType, ['district_admin', 'district_subadmin'])) {
                $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
                $districtStmt->execute([$currentUserId]);
                $district = $districtStmt->fetchColumn();
            }

            $query = "
                SELECT 
                    u.id AS user_id,
                    u.username,
                    u.email,

                    ud.user_type,
                    ud.address,
                    ud.district,
                    ud.taluk,
                    ud.state,
                    ud.zipcode,
                    ud.phone,
                    ud.gender,
                    ud.dob,
                    ud.profile_photo,
                    ud.category,
                    ud.member_id,
                    ud.name AS full_name,

                    ic.id AS id_card_id,
                    ic.name AS id_card_name,
                    ic.occupation,
                    ic.position,
                    ic.card_number,
                    ic.issue_date,
                    ic.expiry_date,
                    ic.qr_code,
                    ic.barcode,
                    ic.status,
                    ic.created_at AS id_card_created_at,
                    ic.updated_at AS id_card_updated_at,
                    ic.registration_date,
                    ic.next_renewal_date,
                    ic.previous_renewal_date

                FROM users u
                LEFT JOIN user_details ud 
                    ON u.id = ud.user_id
                LEFT JOIN user_id_card ic 
                    ON u.id = ic.user_id";

            if ($currentUserType === 'admin') {
                $stmt = $pdo->query($query . " ORDER BY u.id DESC;");
            } elseif (in_array($currentUserType, ['district_admin', 'district_subadmin']) && $district) {
                $stmt = $pdo->prepare($query . " WHERE ud.created_by = ? ORDER BY u.id DESC;");
                $stmt->execute([$currentUserId]);
            } else {
                $status = 403;
                $message = 'Unauthorized access';
                http_response_code($status);
                echo json_encode(['status' => $status, 'message' => $message, 'data' => null]);
                exit;
            }
            $idCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $serial = 1;
            foreach ($idCards as &$user) {
                $user['serial_no'] = $serial++;
            }
            
            $totalCount = count($idCards);

            $status = 200;
            $message = 'ID cards fetched successfully';
            $data = $idCards;

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
    'data'    => $data,
    'total' => $totalCount
]);
