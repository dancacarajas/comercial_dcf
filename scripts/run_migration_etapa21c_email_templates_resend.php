<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 21C (templates HTML e reenvio operacional).
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

echo "== ETAPA 21C - Templates HTML e Reenvio de E-mail ==\n\n";

$indexExists = static function (string $table, string $index) use ($pdo): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i');
    $st->execute(['t' => $table, 'i' => $index]);
    return (int) $st->fetchColumn() > 0;
};

if ($indexExists('email_outbox', 'uniq_email_outbox_event_entity_recipient')) {
    $pdo->exec('ALTER TABLE `email_outbox` DROP INDEX `uniq_email_outbox_event_entity_recipient`');
    echo "indice unico de outbox removido para permitir reenvio manual\n";
}
if (!$indexExists('email_outbox', 'idx_email_outbox_event_entity_recipient')) {
    $pdo->exec('ALTER TABLE `email_outbox` ADD KEY `idx_email_outbox_event_entity_recipient` (`event_key`, `entity_type`, `entity_id`, `recipient_type`, `recipient_email`)');
    echo "indice operacional de outbox garantido\n";
}

$permission = ['Logs de e-mail: reenviar', 'email_logs.resend', 'Reenviar e-mails transacionais registrados'];
$pdo->prepare(
    'INSERT INTO `permissions` (`name`, `slug`, `description`)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`)'
)->execute($permission);
$pdo->exec(
    "INSERT INTO `role_permissions` (`role_id`, `permission_id`)
     SELECT r.`id`, p.`id`
       FROM `roles` r
       JOIN `permissions` p ON p.`slug` = 'email_logs.resend'
      WHERE r.`slug` = 'administrador-geral'
     ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`"
);
echo "permissao email_logs.resend garantida\n";

$layout = static function (string $heading, string $kicker, string $bodyHtml, string $ctaLabel = '', string $ctaUrl = '', string $foot = ''): string {
    $button = '';
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $button = '<tr><td style="padding:18px 0 4px;"><a href="' . $ctaUrl . '" style="display:inline-block;background:#f4c400;color:#111111;text-decoration:none;font-weight:800;border-radius:999px;padding:13px 22px;font-family:Arial,sans-serif;">' . $ctaLabel . '</a></td></tr>';
    }
    $footHtml = $foot !== '' ? '<p style="margin:18px 0 0;color:#667085;font-size:13px;line-height:1.5;">' . $foot . '</p>' : '';

    return '<!doctype html><html><body style="margin:0;padding:0;background:#f5f7fb;color:#111827;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:28px 12px;">'
        . '<tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;font-family:Arial,sans-serif;">'
        . '<tr><td style="height:6px;background:#f4c400;"></td></tr>'
        . '<tr><td style="padding:26px 30px 18px;border-bottom:1px solid #eef0f3;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
        . '<td style="width:54px;"><div style="width:44px;height:44px;border-radius:50%;background:#15b66d;color:#ffffff;font-weight:900;font-size:13px;line-height:44px;text-align:center;">DC</div></td>'
        . '<td><div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#667085;font-weight:800;">' . $kicker . '</div>'
        . '<div style="font-size:20px;line-height:1.25;color:#111111;font-weight:900;margin-top:4px;">Dança Carajás Festival</div></td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:30px;">'
        . '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.2;color:#111111;font-weight:900;">' . $heading . '</h1>'
        . '<div style="font-size:16px;line-height:1.65;color:#263238;">' . $bodyHtml . '</div>'
        . '<table role="presentation" cellspacing="0" cellpadding="0">' . $button . '</table>'
        . $footHtml
        . '</td></tr>'
        . '<tr><td style="padding:18px 30px;background:#111111;color:#ffffff;font-size:12px;line-height:1.5;">'
        . '<strong>Dança Carajás Festival - Captação</strong><br>Mensagem transacional automática. Não compartilhe links de acesso ou assinatura.'
        . '</td></tr></table></td></tr></table></body></html>';
};

$templates = [
    'collector_application_received' => [
        'Manifestação recebida',
        'Credenciamento de captador',
        'Recebemos sua manifestação para atuar como captador do Dança Carajás.<br><br><strong>Número da candidatura:</strong> {{application_number}}<br><br>Nossa equipe fará a triagem inicial e avisará por e-mail quando houver uma nova etapa.',
        '',
        '',
    ],
    'collector_application_received_internal' => [
        'Nova manifestação recebida',
        'Equipe interna',
        '<strong>Nome:</strong> {{name}}<br><strong>E-mail:</strong> {{email}}<br><strong>Cidade/UF:</strong> {{city_state}}<br><strong>Candidatura:</strong> {{application_number}}',
        '',
        '',
    ],
    'collector_documents_requested' => [
        'Envio de documentos liberado',
        'Credenciamento de captador',
        'Para seguir com seu credenciamento, envie os documentos solicitados pelo link seguro abaixo.<br><br><strong>Documentos solicitados:</strong><br><span style="white-space:pre-line;">{{documents_list}}</span>',
        'Enviar documentos',
        '{{public_url}}',
    ],
    'collector_document_uploaded' => [
        'Documento recebido',
        'Credenciamento de captador',
        'Recebemos um documento no seu processo de credenciamento. Você pode acompanhar pendências e próximos passos pelo link seguro.',
        'Acompanhar credenciamento',
        '{{public_url}}',
    ],
    'collector_documents_completed' => [
        'Pacote documental completo',
        'Credenciamento de captador',
        'Recebemos todos os documentos solicitados. A equipe iniciará a análise documental e avisará por e-mail se houver necessidade de ajuste.',
        '',
        '',
    ],
    'collector_document_reviewed_pending_correction' => [
        'Ajuste necessário em documento',
        'Credenciamento de captador',
        'Identificamos uma pendência em documento do seu credenciamento.<br><br><strong>Observação da equipe:</strong><br>{{review_notes}}',
        'Corrigir documento',
        '{{public_url}}',
    ],
    'collector_application_approved' => [
        'Credenciamento aprovado',
        'Credenciamento de captador',
        'Seu credenciamento foi aprovado. A próxima etapa é a assinatura contratual. Você receberá o link de assinatura por e-mail.',
        '',
        '',
    ],
    'collector_application_rejected' => [
        'Resultado do credenciamento',
        'Credenciamento de captador',
        'Agradecemos seu interesse. Neste momento, seu credenciamento não foi aprovado.<br><br><strong>Motivo/observação:</strong> {{rejection_reason}}',
        '',
        '',
    ],
    'signature_request_sent' => [
        'Assinatura disponível',
        'Assinatura eletrônica',
        'Seu documento de assinatura está disponível. Confira os dados antes de assinar.',
        'Assinar documento',
        '{{signature_url}}',
    ],
    'collector_access_released' => [
        'Acesso liberado',
        'Portal do captador',
        'Seu acesso ao sistema Dança Carajás Captação foi liberado. Use o link abaixo para concluir seu cadastro de usuário e senha.',
        'Concluir cadastro',
        '{{public_url}}',
    ],
];

$fallbacks = [
    'collector_application_triage_started' => ['Sua manifestação está em triagem', 'Credenciamento de captador', 'Sua manifestação entrou em triagem inicial. Avisaremos por e-mail quando houver nova etapa.', '', ''],
    'collector_application_in_document_review' => ['Documentos em análise', 'Credenciamento de captador', 'Seus documentos estão em análise pela equipe Dança Carajás.', '', ''],
    'collector_application_adjustments_requested' => ['Ajustes solicitados', 'Credenciamento de captador', 'Precisamos de ajustes no seu credenciamento.<br><br><strong>Observação da equipe:</strong><br>{{review_notes}}', 'Acessar credenciamento', '{{public_url}}'],
    'collector_documents_completed_internal' => ['Captador pronto para análise', 'Equipe interna', 'Pacote documental completo para análise.<br><strong>Nome:</strong> {{name}}<br><strong>Candidatura:</strong> {{application_number}}', '', ''],
    'collector_contract_signed' => ['Assinatura recebida', 'Assinatura eletrônica', 'Recebemos sua assinatura eletrônica.', '', ''],
    'collector_contract_signed_internal' => ['Captador assinou contrato', 'Equipe interna', 'O captador {{name}} assinou o contrato.<br>Candidatura: {{application_number}}', '', ''],
    'collector_contract_fully_signed' => ['Contrato concluído', 'Assinatura eletrônica', 'Todas as assinaturas foram concluídas.', 'Visualizar documento', '{{signature_url}}'],
    'collector_access_self_registered' => ['Acesso criado com sucesso', 'Portal do captador', 'Seu acesso foi criado com sucesso. Acesse o sistema com seu e-mail e senha.', 'Acessar sistema', '{{login_url}}'],
    'collector_access_self_registered_internal' => ['Captador concluiu acesso', 'Equipe interna', 'O captador {{name}} concluiu o cadastro de acesso.<br>Candidatura: {{application_number}}', '', ''],
];
$templates += $fallbacks;

$tpl = new \App\Models\EmailTemplate();
foreach ($templates as $eventKey => [$heading, $kicker, $body, $ctaLabel, $ctaUrl]) {
    $tpl->upsert([
        'event_key' => $eventKey,
        'name' => $heading,
        'subject' => $heading . ' - Danca Carajas',
        'body_text' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)) . ($ctaUrl !== '' ? "\n\nLink: {$ctaUrl}" : ''),
        'body_html' => $layout($heading, $kicker, $body, $ctaLabel, $ctaUrl),
        'enabled' => 1,
    ]);
}
echo 'templates HTML atualizados: ' . count($templates) . "\n";

echo "\nMigration ETAPA 21C concluida.\n";
