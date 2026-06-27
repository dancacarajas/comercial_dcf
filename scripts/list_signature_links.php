<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new App\Core\App($root))->boot();

$pdo = App\Core\Database::connection();
$base = 'http://localhost:8080';

$rows = $pdo->query(
    'SELECT sr.id, sr.title, sr.status, sr.source_id, ss.public_token, ss.status AS signer_status
       FROM signature_requests sr
       JOIN signature_signers ss ON ss.signature_request_id = sr.id
      WHERE sr.archived_at IS NULL
      ORDER BY sr.id DESC
      LIMIT 10'
)->fetchAll(PDO::FETCH_ASSOC);

echo "=== Links para visualizar no navegador (sem download) ===\n\n";

foreach ($rows as $r) {
    $token = (string) $r['public_token'];
    if ((string) $r['signer_status'] !== 'assinado') {
        echo "#{$r['id']} — {$r['title']} (pendente)\n";
        echo "Assinar: {$base}/assinatura/" . rawurlencode($token) . "\n\n";
        continue;
    }
    echo "#{$r['id']} — {$r['title']}\n";
    echo "Contrato (visualizar): {$base}/assinatura/" . rawurlencode($token) . "/documento\n";
    echo "Auditoria:             {$base}/assinatura/" . rawurlencode($token) . "/auditoria\n";
    echo "Confirmação:           {$base}/assinatura/" . rawurlencode($token) . "\n\n";
}
