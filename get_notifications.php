<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'config.php'; // contains $pdo connection

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$status = 200;
$message = '';
$data = [];

try {
    $stmt = $pdo->query("SELECT id, title, message, date, image, type, created_on 
                         FROM notifications 
                         ORDER BY created_on DESC");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepend full URL for images if they exist
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $baseUrl .= "://".$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME'])."/";

    foreach ($rows as &$row) {
        if (!empty($row['image'])) {
            $row['image_url'] = $baseUrl . $row['image'];
        } else {
            $row['image_url'] = null;
        }
    }

    $status = 200;
    $message = 'Notifications fetched successfully';
    $data = $rows;

} catch (PDOException $e) {
    $status = 500;
    $message = 'Database error: ' . $e->getMessage();
}

http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
]);
