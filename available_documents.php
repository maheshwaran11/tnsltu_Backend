<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';
require 'db.php'; // 🔗 include PDO connection ($pdo)

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔒 Extract JWT token
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
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // 📖 Read all / single document
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM available_documents WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($doc) {
                    echo json_encode(['status' => 200, 'data' => $doc]);
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 404, 'message' => 'Document not found']);
                }
            } else {
                $stmt = $pdo->query("SELECT * FROM available_documents ORDER BY created_at DESC");
                $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 200, 'data' => $docs]);
            }
            break;

        // ➕ Create document (with file upload)
        case 'POST':
            $title       = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $uploaded_by = $currentUserId;

            if (!$title || !isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Title and file are required']);
                exit;
            }

            $file = $_FILES['file'];
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Invalid file type']);
                exit;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'File upload error']);
                exit;
            }

            $uploadDir = __DIR__ . '/uploads/documents/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $fileName = $safeName . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $dbPath = 'uploads/documents/' . $fileName;

                $stmt = $pdo->prepare("INSERT INTO available_documents (title, description, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $dbPath, $uploaded_by]);

                http_response_code(201);
                echo json_encode(['status' => 201, 'message' => 'Document uploaded successfully', 'file_path' => $dbPath]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 500, 'message' => 'Failed to save file']);
            }
            break;

        // ✏️ Update document (with optional new file)
        case 'PUT':
            parse_str(file_get_contents("php://input"), $data);

            $id          = $data['id'] ?? null;
            $title       = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $file_path   = $data['file_path'] ?? null; // keep old path if not updating file

            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Document ID required']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE available_documents SET title=?, description=?, file_path=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $description, $file_path, $id]);

            echo json_encode(['status' => 200, 'message' => 'Document updated']);
            break;

        // 🗑 Delete document
        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"));
            $id = $data->id ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Document ID required']);
                exit;
            }

            // remove file from server
            $stmt = $pdo->prepare("SELECT file_path FROM available_documents WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            if ($doc && file_exists(__DIR__ . '/' . $doc['file_path'])) {
                unlink(__DIR__ . '/' . $doc['file_path']);
            }

            $stmt = $pdo->prepare("DELETE FROM available_documents WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['status' => 200, 'message' => 'Document deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 405, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
