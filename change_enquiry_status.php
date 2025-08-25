<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

$id = (int)$input['id'];
$status = isset($input['status']) ? trim($input['status']) : null;

try {
    if ($status === null || $status === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 400,
            'message' => 'Status is required'
        ]);
        exit();
    }

    $sql = "UPDATE enquiries SET status = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
        ':id' => $id
    ]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode([
            'status' => 200,
            'data' => ['id' => $id, 'status' => $status],
            'message' => 'Enquiry status updated successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 404,
            'message' => 'Enquiry not found or no change in status'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'data' => null,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
