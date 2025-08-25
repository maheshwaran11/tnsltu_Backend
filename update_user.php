<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'jwt.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Default response
$status = 400;
$message = '';
$data = null;

// Get Authorization
$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = $headers['Authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $status = 401;
    $message = 'Authorization token not found';
    http_response_code($status);
} else {
    $token = $matches[1];
    $userData = validateJWT($token);

    if (!$userData) {
        $status = 401;
        $message = 'Invalid or expired token';
        http_response_code($status);
    } else {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            $status = 400;
            $message = 'User ID is required';
            http_response_code($status);
        } else {
            $dataInput = json_decode(file_get_contents("php://input"));

            // Extract fields
            $username      = trim($dataInput->username ?? '');
            $name      = trim($dataInput->name ?? '');
            $email         = trim($dataInput->email ?? '');
            $user_type     = trim($dataInput->user_type ?? '');
            $address       = trim($dataInput->address ?? '');
            $address_tamil = trim($dataInput->address_tamil ?? '');
            $district      = trim($dataInput->district ?? '');
            $taluk         = trim($dataInput->taluk ?? '');
            $state         = trim($dataInput->state ?? '');
            $zipcode       = trim($dataInput->zipcode ?? '');
            $phone         = trim($dataInput->phone ?? '');
            $gender        = $dataInput->gender ?? null;
            $dob           = $dataInput->dob ?? null;
            $profile_photo = $dataInput->profile_photo ?? null;
            $status_value  = trim($dataInput->status ?? 'inactive');
            $category     = $dataInput->category ?? '';
            $notes         = $dataInput->notes ?? null;
            $member_id     = $dataInput->member_id ?? null;

            // print_r($dataInput);

            $profilePhotoPath = null;

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

                    // print_r($filePath);


                    if (file_put_contents($filePath, $profile_photo)) {
                        $profilePhotoPath = $filePath;
                    } else {
                        http_response_code(500);
                        echo json_encode(['status' => 500, 'message' => 'Failed to save profile photo']);
                        exit;
                    }
                }
            }



            // ✅ Basic validations
            if (!$username || !$email || !$user_type) {
                $status = 422;
                $message = 'Username, email, and user type are required';
                http_response_code($status);
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $status = 422;
                $message = 'Invalid email format';
                http_response_code($status);
            } elseif (!empty($phone) && !preg_match('/^\d{10,15}$/', $phone)) {
                $status = 422;
                $message = 'Invalid phone number';
                http_response_code($status);
            } else {
                try {
                    // Update user_details
                   $stmt1 = $pdo->prepare("
                        UPDATE user_details 
                        SET user_type = ?, address = ?, address_tamil = ?, district = ?, taluk = ?, state = ?, zipcode = ?, 
                            phone = ?, gender = ?, dob = ?, status = ?, category = ?, notes = ?, profile_photo = ?, 
                            member_id = ?, name = ?
                        WHERE user_id = ?
                    ");
                    $stmt1->execute([
                        $user_type, $address, $address_tamil, $district, $taluk, $state, $zipcode,
                        $phone, $gender, $dob, $status_value, $category, $notes,
                        $profilePhotoPath, $member_id, $name,  $id
                    ]);

                    // Update users table
                    $stmt2 = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmt2->execute([$username, $email, $id]);

                    $status = 200;
                    $message = 'User updated successfully';
                    $data = ['user' => $userData];
                    http_response_code($status);
                } catch (PDOException $e) {
                    $status = 500;
                    $message = 'Server error';
                    $data = ['error' => $e->getMessage()];
                    http_response_code($status);
                }
            }
        }
    }
}

// Final response
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
]);
