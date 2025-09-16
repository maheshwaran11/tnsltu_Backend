<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require 'config.php'; // PDO connection ($pdo)

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
    // ✅ Fetch users whose next_renewal_date is within next 30 days
    $sql = "
        SELECT 
            u.id AS user_id, 
            u.username, 
            u.email, 
            u.created_at,

            d.name, d.phone, d.gender, d.address, d.district, d.taluk, d.zipcode,

            i.id AS id_card_id, 
            i.card_number, 
            i.issue_date, 
            i.expiry_date, 
            i.next_renewal_date,
            DATEDIFF(i.next_renewal_date, CURDATE()) AS days_left
        FROM users u
        LEFT JOIN user_details d ON u.id = d.user_id
        LEFT JOIN user_id_card i ON u.id = i.user_id
        WHERE i.next_renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY i.next_renewal_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];

    foreach ($rows as $row) {
        $notifications[] = [
            "user_id" => $row["user_id"],
            "username" => $row["username"],
            "email" => $row["email"],
            "created_at" => $row["created_at"],

            "details" => [
                "name" => $row["name"],
                "phone" => $row["phone"],
                "gender" => $row["gender"],
                "address" => $row["address"],
                "district" => $row["district"],
                "taluk" => $row["taluk"],
                "zipcode" => $row["zipcode"]
            ],

            "id_card" => [
                "id" => $row["id_card_id"],
                "card_number" => $row["card_number"],
                "issue_date" => $row["issue_date"],
                "expiry_date" => $row["expiry_date"],
                "next_renewal_date" => $row["next_renewal_date"],
                "days_left" => $row["days_left"]
            ]
        ];
    }

    if (count($notifications) > 0) {
        $response["status"] = 200;
        $response["message"] = "Upcoming renewals fetched successfully";
        $response["data"] = $notifications;
    } else {
        $response["status"] = 404;
        $response["message"] = "No upcoming renewals within 30 days";
    }

} catch (Exception $e) {
    $response["status"] = 500;
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
