<?php
require __DIR__ . '/vendor/autoload.php';

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

// Load default config
$defaultConfig = (new ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

// Create mPDF instance with local fonts folder
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/fonts']), // local fonts folder
    'fontdata' => $fontData + [
        'latha' => [   // <-- give your font an alias
            'R' => 'Latha.ttf',  // <-- exact file name in fonts folder
        ],
    ],
    'default_font' => 'latha' // <-- set as default font
]);

$html = "
    <h2 style='text-align:center;'>வணக்கம்!</h2>
    <p>இது தமிழ் எழுத்துருவை (local font) பயன்படுத்தி உருவாக்கப்பட்டது.</p>
";

$mpdf->WriteHTML($html);
$mpdf->Output("tamil.pdf", "I"); // Show in browser
