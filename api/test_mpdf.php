<?php
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

require_once $autoloadPath;

// Verify MPDF class exists
if (!class_exists('\Mpdf\Mpdf')) {
    die("MPDF class not found. Installation incomplete.<br>
         Run: <code>composer require mpdf/mpdf</code>");
}

try {
    // Configure temporary directory
    $tempDir = __DIR__ . '/../tmp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => $tempDir,
        'default_font' => 'dejavusans'
    ]);

    $mpdf->WriteHTML('<h1>Hello World</h1>');
    $mpdf->Output('test.pdf', 'D');

} catch (Exception $e) {
    echo '<h2>Error</h2>';
    echo '<pre>' . $e->getMessage() . '</pre>';
    echo '<p>Ensure you have properly installed MPDF using Composer.</p>';
}