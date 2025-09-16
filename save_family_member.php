<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require 'config.php';
require 'jwt.php';

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
    if (empty($headers['Authorization'])) {
        $status = 401;
        throw new Exception("Authorization header missing");
    }

    $token   = str_replace("Bearer ", "", $headers['Authorization']);
    $payload = validateJWT($token);

    if (!$payload) {
        $status = 401;
        throw new Exception("Invalid or expired token");
    }

    // Input
    $input = json_decode(file_get_contents("php://input"));

    $userId   = (int)($input->user_id ?? $payload->user_id); // from body or JWT
    $familyId = (int)($input->family_id ?? 0); // only for update

    if (empty($userId) || empty($input->name)) {
        throw new Exception("Missing required fields");
    }

    $name           = trim($input->name);
    $relation       = $input->relation ?? null;
    $gender         = $input->gender ?? null;
    $dob            = $input->dob ?? null;
    $education      = $input->education ?? null;
    $courseDuration = $input->course_duration ?? null;
    $joiningYear    = $input->joining_year ?? 0;
    $finalYear      = $input->final_year ?? 0;
    $currentStatus  = $input->current_status ?? null;
    $claimType      = $input->claim_type ?? null;

    if ($familyId > 0) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE family_members
            SET name=?, relation=?, gender=?, dob=?, education=?, course_duration=?, 
                joining_year=?, final_year=?, claim_type=?, current_status=?
            WHERE id=? AND user_id=?
        ");
        $stmt->execute([
            $name, $relation, $gender, $dob, $education,
            $courseDuration, $joiningYear, $finalYear, $claimType, $currentStatus,
            $familyId, $userId
        ]);

        $stmt = $pdo->prepare("
            SELECT fm.id, fm.user_id, u.username AS head_username, fm.name, fm.relation, fm.gender, fm.dob,
                   fm.education, fm.course_duration, fm.joining_year, fm.final_year, fm.current_status, fm.claim_type, fm.created_at
            FROM family_members fm
            JOIN users u ON fm.user_id = u.id
            WHERE fm.id = ?
        ");
        $stmt->execute([$familyId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $status = 200;
        $message = "Family member updated successfully";

    } else {
        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO family_members
                (user_id, name, relation, gender, dob, education, course_duration, joining_year, final_year, claim_type, current_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId, $name, $relation, $gender, $dob, $education,
            $courseDuration, $joiningYear, $finalYear, $claimType, $currentStatus
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
    "status"  => $status,
    "message" => $message,
    "data"    => $data
]);
