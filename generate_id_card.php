<?php
// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
define('_SYSTEM_TTFONTS', __DIR__ . '/fonts/');
// Use tFPDF (UTF-8 supported version of FPDF)
require 'vendor/autoload.php'; 

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

$fileName = $folder . "ID_" . $member_id . ".pdf";

// Extend tFPDF class for background
class PDF extends tFPDF {
    var $bgImage;

    function setBackground($img) {
        $this->bgImage = $img;
    }

    function Header() {
        if ($this->bgImage) {
            $this->Image($this->bgImage, 0, 0, 140, 94);
        }
    }

    function Footer() {
        // No footer
    }
}

$pdf = new PDF('L', 'mm', [140, 94]);
$pdf->setBackground($backgroundImage);
$pdf->AddPage();

// Load Tamil font
$pdf->AddFont('NotoSansTamil','','nt.ttf',true);
$pdf->SetFont('NotoSansTamil','',9);
$pdf->SetTextColor(0, 0, 0);

// Member ID
$pdf->SetXY(63, 43);
$pdf->Cell(100, 5, $member_id, 0, 0, 'L');

// Name (Tamil OK)
$pdf->SetXY(63, 47.2);
$pdf->Cell(100, 5, $id_card_name, 0, 0, 'L');

// Occupation (Tamil OK)
$pdf->SetXY(63, 51.9);
$pdf->Cell(100, 5, $occupation, 0, 0, 'L');

// Position (Tamil OK)
$pdf->SetXY(63, 56.7);
$pdf->Cell(100, 5, $position, 0, 0, 'L');

// Next renewal date
$pdf->SetXY(63, 61.3);
$pdf->Cell(100, 5, $next_renewal_date, 0, 0, 'L');

// Phone
$pdf->SetXY(63, 66);
$pdf->Cell(100, 5, $phone, 0, 0, 'L');

// Profile photo (if available)
if($profile_photo != '') {
    $pdf->Image($profile_photo, 6, 40, 25, 30);
}

// Save PDF
$pdf->Output('F', $fileName);

// Return JSON response
echo json_encode([
    "status" => "success",
    "message" => "ID card generated",
    // "download_url" => "https://tnsltu.in/api/$fileName"
    "download_url" => "http://localhost/api/$fileName"
]);
