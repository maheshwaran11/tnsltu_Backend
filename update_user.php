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
            $name          = trim($dataInput->name ?? '');
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
            $category      = $dataInput->category ?? '';
            $notes         = $dataInput->notes ?? null;
            $member_id     = $dataInput->member_id ?? null;
            $relation_name = $dataInput->relation_name ?? null;
            $relation_type = $dataInput->relation_type ?? null;
            $card_status   = $dataInput->card_status ?? null;
            $card_type     = $dataInput->card_type ?? null;
            $subscription_number = $dataInput->subscription_number ?? null;
            $donation_number     = $dataInput->donation_number ?? null;

            $profilePhotoPath = null;

            if (!empty($profile_photo) && is_string($profile_photo)) {
                if (preg_match('/^data:image\/(\w+);base64,/', $profile_photo, $type)) {
                    // Base64 upload
                    $extension = strtolower($type[1]); // jpg, png, gif, etc.

                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $profile_photo = substr($profile_photo, strpos($profile_photo, ',') + 1);
                        $profile_photo = base64_decode($profile_photo);

                        if ($profile_photo !== false) {
                            $uploadDir = 'uploads/';
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }

                            $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $username . '_' . $email);
                            $fileName = strtolower($safeName) . '.' . $extension;
                            $filePath = $uploadDir . $fileName;

                            if (file_put_contents($filePath, $profile_photo)) {
                                $profilePhotoPath = $filePath;
                            }
                        }
                    }
                } elseif (preg_match('/^uploads\//', $profile_photo)) {
                    // Already a valid path in "uploads/"
                    $profilePhotoPath = $profile_photo;
                }
            }

            // If still null, keep old one
            if ($profilePhotoPath === null) {
                $stmtPhoto = $pdo->prepare("SELECT profile_photo FROM user_details WHERE user_id = ?");
                $stmtPhoto->execute([$id]);
                $existing = $stmtPhoto->fetchColumn();
                $profilePhotoPath = $existing ?: null;
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
                    // ✅ Update user_details
                    $stmt1 = $pdo->prepare("
                        UPDATE user_details 
                        SET user_type = ?, address = ?, address_tamil = ?, district = ?, taluk = ?, state = ?, zipcode = ?, 
                            phone = ?, gender = ?, dob = ?, status = ?, category = ?, notes = ?, profile_photo = ?, 
                            member_id = ?, name = ?, relation_name = ?, relation_type = ?, card_type = ?, card_status = ?,
                            subscription_number = ?, donation_number = ? 
                        WHERE user_id = ?
                    ");
                    $stmt1->execute([
                        $user_type, $address, $address_tamil, $district, $taluk, $state, $zipcode,
                        $phone, $gender, $dob, $status_value, $category, $notes,
                        $profilePhotoPath, $member_id, $name, $relation_name, $relation_type,
                        $card_type, $card_status, $subscription_number, $donation_number, $id
                    ]);

                    // ✅ Update users table only if changed & unique
                    $stmtCheck = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
                    $stmtCheck->execute([$id]);
                    $currentUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($currentUser) {
                        if ($username !== $currentUser['username'] || $email !== $currentUser['email']) {
                            // Check uniqueness in other rows
                            $stmtUnique = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                            $stmtUnique->execute([$username, $email, $id]);
                            $exists = $stmtUnique->fetchColumn();

                            if ($exists == 0) {
                                $stmt2 = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                                $stmt2->execute([$username, $email, $id]);
                            }
                            // else → skip update silently
                        }
                    }

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
