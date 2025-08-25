<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require 'config.php'; // contains $pdo connection

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$status = 400;
$message = '';
$data = null;

try {
    // Get raw input
    $input = json_decode(file_get_contents("php://input"), true);

    if (
        isset($input['name'], $input['phone'], $input['email'], $input['district'],
              $input['taluk'], $input['service'], $input['message'])
    ) {
        $stmt = $pdo->prepare("
            INSERT INTO enquiries 
                (name, phone, email, district, taluk, service, message, status, isRead) 
            VALUES 
                (:name, :phone, :email, :district, :taluk, :service, :message, 'submitted', 0)
        ");

        $stmt->execute([
            ':name'     => $input['name'],
            ':phone'    => $input['phone'],
            ':email'    => $input['email'],
            ':district' => $input['district'],
            ':taluk'    => $input['taluk'],
            ':service'  => $input['service'],
            ':message'  => $input['message']
        ]);

        $status = 201;
        $message = 'Enquiry added successfully';
        $data = ['id' => $pdo->lastInsertId()];
    } else {
        $status = 422;
        $message = 'Missing required fields';
    }

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
