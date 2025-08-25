<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require 'config.php'; // contains $pdo connection

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 400,
        'message' => 'Missing enquiry ID'
    ]);
    exit();
}

$id = $input['id'];
$status = isset($input['status']) ? $input['status'] : null;
$isRead = isset($input['isRead']) ? (int)$input['isRead'] : null;

try {
    $updates = [];
    $params = [':id' => $id];

    if ($status !== null) {
        $updates[] = "status = :status";
        $params[':status'] = $status;
    }
    if ($isRead !== null) {
        $updates[] = "isRead = :isRead";
        $params[':isRead'] = $isRead;
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'status' => 400,
            'message' => 'No fields to update'
        ]);
        exit();
    }

    $sql = "UPDATE enquiries SET " . implode(", ", $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'data' => ['id' => $id, 'isRead' => $isRead],
        'message' => 'Enquiry updated successfully'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'data' => null,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
