<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require 'config.php'; // $pdo connection
require 'jwt.php';    // JWT validation

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$status = 400;
$message = '';
$data = null;

try {
    // Decode token
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        $status = 401;
        throw new Exception("Authorization header missing");
    }

    $token = str_replace("Bearer ", "", $headers['Authorization']);
    $payload = validateJWT($token);

    if (!$payload) {
        $status = 401;
        throw new Exception("Invalid or expired token");
    }

    // $currentUserId   = $payload->user_id;
    // $currentUserType = $payload->user_type;

    // Get input
    $input = json_decode(file_get_contents("php://input"));
    if (!isset($input->id, $input->name)) {
        $status = 400;
        throw new Exception("Missing required fields");
    }

    $userId         = (int)$input->id;
    $familyId       = isset($input->id) ? (int)$input->id : null; // <-- new
    $name           = trim($input->name);
    $relation       = $input->relation ?? null;
    $gender         = $input->gender ?? null;
    $dob            = $input->dob ?? null;
    $education      = $input->education ?? null;
    $courseDuration = $input->course_duration ?? null;
    $joiningYear    = $input->joining_year ?? null;
    $finalYear      = $input->final_year ?? null;
    $currentStatus  = $input->current_status ?? null;
    $claimType  = $input->claim_type ?? null;
// print_r($input);
    // INSERT or UPDATE logic
    if ($familyId) {
        // UPDATE existing member
        $stmt = $pdo->prepare("
            UPDATE family_members
            SET name=?, relation=?, gender=?, dob=?, education=?, course_duration=?, 
                joining_year=?, final_year=?, claim_type=?, current_status=?
            WHERE id=?
        ");
        $stmt->execute([
            $name, $relation, $gender, $dob, $education,
            $courseDuration, $joiningYear, $finalYear, $claimType, $currentStatus,
            $familyId
        ]);

        // fetch updated
        $stmt = $pdo->prepare("
            SELECT fm.id, fm.user_id, u.username AS head_username, fm.name, fm.relation, fm.gender, fm.dob,
                   fm.education, fm.course_duration, fm.joining_year, fm.final_year, fm.current_status, fm.created_at
            FROM family_members fm
            JOIN users u ON fm.user_id = u.id
            WHERE fm.id = ?
        ");
        $stmt->execute([$familyId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $status = 200;
        $message = "Family member updated successfully";

    } else {
        // INSERT new
        $stmt = $pdo->prepare("
            INSERT INTO family_members
                (user_id, name, relation, gender, dob, education, course_duration, joining_year, final_year, current_status, claim_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId, $name, $relation, $gender, $dob, $education,
            $courseDuration, $joiningYear, $finalYear, $currentStatus, $claimType
        ]);

        $familyId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            SELECT fm.id, fm.user_id, u.username AS head_username, fm.name, fm.relation, fm.gender, fm.dob,
                   fm.education, fm.course_duration, fm.joining_year, fm.final_year, fm.current_status, fm.claim_type, fm.created_at
            FROM family_members fm
            JOIN users u ON fm.user_id = u.id
            WHERE fm.id = ?
        ");
        $stmt->execute([$familyId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $status = 201;
        $message = "Family member added successfully";
    }

} catch (Exception $e) {
    if ($status === 200 || $status === 201) $status = 500;
    $message = $e->getMessage();
}

http_response_code($status);
echo json_encode([
    "status" => $status,
    "message" => $message,
    "data" => $data
]);
