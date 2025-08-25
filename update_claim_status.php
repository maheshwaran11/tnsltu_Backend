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
    // 🔹 Check Auth Header
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

    // 🔹 Parse Input
    $input = json_decode(file_get_contents("php://input"));

    if (!isset($input->claim_id, $input->claim_status)) {
        $status = 400;
        throw new Exception("Missing required fields: claim_id, claim_status");
    }

    $claimId     = (int)$input->claim_id;
    $claimStatus = trim($input->claim_status);

    // 🔹 Update Status in family_members
    $stmt = $pdo->prepare("
        UPDATE family_members
        SET current_status = ?
        WHERE id = ?
    ");
    $stmt->execute([$claimStatus, $claimId]);

    if ($stmt->rowCount() === 0) {
        $status = 404;
        throw new Exception("Family member not found or status unchanged");
    }

    // 🔹 Fetch Updated Record
    $stmt = $pdo->prepare("
        SELECT fm.id, fm.user_id, u.username AS head_username, fm.name, fm.relation, fm.gender, fm.dob,
               fm.education, fm.course_duration, fm.joining_year, fm.final_year, fm.current_status, fm.created_at
        FROM family_members fm
        JOIN users u ON fm.user_id = u.id
        WHERE fm.id = ?
    ");
    $stmt->execute([$claimId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $status = 200;
    $message = "Claim status updated successfully";

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
