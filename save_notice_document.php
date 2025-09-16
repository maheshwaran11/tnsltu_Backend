<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'db.php';
require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔑 JWT Auth
$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Authorization token not found']);
    exit;
}

$token = $matches[1];
$userData = validateJWT($token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired token']);
    exit;
}

$currentUserId = $userData['id'];

try {
    // Use POST for both create and update
    $id          = $_POST['id'] ?? null;
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');

    // File Upload
    $filePath = null;
    if (!empty($_FILES['file']['name'])) {
        $uploadDir = 'uploads/documents/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . "_" . basename($_FILES['file']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $filePath = $targetPath;
        } else {
            throw new Exception("File upload failed");
        }
    }

    if (!$title) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => 'Title is required']);
        exit;
    }

    if ($id) {
        // ✅ UPDATE DOCUMENT
        $query = "UPDATE available_documents 
                  SET title = ?, description = ?, category = ?, updated_by = ?, updated_at = NOW()";
        $params = [$title, $description, $category, $currentUserId];

        if ($filePath) {
            $query .= ", file_path = ?";
            $params[] = $filePath;
        }

        $query .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        http_response_code(200);
        echo json_encode([
            'status' => 200,
            'message' => 'Document updated successfully',
            'id' => $id
        ]);
    } else {
        // ✅ CREATE DOCUMENT
        $stmt = $pdo->prepare("INSERT INTO available_documents 
            (title, description, category, file_path, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $description, $category, $filePath, $currentUserId]);

        $newId = $pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'status' => 201,
            'message' => 'Document created successfully',
            'id' => $newId
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Error saving document',
        'error' => $e->getMessage()
    ]);
}
