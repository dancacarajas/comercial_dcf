<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 21B (gatilhos de e-mail da trilha de captadores).
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== ETAPA 21B - Gatilhos de E-mail da Trilha de Captadores ==\n\n";

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c');
    $st->execute(['t' => $table, 'c' => $column]);
    return (int) $st->fetchColumn() > 0;
};

$indexExists = static function (string $table, string $index) use ($pdo): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i');
    $st->execute(['t' => $table, 'i' => $index]);
    return (int) $st->fetchColumn() > 0;
};

foreach (['email_outbox', 'email_logs'] as $table) {
    if (!$columnExists($table, 'entity_type')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `entity_type` VARCHAR(80) NULL DEFAULT NULL AFTER `event_key`");
    }
    if (!$columnExists($table, 'entity_id')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `entity_type`");
    }
    if (!$columnExists($table, 'recipient_type')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `recipient_type` VARCHAR(40) NULL DEFAULT NULL AFTER `entity_id`");
    }
    if (!$indexExists($table, "idx_{$table}_entity")) {
        $pdo->exec("ALTER TABLE `{$table}` ADD KEY `idx_{$table}_entity` (`entity_type`, `entity_id`, `event_key`)");
    }
    echo "rastreio garantido em {$table}\n";
}
if (!$indexExists('email_outbox', 'uniq_email_outbox_event_entity_recipient')) {
    $pdo->exec(
        'ALTER TABLE `email_outbox`
            ADD UNIQUE KEY `uniq_email_outbox_event_entity_recipient`
            (`event_key`, `entity_type`, `entity_id`, `recipient_type`, `recipient_email`)'
    );
    echo "idempotencia garantida em email_outbox\n";
}

$templates = [
    'collector_application_received' => [
        'Manifestacao recebida',
        'Recebemos sua manifestacao para ser captador do Danca Carajas',
        "Olá, {{name}}.\n\nRecebemos sua manifestação para atuar como captador do Dança Carajás.\n\nNúmero da candidatura: {{application_number}}\n\nNossa equipe fará a triagem inicial e você receberá novas orientações por e-mail.\n\nEquipe Dança Carajás",
        '<p>Olá, {{name}}.</p><p>Recebemos sua manifestação para atuar como captador do Dança Carajás.</p><p><strong>Número da candidatura:</strong> {{application_number}}</p><p>Nossa equipe fará a triagem inicial e você receberá novas orientações por e-mail.</p><p>Equipe Dança Carajás</p>',
    ],
    'collector_application_received_internal' => [
        'Nova manifestacao de captador',
        'Nova manifestacao de captador recebida',
        "Nova manifestação recebida.\n\nNome: {{name}}\nE-mail: {{email}}\nCidade/UF: {{city_state}}\nCandidatura: {{application_number}}",
        '<p>Nova manifestação recebida.</p><p><strong>Nome:</strong> {{name}}<br><strong>E-mail:</strong> {{email}}<br><strong>Cidade/UF:</strong> {{city_state}}<br><strong>Candidatura:</strong> {{application_number}}</p>',
    ],
    'collector_application_triage_started' => [
        'Triagem iniciada',
        'Sua manifestacao esta em triagem',
        "Olá, {{name}}.\n\nSua manifestação entrou em triagem inicial. Avisaremos por e-mail quando houver nova etapa.",
        '<p>Olá, {{name}}.</p><p>Sua manifestação entrou em triagem inicial. Avisaremos por e-mail quando houver nova etapa.</p>',
    ],
    'collector_documents_requested' => [
        'Solicitacao de documentos',
        'Envie seus documentos para seguir no credenciamento',
        "Olá, {{name}}.\n\nPara seguir com seu credenciamento, envie os documentos pelo link seguro abaixo:\n\n{{public_url}}\n\nDocumentos solicitados:{{documents_list}}\n\nEquipe Dança Carajás",
        '<p>Olá, {{name}}.</p><p>Para seguir com seu credenciamento, envie os documentos pelo link seguro abaixo:</p><p><a href="{{public_url}}">{{public_url}}</a></p><p><strong>Documentos solicitados:</strong></p><pre>{{documents_list}}</pre><p>Equipe Dança Carajás</p>',
    ],
    'collector_document_uploaded' => [
        'Documento recebido',
        'Documento recebido no credenciamento',
        "Olá, {{name}}.\n\nRecebemos um documento no seu credenciamento. Você pode acompanhar pendências pelo link:\n\n{{public_url}}",
        '<p>Olá, {{name}}.</p><p>Recebemos um documento no seu credenciamento.</p><p><a href="{{public_url}}">Acompanhar credenciamento</a></p>',
    ],
    'collector_documents_completed' => [
        'Documentos completos',
        'Documentos recebidos - analise sera iniciada',
        "Olá, {{name}}.\n\nRecebemos o pacote documental completo. A equipe iniciará a análise e avisará se houver necessidade de ajuste.",
        '<p>Olá, {{name}}.</p><p>Recebemos o pacote documental completo. A equipe iniciará a análise e avisará se houver necessidade de ajuste.</p>',
    ],
    'collector_documents_completed_internal' => [
        'Pacote documental completo',
        'Captador pronto para analise documental',
        "Pacote documental completo para análise.\n\nNome: {{name}}\nCandidatura: {{application_number}}",
        '<p>Pacote documental completo para análise.</p><p><strong>Nome:</strong> {{name}}<br><strong>Candidatura:</strong> {{application_number}}</p>',
    ],
    'collector_document_reviewed_pending_correction' => [
        'Ajuste em documento',
        'Ajuste necessario em documento do credenciamento',
        "Olá, {{name}}.\n\nIdentificamos uma pendência em documento do credenciamento.\n\nObservação da equipe:\n{{review_notes}}\n\nAcesse o link para corrigir:\n{{public_url}}",
        '<p>Olá, {{name}}.</p><p>Identificamos uma pendência em documento do credenciamento.</p><p><strong>Observação da equipe:</strong><br>{{review_notes}}</p><p><a href="{{public_url}}">Corrigir documento</a></p>',
    ],
    'collector_application_in_document_review' => [
        'Analise documental',
        'Seus documentos estao em analise',
        "Olá, {{name}}.\n\nSeus documentos estão em análise pela equipe Dança Carajás.",
        '<p>Olá, {{name}}.</p><p>Seus documentos estão em análise pela equipe Dança Carajás.</p>',
    ],
    'collector_application_adjustments_requested' => [
        'Ajustes solicitados',
        'Precisamos de ajustes no seu credenciamento',
        "Olá, {{name}}.\n\nPrecisamos de ajustes no seu credenciamento.\n\nObservação da equipe:\n{{review_notes}}\n\nAcesse: {{public_url}}",
        '<p>Olá, {{name}}.</p><p>Precisamos de ajustes no seu credenciamento.</p><p><strong>Observação da equipe:</strong><br>{{review_notes}}</p><p><a href="{{public_url}}">Acessar credenciamento</a></p>',
    ],
    'collector_application_rejected' => [
        'Candidatura reprovada',
        'Resultado do credenciamento de captador',
        "Olá, {{name}}.\n\nAgradecemos seu interesse. Neste momento, seu credenciamento não foi aprovado.\n\nMotivo/observação: {{rejection_reason}}\n\nEquipe Dança Carajás",
        '<p>Olá, {{name}}.</p><p>Agradecemos seu interesse. Neste momento, seu credenciamento não foi aprovado.</p><p><strong>Motivo/observação:</strong> {{rejection_reason}}</p><p>Equipe Dança Carajás</p>',
    ],
    'collector_application_approved' => [
        'Candidatura aprovada',
        'Seu credenciamento foi aprovado',
        "Olá, {{name}}.\n\nSeu credenciamento foi aprovado. A próxima etapa é a assinatura contratual. Você receberá o link de assinatura por e-mail.",
        '<p>Olá, {{name}}.</p><p>Seu credenciamento foi aprovado. A próxima etapa é a assinatura contratual. Você receberá o link de assinatura por e-mail.</p>',
    ],
    'signature_request_sent' => [
        'Assinatura enviada',
        'Assine seu termo de autorizacao de captacao',
        "Olá, {{name}}.\n\nSeu documento de assinatura está disponível no link abaixo:\n\n{{signature_url}}\n\nConfira os dados antes de assinar.",
        '<p>Olá, {{name}}.</p><p>Seu documento de assinatura está disponível no link abaixo:</p><p><a href="{{signature_url}}">Assinar documento</a></p><p>Confira os dados antes de assinar.</p>',
    ],
    'collector_contract_signed' => [
        'Assinatura recebida',
        'Assinatura recebida',
        "Olá, {{name}}.\n\nRecebemos sua assinatura eletrônica.",
        '<p>Olá, {{name}}.</p><p>Recebemos sua assinatura eletrônica.</p>',
    ],
    'collector_contract_signed_internal' => [
        'Captador assinou contrato',
        'Captador assinou o contrato',
        "O captador {{name}} assinou o contrato.\nCandidatura: {{application_number}}",
        '<p>O captador {{name}} assinou o contrato.</p><p>Candidatura: {{application_number}}</p>',
    ],
    'collector_contract_fully_signed' => [
        'Contrato concluido',
        'Contrato concluido',
        "Olá, {{name}}.\n\nTodas as assinaturas foram concluídas. Documento: {{signature_url}}",
        '<p>Olá, {{name}}.</p><p>Todas as assinaturas foram concluídas.</p><p><a href="{{signature_url}}">Visualizar documento</a></p>',
    ],
    'collector_access_released' => [
        'Acesso liberado',
        'Acesso liberado ao sistema Danca Carajas Captacao',
        "Olá, {{name}}.\n\nSeu acesso ao sistema Dança Carajás Captação foi liberado.\n\nUse o link abaixo para concluir seu cadastro de usuário e senha:\n{{public_url}}",
        '<p>Olá, {{name}}.</p><p>Seu acesso ao sistema Dança Carajás Captação foi liberado.</p><p><a href="{{public_url}}">Concluir cadastro</a></p>',
    ],
    'collector_access_self_registered' => [
        'Acesso criado',
        'Seu acesso foi criado com sucesso',
        "Olá, {{name}}.\n\nSeu acesso foi criado com sucesso. Acesse o sistema em {{login_url}}.",
        '<p>Olá, {{name}}.</p><p>Seu acesso foi criado com sucesso.</p><p><a href="{{login_url}}">Acessar sistema</a></p>',
    ],
    'collector_access_self_registered_internal' => [
        'Captador concluiu acesso',
        'Captador concluiu acesso ao sistema',
        "O captador {{name}} concluiu o cadastro de acesso.\nCandidatura: {{application_number}}",
        '<p>O captador {{name}} concluiu o cadastro de acesso.</p><p>Candidatura: {{application_number}}</p>',
    ],
];

$tpl = new \App\Models\EmailTemplate();
foreach ($templates as $key => [$name, $subject, $bodyText, $bodyHtml]) {
    $tpl->upsert([
        'event_key' => $key,
        'name' => $name,
        'subject' => $subject,
        'body_text' => $bodyText,
        'body_html' => $bodyHtml,
        'enabled' => 1,
    ]);
}
echo 'templates/gatilhos garantidos: ' . count($templates) . "\n";

$ruleStmt = $pdo->prepare(
    'INSERT INTO `email_event_rules` (`event_key`, `template_event_key`, `recipient_type`, `enabled`, `created_at`, `updated_at`)
     VALUES (:event_key, :template_event_key, :recipient_type, 1, NOW(), NOW())
     ON DUPLICATE KEY UPDATE `template_event_key` = VALUES(`template_event_key`), `enabled` = VALUES(`enabled`), `updated_at` = NOW()'
);
foreach ($templates as $eventKey => $_) {
    $recipientType = str_ends_with($eventKey, '_internal') ? 'equipe' : 'captador';
    $ruleStmt->execute([
        'event_key' => $eventKey,
        'template_event_key' => $eventKey,
        'recipient_type' => $recipientType,
    ]);
}
echo "regras de eventos garantidas\n";

echo "\nMigration ETAPA 21B concluida.\n";
