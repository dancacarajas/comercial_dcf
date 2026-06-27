<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';

(new App\Core\App($root))->boot();

use App\Services\ContractPdfDocument;
use App\Services\PdfImageLoader;

$publicRoot = $root . '/public/';
$festival = $publicRoot . 'assets/img/branding/danca-carajas-logo.png';
$producer = $publicRoot . 'assets/img/branding/ja-producoes-logo.png';

foreach (['festival' => $festival, 'producer' => $producer] as $label => $path) {
    $img = PdfImageLoader::fromFile($path);
    echo $label . ': ' . ($img === null ? 'FAIL' : "OK {$img['width']}x{$img['height']}") . PHP_EOL;
}

$pdf = new ContractPdfDocument();
$pdf->setBranding([
    'festival_name'       => 'Dança Carajás Festival',
    'producer_name'       => 'JA Produções',
    'producer_legal_line' => 'JA Produções — Responsável jurídica pela contratação',
    'footer_line'         => 'Dança Carajás Captação',
    'festival_logo_path'  => $festival,
    'producer_logo_path'  => $producer,
]);
$pdf->setMeta([
    'title'     => 'Contrato de Credenciamento de Captador',
    'reference' => 'Candidatura nº 1 · Assinatura nº 1',
    'issued_at' => date('d/m/Y H:i:s'),
]);
$pdf->addSpacer(4.0);
$pdf->addHeading('Cláusula 1 — Do objeto');
$pdf->addParagraph(
    'O presente instrumento tem por objeto regular a participação do CAPTADOR no processo '
    . 'de credenciamento do Dança Carajás Festival, conforme edital vigente e normas internas.'
);
$pdf->addHeading('Cláusula 2 — Das obrigações');
$pdf->addParagraph(
    'O CAPTADOR compromete-se a observar as diretrizes de conduta, prazos e procedimentos '
    . 'estabelecidos pela organização do festival e pela produtora responsável.'
);
$pdf->addSpacer(12.0);
$pdf->setSignatureStamp([
    'signer_name'       => 'Maria Demo Captadora',
    'signer_document'   => '123.456.789-00',
    'signed_at'         => date('d/m/Y H:i:s'),
    'verification_code' => 'DEMO1234ABCD5678',
    'audit_url'         => 'http://localhost:8080/assinatura/demo/auditoria',
]);

$out = $root . '/storage/uploads/signatures/preview-contrato-profissional.pdf';
$pdf->saveToFile($out);
echo 'PDF: ' . $out . ' (' . filesize($out) . ' bytes)' . PHP_EOL;
echo str_starts_with(file_get_contents($out) ?: '', '%PDF') ? 'PDF header OK' : 'PDF header FAIL';
echo PHP_EOL;
