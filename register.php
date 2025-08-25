<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $status = 401;
    $message = 'Authorization token not found';
} else {
    $token = $matches[1];
    $userData = validateJWT($token);


    if (!$userData) {
        $status = 401;
        $message = 'Invalid or expired token';
    } else {
        $currentUserId = $userData['id'];


        $data = json_decode(file_get_contents("php://input"));

        $username      = trim($data->username ?? '');
        $fullName      = trim($data->name ?? '');
        $email         = trim($data->email ?? '');
        $password      = $data->password ?? '';
        $user_type     = $data->user_type ?? 'user';
        $address       = $data->address ?? '';
        $address_tamil = $data->address_tamil ?? '';
        $district      = $data->district ?? '';
        $taluk         = $data->taluk ?? '';
        $state         = $data->state ?? '';
        $zipcode       = $data->zipcode ?? '';
        $phone         = $data->phone ?? '';
        $gender        = $data->gender ?? null;
        $dob           = $data->dob ?? null;
        $profile_photo = $data->profile_photo ?? null;
        $status        = $data->status ?? 'inactive';
        $category     = $data->category ?? '';
        $notes         = $data->notes ?? null;
        $member_id     = $data->member_id ?? null;  

        // Initialize profile photo path
        $profilePhotoPath = null;

        // 🔁 Upload profile photo if valid base64 provided
        if (!empty($profile_photo) && is_string($profile_photo) && preg_match('/^data:image\/(\w+);base64,/', $profile_photo, $type)) {
            $extension = strtolower($type[1]); // jpg, png, gif, etc.

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $profile_photo = substr($profile_photo, strpos($profile_photo, ',') + 1);
                $profile_photo = base64_decode($profile_photo);

                if ($profile_photo === false) {
                    http_response_code(400);
                    echo json_encode(['status' => 400, 'message' => 'Invalid base64 image']);
                    exit;
                }

                $uploadDir = 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // 🔤 Sanitize username + email to use in filename
                $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $username . '_' . $email);
                $fileName = strtolower($safeName) . '.' . $extension;
                $filePath = $uploadDir . $fileName;

                if (file_put_contents($filePath, $profile_photo)) {
                    $profilePhotoPath = $filePath;
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 500, 'message' => 'Failed to save profile photo']);
                    exit;
                }
            }
        }

        // ✅ Basic validation
        if (!$username || !$email || !$password || !$user_type) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'Required fields missing']);
            exit;
        }

        try {
            // 🔍 Email uniqueness check
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            
            if ($checkStmt->fetch()) {
                http_response_code(409); // 409 Conflict
                echo json_encode(['status' => 409, 'message' => 'Email already registered']);
                exit;
            }

            // Start transaction
            $pdo->beginTransaction();

            // Insert into users table
            $stmt1 = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt1->execute([$username, $email, $hashedPassword]);

            // Get the new user_id
            $user_id = $pdo->lastInsertId();

            // Insert into user_details table
            $stmt2 = $pdo->prepare("
                INSERT INTO user_details (
                    user_id, user_type, address, address_tamil, district, taluk, state, zipcode, phone,
                    gender, dob, status, category, notes, profile_photo, member_id, name, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt2->execute([
                $user_id, $user_type, $address, $address_tamil, $district, $taluk, $state, $zipcode, $phone,
                $gender, $dob, $status, $category, $notes, $profilePhotoPath, $member_id, $fullName, $currentUserId
            ]);

            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                'status' => 201,
                'message' => 'User registered successfully',
                'data' => [
                    'user_id' => $user_id,
                    'profile_photo' => $profilePhotoPath
                ]
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                'status' => 500,
                'message' => 'Registration failed',
                'data' => ['error' => $e->getMessage()]
            ]);
        }

    }
}

