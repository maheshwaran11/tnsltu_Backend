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



$status = 400;
$message = '';
$data = null;

try {
    // Collect form data (multipart/form-data)
    $title   = $_POST['title']   ?? null;
    $messageTxt = $_POST['message'] ?? null;
    $date    = $_POST['date']    ?? date("Y-m-d");
    $type    = $_POST['type']    ?? null;

    if ($title && $messageTxt) {
        $imagePath = null;

        // Handle image upload if exists
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . "/uploads/nitification_slides/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = time() . "_" . basename($_FILES["image"]["name"]);
            $targetFile = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                $imagePath = "uploads/nitification_slides/" . $filename; // relative path to save in DB
            } else {
                throw new Exception("Image upload failed");
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, date, image, type, created_on) 
            VALUES (:title, :message, :date, :image, :type, NOW())
        ");

        $stmt->execute([
            ':title'   => $title,
            ':message' => $messageTxt,
            ':date'    => $date,
            ':image'   => $imagePath,
            ':type'    => $type
        ]);

        $status = 201;
        $message = 'Notification created successfully';
        $data = [
            'id'    => $pdo->lastInsertId(),
            'image' => $imagePath
        ];
    } else {
        $status = 422;
        $message = 'Missing required fields: title, message';
    }
} catch (Exception $e) {
    $status = 500;
    $message = 'Error: ' . $e->getMessage();
}

http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
]);
