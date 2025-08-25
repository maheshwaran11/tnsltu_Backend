<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

require 'config.php'; // DB connection ($pdo)

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize default response
$status = 400;
$message = 'Invalid request';
$responseData = null;

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email) {
    $message = 'Email is required';
} else {
    try {
        // 1. Check if the email exists
        $stmt = $pdo->prepare("
            SELECT u.id, u.email 
            FROM users u 
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'No account found with that email';
        } else {
            // 2. Generate a reset token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 3. Store in password_resets table (create it if not done)
            $insert = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $insert->execute([$user['id'], $token, $expiresAt]);

            // 4. Send email (simple mail - for real use, prefer PHPMailer/SMTP)
            $resetUrl = "https://tnsltu.in/reset-password?token=$token";
            $subject = "Password Reset Request";
            $messageBody = "Click the link to reset your password: $resetUrl\n\nIf you didn't request it, please ignore.";
            $headers = "From: noreply@tnsltu.in";

            // Uncomment below to actually send in production
            // mail($user['email'], $subject, $messageBody, $headers);

            $status = 200;
            $message = 'Reset link sent to your email address';
        }

    } catch (PDOException $e) {
        $status = 500;
        $message = 'Database error: ' . $e->getMessage();
    }
}

// Return final response
http_response_code($status);
echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $responseData
]);
