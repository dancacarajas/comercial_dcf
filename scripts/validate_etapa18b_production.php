<?php

declare(strict_types=1);

/**
 * Smoke test HTTP — Etapa 18B em produção.
 * Executar de qualquer máquina com curl:
 *   php scripts/validate_etapa18b_production.php
 */

const BASE_URL = 'https://comercial.dancacarajas.com.br';

$checks = [
    ['GET', '/health', 200, 'health'],
    ['GET', '/login', 200, 'login'],
    ['GET', '/contract-templates', 302, 'contract-templates protegido'],
    ['GET', '/signature-requests', 302, 'signature-requests protegido'],
    ['GET', '/collector-applications', 302, 'collector-applications protegido'],
];

$pass = 0;
$fail = 0;

foreach ($checks as [$method, $path, $expected, $label]) {
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === $expected) {
        echo "[PASS] {$label} → {$code}\n";
        $pass++;
    } else {
        echo "[FAIL] {$label} → {$code} (esperado {$expected})\n";
        $fail++;
    }
}

$css = curl_init(BASE_URL . '/assets/css/contract-print.css');
curl_setopt_array($css, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
$cssBody = (string) curl_exec($css);
$cssCode = (int) curl_getinfo($css, CURLINFO_HTTP_CODE);
curl_close($css);

if ($cssCode === 200 && str_contains($cssBody, 'signature-proof')) {
    echo "[PASS] contract-print.css publicado\n";
    $pass++;
} else {
    echo "[FAIL] contract-print.css ausente ou incompleto\n";
    $fail++;
}

echo "\n=== RESUMO PRODUÇÃO 18B ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
