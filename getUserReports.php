<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require 'config.php'; // mysqli connection ($conn)

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = [
    "status" => 400,
    "message" => "Something went wrong",
    "data" => null
];

try {
    // ✅ Fetch all users with joined details and id card
    $sql = "
        SELECT 
            u.id AS user_id, 
            u.username, 
            u.email, 
            u.password, 
            u.created_at,

            d.user_type, d.name, d.relation_type, d.phone, d.gender, d.dob, 
            d.address, d.district, d.taluk, d.pincode, d.profile_photo,

            i.id AS id_card_id, i.card_number, i.issue_date, i.expiry_date
        FROM users u
        LEFT JOIN user_details d ON u.id = d.user_id
        LEFT JOIN user_id_cards i ON u.id = i.user_id
        ORDER BY u.id ASC
    ";

    $result = $conn->query($sql);
    $users = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $userId = $row['user_id'];

            // ✅ Fetch family members for this user
            $familySql = "SELECT id, name, relation, education, claim_type, current_status 
                          FROM family_members 
                          WHERE user_id = ?";
            $stmt = $conn->prepare($familySql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $familyRes = $stmt->get_result();

            $familyMembers = [];
            while ($f = $familyRes->fetch_assoc()) {
                $familyMembers[] = $f;
            }
            $stmt->close();

            // ✅ Build user report object with ALL fields
            $users[] = [
                "id" => $row['user_id'],
                "username" => $row['username'],
                "email" => $row['email'],
                "password" => $row['password'], // ⚠️ only include if needed!
                "created_at" => $row['created_at'],

                "details" => [
                    "user_type" => $row['user_type'],
                    "name" => $row['name'],
                    "relation_type" => $row['relation_type'],
                    "phone" => $row['phone'],
                    "gender" => $row['gender'],
                    "dob" => $row['dob'],
                    "address" => $row['address'],
                    "district" => $row['district'],
                    "taluk" => $row['taluk'],
                    "pincode" => $row['pincode'],
                    "profile_photo" => $row['profile_photo']
                ],

                "id_card" => [
                    "id" => $row['id_card_id'],
                    "card_number" => $row['card_number'],
                    "issue_date" => $row['issue_date'],
                    "expiry_date" => $row['expiry_date']
                ],

                "family_members" => $familyMembers
            ];
        }

        $response["status"] = 200;
        $response["message"] = "Reports fetched successfully";
        $response["data"] = $users;
    } else {
        $response["status"] = 404;
        $response["message"] = "No users found";
    }

} catch (Exception $e) {
    $response["status"] = 500;
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
