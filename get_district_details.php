<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'config.php'; // contains $pdo

$status = 400;
$message = '';
$data = [];
$totalCount = 0;

try {
    $district = isset($_GET['district']) ? trim($_GET['district']) : '';

    $query = "
        SELECT 
            u.id, u.username, u.email,
            ud.user_type, ud.address, ud.district, ud.taluk, ud.state, ud.zipcode,
            ud.phone, ud.gender, ud.dob, ud.profile_photo, ud.status, ud.category,
            ud.notes, ud.member_id, ud.created_by
        FROM users u
        LEFT JOIN user_details ud ON u.id = ud.user_id
        WHERE ud.user_type IN ('district_admin', 'district_subadmin')
    ";

    if ($district !== '') {
        $query .= " AND ud.district = :district";
        $stmt = $pdo->prepare($query . " ORDER BY u.id DESC");
        $stmt->execute([':district' => $district]);
    } else {
        $stmt = $pdo->query($query . " ORDER BY u.id DESC");
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add serial number & membership duration
    $serial = 1;
    foreach ($users as &$user) {
        $user['serial_no'] = $serial++;

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
    $message = $district 
        ? "District admins from '{$district}' fetched successfully" 
        : "All district admins fetched successfully";
    $data = $users;

} catch (PDOException $e) {
    $status = 500;
    $message = 'Database error: ' . $e->getMessage();
}

http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data,
    'total' => $totalCount
]);
