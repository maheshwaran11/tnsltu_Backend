<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php';
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Authorization token not found']);
    exit;
}

$token = $matches[1];
$userData = validateJWT($token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired token']);
    exit;
}

$currentUserId   = $userData['id'];
$currentUserType = $userData['user_type'];

$data = json_decode(file_get_contents("php://input"));

$id_card_name      = trim($data->id_card_name ?? '');
$position          = trim($data->position ?? '');
$occupation        = trim($data->occupation ?? '');
$member_id         = trim($data->member_id ?? '');
$registration_date = trim($data->registration_date ?? '');
$next_renewal_date = trim($data->next_renewal_date ?? '');
$user_id           = intval($data->user_id ?? 0);
$approve           = isset($data->approved) ? (bool)$data->approved : false; // only admin can use this

// ✅ Basic validation
if (!$id_card_name || !$position || !$occupation || !$member_id || !$registration_date || !$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Required fields missing']);
    exit;
}

// ✅ Validate date format (YYYY-MM-DD)
function validateDate($date) {
    if (empty($date)) return true;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (!validateDate($registration_date) || !validateDate($next_renewal_date)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

try {
    // 🔍 Check if target user exists
    $checkUser = $pdo->prepare("SELECT id, user_type, district FROM user_details WHERE user_id = ?");
    $checkUser->execute([$user_id]);
    $targetUser = $checkUser->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Target user not found']);
        exit;
    }

    // 🔐 Authorization rules
    if ($currentUserType === 'user' && $user_id !== $currentUserId) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'message' => 'Unauthorized: Users can only update their own ID card']);
        exit;
    }

    if (in_array($currentUserType, ['district_admin', 'district_subadmin'])) {
        // Get district of current user
        $districtStmt = $pdo->prepare("SELECT district FROM user_details WHERE user_id = ?");
        $districtStmt->execute([$currentUserId]);
        $adminDistrict = $districtStmt->fetchColumn();

        if (!$adminDistrict || $adminDistrict !== $targetUser['district']) {
            http_response_code(403);
            echo json_encode(['status' => 403, 'message' => 'Unauthorized: Cannot manage users outside your district']);
            exit;
        }
    }

    // Decide status
    $statusValue = 'pending';
    if (in_array($currentUserType, ['district_admin', 'district_subadmin'])) {
        $statusValue = 'pending';
    } elseif ($currentUserType === 'admin') {
        
        if ($approve) {
            $statusValue = 'approved';
        } else {
            $statusValue = 'pending';
        }
        
        
         // admin can approve or leave pending
    }


    // 🔍 Check if ID card exists
    $checkCard = $pdo->prepare("SELECT id FROM user_id_card WHERE user_id = ?");
    $checkCard->execute([$user_id]);
    $existing = $checkCard->fetch();

    if ($existing) {
        // 🔄 Update
        $stmt = $pdo->prepare("
            UPDATE user_id_card 
            SET name = ?, position = ?, occupation = ?, card_number = ?, status = ?,
                registration_date = ?, next_renewal_date = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $id_card_name, $position, $occupation, $member_id,
            $statusValue, $registration_date, $next_renewal_date ?: null, $user_id
        ]);

        http_response_code(200);
        echo json_encode(['status' => 200, 'message' => 'ID card updated successfully', 'card_status' => $statusValue]);
    } else {
        // ➕ Insert
        $stmt = $pdo->prepare("
            INSERT INTO user_id_card (
                user_id, name, position, occupation, card_number, 
                registration_date, next_renewal_date, created_at, updated_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
        ");
        $stmt->execute([
            $user_id, $id_card_name, $position, $occupation,
            $member_id, $registration_date, $next_renewal_date ?: null, $statusValue
        ]);

        http_response_code(201);
        echo json_encode([
            'status' => 201,
            'message' => 'ID card created successfully',
            'card_status' => $statusValue,
            'data' => ['id' => $pdo->lastInsertId()]
        ]);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Server error'.$e->getMessage(),
    ]);
}
