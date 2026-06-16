#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

$html = '<!DOCTYPE html><html><body><h1>Test Brief</h1><p>PDF export verification.</p></body></html>';

if (! class_exists(\Dompdf\Dompdf::class)) {
    fwrite(STDERR, "FAIL: dompdf not installed\n");
    exit(1);
}

$dompdf = new \Dompdf\Dompdf;
$dompdf->loadHtml($html);
$dompdf->setPaper('letter');
$dompdf->render();
$output = $dompdf->output();

if (strlen($output) < 1000 || ! str_starts_with($output, '%PDF')) {
    fwrite(STDERR, 'FAIL: invalid PDF output ('.strlen($output)." bytes)\n");
    exit(1);
}

echo 'OK: PDF generated ('.strlen($output)." bytes, starts with %PDF)\n";

$docxGen = new App\Services\DocxGenerator;
$docx = $docxGen->fromHtml($html);
if (strlen($docx) < 500 || ! str_starts_with($docx, 'PK')) {
    fwrite(STDERR, 'FAIL: invalid DOCX output ('.strlen($docx)." bytes)\n");
    exit(1);
}

echo 'OK: DOCX generated ('.strlen($docx)." bytes, ZIP/PK header)\n";
