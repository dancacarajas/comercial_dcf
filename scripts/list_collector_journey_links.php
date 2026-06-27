<?php

declare(strict_types=1);

/**
 * Lista candidaturas e links públicos por etapa da esteira.
 * Uso: docker exec dcc_app php /var/www/html/scripts/list_collector_journey_links.php
 */

$root = dirname(__DIR__);

require $root . '/app/Core/App.php';
(new App\Core\App($root))->boot();

$pdo = App\Core\Database::connection();
$model = new App\Models\CollectorApplication();

$appConfig = require $root . '/config/app.php';
$baseUrl = rtrim((string) (getenv('VALIDATE_BASE_URL') ?: ($appConfig['url'] ?? 'http://localhost:8080')), '/');
if (file_exists('/.dockerenv') && !getenv('VALIDATE_BASE_URL')) {
    $baseUrl = 'http://localhost:8080';
}

$steps = $model->getJourneySteps();
$statuses = $model->getStatuses();

$rows = $pdo->query(
    'SELECT id, application_number, name, email, status, document_status, review_status, access_status,
            public_token, public_token_expires_at, public_token_revoked_at, archived_at
     FROM collector_applications
     ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

echo "=== Esteira de credenciamento — links públicos ===\n";
echo "Base: {$baseUrl}\n\n";

$byStep = array_fill_keys(array_keys($steps), []);

foreach ($rows as $row) {
    $step = $model->journeyStepKey($row);
    $token = trim((string) ($row['public_token'] ?? ''));
    $check = $token !== '' ? $model->validatePublicToken($row) : ['valid' => false, 'reason' => 'Sem token'];

    $byStep[$step][] = [
        'id' => (int) $row['id'],
        'number' => (string) $row['application_number'],
        'name' => (string) $row['name'],
        'status' => (string) $row['status'],
        'status_label' => $statuses[(string) $row['status']] ?? (string) $row['status'],
        'step' => $step,
        'token_valid' => !empty($check['valid']),
        'token_reason' => (string) ($check['reason'] ?? ''),
        'public_url' => $token !== '' && !empty($check['valid'])
            ? $baseUrl . '/captadores/credenciamento/' . rawurlencode($token)
            : null,
        'internal_url' => $baseUrl . '/collector-applications/' . (int) $row['id'],
    ];
}

foreach ($steps as $stepKey => $stepLabel) {
    echo "## {$stepLabel} ({$stepKey})\n";
    $items = $byStep[$stepKey] ?? [];
    if ($items === []) {
        echo "  (nenhuma candidatura nesta etapa)\n\n";
        continue;
    }

    foreach ($items as $item) {
        echo "  • #{$item['id']} {$item['number']} — {$item['name']}\n";
        echo "    Status: {$item['status_label']} ({$item['status']})\n";
        echo "    Interno: {$item['internal_url']}\n";
        if ($item['public_url']) {
            echo "    Público: {$item['public_url']}\n";
        } else {
            echo "    Público: indisponível ({$item['token_reason']})\n";
        }
        echo "\n";
    }
}

echo "=== Resumo — um link por etapa (público válido) ===\n\n";
foreach ($steps as $stepKey => $stepLabel) {
    $best = null;
    foreach ($byStep[$stepKey] ?? [] as $item) {
        if ($item['public_url']) {
            $best = $item;
            break;
        }
    }
    if ($best === null) {
        echo "{$stepLabel}: sem link público válido\n";
        continue;
    }
    echo "{$stepLabel}:\n  {$best['public_url']}\n  (status: {$best['status_label']})\n  Interno: {$best['internal_url']}\n\n";
}
