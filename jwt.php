<?php
require 'config.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


$secret_key = "1234";
function createJWT($user)
{
    global $secret_key;

    $payload = [
        "iss" => "http://localhost",
        "aud" => "http://localhost",
        "iat" => time(),
        "exp" => time() + (60 * 60), // valid for 1 hour
        "data" => [
            "id" => $user['id'],
            "username" => $user['username'],
            "email" => $user['email'],
            'user_type' => $user['user_type'] ?? 'user',
        ]
    ];
    
    return JWT::encode($payload, $secret_key, 'HS256');
}

function validateJWT($token)
{
    global $secret_key;

    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return (array) $decoded->data;
    } catch (Exception $e) {
        return false;
    }
}
?>
