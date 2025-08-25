<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php'; // contains $pdo connection

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$status = 400;
$message = '';
$data = null;

try {
    // ✅ Accept either query param ?id=123 OR JSON body {"id":123}
    $id = null;

    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    } else {
        $input = json_decode(file_get_contents("php://input"), true);
        if (isset($input['id'])) {
            $id = intval($input['id']);
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            $status = 200;
            $message = "Notification deleted successfully";
            $data = ["id" => $id];
        } else {
            $status = 404;
            $message = "Notification not found";
        }
    } else {
        $status = 422;
        $message = "Missing or invalid ID";
    }

} catch (PDOException $e) {
    $status = 500;
    $message = "Database error: " . $e->getMessage();
}

http_response_code($status);
echo json_encode([
    "status" => $status,
    "message" => $message,
    "data" => $data
]);
