<?php
// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require 'vendor/autoload.php';
require 'vendor/setasign/fpdf/fpdf.php'; // Ensure FPDF is installed via Composer

// Create ID Cards folder if not exists
$folder = "idcards/";
if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

// Get data from request


$data = json_decode(file_get_contents("php://input"));

$id_card_name      = trim($data->id_card_name ?? '');
$position          = trim($data->position ?? '');
$occupation        = trim($data->occupation ?? '');
$member_id         = trim($data->member_id ?? '');
$registration_date = trim($data->registration_date ?? '');
$next_renewal_date = trim($data->next_renewal_date ?? '');
$user_id           = intval($data->user_id ?? 0);
$phone             = trim($data->phone ?? '');
$profile_photo     = trim($data->profile_photo ?? '');

// Background image (should be a PNG/JPG in your project)
$backgroundImage = "./assets/bac.jpg"; // Your background image
// $sign = "./assets/sign.jpg"; // Your signature image (optional)

$fileName = $folder . "ID_" . $member_id . ".pdf";

class PDF extends FPDF {
    var $bgImage;

    function setBackground($img) {
        $this->bgImage = $img;
    }

    function Header() {
        if ($this->bgImage) {
            // Background covers full card
            $this->Image($this->bgImage, 0, 0, 140, 94);
        }
    }

    function Footer() {
        // No footer, prevent adding extra content
    }
}

$pdf = new PDF('L', 'mm', array(140, 94)); // ID card size
$pdf->setBackground($backgroundImage);
$pdf->AddPage();

// All text on the first page
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

// Member ID
$pdf->SetXY(63, 43);
$pdf->Cell(100, 5, $member_id, 0, 0); // ln=0 so it stays in place

// Name
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetXY(63, 47.2);
$pdf->Cell(100, 5, $id_card_name, 0, 0);

// Occupation
$pdf->SetXY(63, 51.9);
$pdf->Cell(100, 5, $occupation, 0, 0);

// Position
$pdf->SetXY(63, 56.7);
$pdf->Cell(100, 5, $position, 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetXY(63, 61.3);
$pdf->Cell(100, 5, $next_renewal_date, 0, 0);


$pdf->SetFont('Arial', 'B', 9);
$pdf->SetXY(63, 66);
$pdf->Cell(100, 5, $phone, 0, 0);

// $pdf->Image($sign, 100, 58, 30, 15); // Signature image

if($profile_photo != '') {
    $pdf->Image($profile_photo, 6, 40, 25, 30); // Profile photo
}
// // Next Renewal
// $pdf->SetXY(54, 40);
// $pdf->Cell(50, 5, $next_renewal_date, 0, 0);

// // Phone
// $pdf->SetXY(54, 43);
// $pdf->Cell(50, 5, $phone, 0, 0);

$pdf->Output('F', $fileName);

// Return JSON response
echo json_encode([
    "status" => "success",
    "message" => "ID card generated",
    "download_url" => "http://localhost/api/$fileName"
    // "download_url" => "https://tnsltu.in/api/$fileName"
]);
