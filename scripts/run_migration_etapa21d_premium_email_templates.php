<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 21D (templates premium de e-mail transacional).
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

echo "== ETAPA 21D - Templates Premium de E-mail ==\n\n";

$layout = static function (
    string $eyebrow,
    string $title,
    string $summary,
    string $body,
    string $icon,
    string $ctaLabel = '',
    string $ctaUrl = '',
    string $note = ''
): string {
    $cta = $ctaLabel !== '' && $ctaUrl !== ''
        ? '<tr><td style="padding-top:24px;"><a href="' . $ctaUrl . '" style="display:inline-block;background:#f4c400;color:#111111;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:15px;line-height:1;border-radius:999px;padding:15px 24px;border:1px solid #d7ae00;">' . $ctaLabel . '</a></td></tr>'
        : '';
    $noteHtml = $note !== ''
        ? '<tr><td style="padding-top:18px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff8d6;border:1px solid #f1d46a;border-radius:12px;"><tr><td style="padding:14px 16px;color:#3d3420;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.55;"><strong style="color:#111111;">Atenção:</strong> ' . $note . '</td></tr></table></td></tr>'
        : '';

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body class="dcx-email-premium" style="margin:0;padding:0;background:#eef1f6;color:#121212;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef1f6;padding:30px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #dfe4ec;border-radius:18px;overflow:hidden;box-shadow:0 18px 48px rgba(17,24,39,.10);">'
        . '<tr><td style="height:7px;background:#f4c400;font-size:0;line-height:0;">&nbsp;</td></tr>'
        . '<tr><td style="padding:24px 30px 20px;background:#ffffff;border-bottom:1px solid #eceff4;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
        . '<td align="left" style="vertical-align:middle;"><img src="{{festival_logo_url}}" width="92" alt="Dança Carajás Festival" style="display:block;max-width:92px;height:auto;border:0;"></td>'
        . '<td align="center" style="vertical-align:middle;padding:0 16px;"><div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.13em;color:#667085;">' . $eyebrow . '</div><div style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;line-height:1.2;color:#111111;margin-top:5px;">Dança Carajás Festival</div></td>'
        . '<td align="right" style="vertical-align:middle;"><img src="{{producer_logo_url}}" width="118" alt="JA Produções" style="display:block;max-width:118px;height:auto;border:0;"></td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:30px 30px 8px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
        . '<td style="width:54px;vertical-align:top;"><div style="width:42px;height:42px;border-radius:14px;background:#111111;color:#f4c400;text-align:center;line-height:42px;font-family:Arial,Helvetica,sans-serif;font-size:21px;font-weight:900;">' . $icon . '</div></td>'
        . '<td style="vertical-align:top;"><h1 style="margin:0;color:#111111;font-family:Arial,Helvetica,sans-serif;font-size:28px;line-height:1.16;font-weight:900;letter-spacing:-.01em;">' . $title . '</h1><p style="margin:10px 0 0;color:#4b5563;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;">' . $summary . '</p></td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:14px 30px 28px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fafafa;border:1px solid #eceff4;border-radius:16px;"><tr><td style="padding:22px 24px;color:#263238;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.7;">' . $body . '</td></tr></table>'
        . '<table role="presentation" cellspacing="0" cellpadding="0">' . $cta . $noteHtml . '</table>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:26px;"><tr>'
        . '<td style="padding:14px 16px;border-radius:14px;background:#f6f7f9;color:#5f6b7a;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.55;">'
        . '<span style="display:inline-block;margin-right:10px;color:#111111;font-weight:800;">✓ E-mail transacional</span>'
        . '<span style="display:inline-block;margin-right:10px;color:#111111;font-weight:800;">🔒 Link seguro</span>'
        . '<span style="display:inline-block;color:#111111;font-weight:800;">☑ Registro auditável</span>'
        . '<br>Em caso de dúvida, responda este e-mail ou fale com a equipe Dança Carajás.</td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:22px 30px;background:#111111;color:#ffffff;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
        . '<td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;"><strong style="color:#f4c400;">Dança Carajás Festival - Captação</strong><br>Operação institucional por JA Produções Artísticas.</td>'
        . '<td align="right" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#cbd5e1;">Mensagem automática<br>Não compartilhe links de acesso.</td>'
        . '</tr></table></td></tr>'
        . '</table></td></tr></table></body></html>';
};

$templates = [
    'collector_application_received' => ['Credenciamento de captador', 'Manifestação recebida', 'Seu cadastro inicial entrou na esteira de credenciamento.', 'Olá, <strong>{{name}}</strong>.<br><br>Recebemos sua manifestação para atuar como captador do Dança Carajás.<br><br><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:8px;"><tr><td style="padding:14px 16px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;"><span style="color:#667085;font-size:12px;text-transform:uppercase;font-weight:900;letter-spacing:.08em;">Número da candidatura</span><br><strong style="font-size:18px;color:#111111;">{{application_number}}</strong></td></tr></table><br>Nossa equipe fará a triagem inicial e avisará por e-mail quando houver nova etapa.', '✓', '', '', 'Este e-mail confirma apenas o recebimento da manifestação. A atuação como captador depende das próximas fases de credenciamento.'],
    'collector_application_received_internal' => ['Equipe interna', 'Nova manifestação recebida', 'Há uma nova candidatura de captador para triagem.', '<strong>Nome:</strong> {{name}}<br><strong>E-mail:</strong> {{email}}<br><strong>Cidade/UF:</strong> {{city_state}}<br><strong>Candidatura:</strong> {{application_number}}', '★', '', '', 'Acesse o painel interno para avaliar a candidatura.'],
    'collector_application_triage_started' => ['Credenciamento de captador', 'Triagem iniciada', 'Sua manifestação começou a ser avaliada pela equipe.', 'Olá, <strong>{{name}}</strong>.<br><br>Sua manifestação entrou em triagem inicial. Você receberá novas orientações por e-mail quando a equipe avançar para a próxima etapa.', '→', '', '', 'Não é necessário reenviar a manifestação neste momento.'],
    'collector_documents_requested' => ['Documentação', 'Envio de documentos liberado', 'A próxima etapa do seu credenciamento está pronta.', 'Olá, <strong>{{name}}</strong>.<br><br>Para seguir com seu credenciamento, envie os documentos solicitados pelo link seguro abaixo.<br><br><strong>Documentos solicitados:</strong><br><span style="white-space:pre-line;">{{documents_list}}</span>', '⬆', 'Enviar documentos', '{{public_url}}', 'O envio incompleto pode atrasar a análise documental.'],
    'collector_document_uploaded' => ['Documentação', 'Documento recebido', 'Recebemos um arquivo no seu processo.', 'Olá, <strong>{{name}}</strong>.<br><br>Um documento foi registrado no seu credenciamento. Você pode acompanhar pendências e próximos passos pelo link seguro.', '✓', 'Acompanhar credenciamento', '{{public_url}}', 'A equipe avisará quando o pacote documental estiver completo ou precisar de correções.'],
    'collector_documents_completed' => ['Documentação', 'Pacote documental completo', 'Todos os documentos solicitados foram recebidos.', 'Olá, <strong>{{name}}</strong>.<br><br>Recebemos o pacote documental completo. A equipe iniciará a análise e avisará por e-mail se houver necessidade de ajuste.', '☑', '', '', 'Aguarde a análise documental antes de enviar novos arquivos.'],
    'collector_documents_completed_internal' => ['Equipe interna', 'Captador pronto para análise', 'O pacote documental foi concluído.', '<strong>Nome:</strong> {{name}}<br><strong>Candidatura:</strong> {{application_number}}', '★', '', '', 'A equipe já pode iniciar a análise documental.'],
    'collector_document_reviewed_pending_correction' => ['Documentação', 'Ajuste necessário em documento', 'Há uma pendência documental no seu credenciamento.', 'Olá, <strong>{{name}}</strong>.<br><br>Identificamos uma pendência em documento do seu credenciamento.<br><br><strong>Observação da equipe:</strong><br>{{review_notes}}', '!', 'Corrigir documento', '{{public_url}}', 'A correção deve ser enviada pelo link seguro, dentro do próprio processo.'],
    'collector_application_in_document_review' => ['Análise documental', 'Documentos em análise', 'Sua documentação está sendo avaliada.', 'Olá, <strong>{{name}}</strong>.<br><br>Seus documentos estão em análise pela equipe Dança Carajás. Avisaremos por e-mail quando houver decisão ou solicitação de ajuste.', '⌕', '', '', 'Não é necessário responder este e-mail, salvo se a equipe solicitar.'],
    'collector_application_adjustments_requested' => ['Credenciamento de captador', 'Ajustes solicitados', 'Precisamos de correções para continuar.', 'Olá, <strong>{{name}}</strong>.<br><br>Precisamos de ajustes no seu credenciamento.<br><br><strong>Observação da equipe:</strong><br>{{review_notes}}', '!', 'Acessar credenciamento', '{{public_url}}', 'O processo só avança após o ajuste solicitado.'],
    'collector_application_rejected' => ['Credenciamento de captador', 'Resultado do credenciamento', 'A análise do seu credenciamento foi concluída.', 'Olá, <strong>{{name}}</strong>.<br><br>Agradecemos seu interesse. Neste momento, seu credenciamento não foi aprovado.<br><br><strong>Motivo/observação:</strong><br>{{rejection_reason}}', '×', '', '', 'Novas oportunidades poderão ser abertas futuramente.'],
    'collector_application_approved' => ['Credenciamento de captador', 'Credenciamento aprovado', 'Você avançou para a etapa contratual.', 'Olá, <strong>{{name}}</strong>.<br><br>Seu credenciamento foi aprovado. A próxima etapa é a assinatura contratual. Você receberá o link de assinatura por e-mail.', '✓', '', '', 'A aprovação só libera atuação após cumprimento das etapas contratuais e de acesso.'],
    'signature_request_sent' => ['Assinatura eletrônica', 'Assinatura disponível', 'Seu documento contratual está pronto para assinatura.', 'Olá, <strong>{{name}}</strong>.<br><br>Seu documento de assinatura está disponível. Confira todos os dados antes de assinar.', '✎', 'Assinar documento', '{{signature_url}}', 'O link é pessoal e não deve ser compartilhado.'],
    'collector_contract_signed' => ['Assinatura eletrônica', 'Assinatura recebida', 'Registramos sua assinatura eletrônica.', 'Olá, <strong>{{name}}</strong>.<br><br>Recebemos sua assinatura eletrônica. A equipe continuará a conferência das próximas etapas.', '✓', '', '', 'Guarde este e-mail como confirmação do registro.'],
    'collector_contract_signed_internal' => ['Equipe interna', 'Captador assinou contrato', 'Uma assinatura de captador foi registrada.', 'O captador <strong>{{name}}</strong> assinou o contrato.<br><strong>Candidatura:</strong> {{application_number}}', '★', '', '', 'Verifique se há assinaturas pendentes antes de liberar acesso.'],
    'collector_contract_fully_signed' => ['Assinatura eletrônica', 'Contrato concluído', 'Todas as assinaturas foram concluídas.', 'Olá, <strong>{{name}}</strong>.<br><br>Todas as assinaturas foram concluídas e o documento está finalizado.', '☑', 'Visualizar documento', '{{signature_url}}', 'O documento concluído fica disponível conforme regras internas do processo.'],
    'collector_access_released' => ['Portal do captador', 'Acesso liberado', 'Você já pode concluir a criação do seu acesso.', 'Olá, <strong>{{name}}</strong>.<br><br>Seu acesso ao sistema Dança Carajás Captação foi liberado. Use o link abaixo para concluir seu cadastro de usuário e senha.', '→', 'Concluir cadastro', '{{public_url}}', 'Use o mesmo e-mail informado no credenciamento. Nunca compartilhe sua senha.'],
    'collector_access_self_registered' => ['Portal do captador', 'Acesso criado com sucesso', 'Seu usuário foi criado no sistema.', 'Olá, <strong>{{name}}</strong>.<br><br>Seu acesso foi criado com sucesso. Você já pode entrar no portal com seu e-mail e senha.', '✓', 'Acessar sistema', '{{login_url}}', 'Por segurança, nunca compartilhe sua senha ou dados de acesso.'],
    'collector_access_self_registered_internal' => ['Equipe interna', 'Captador concluiu acesso', 'Um captador concluiu o cadastro de usuário.', 'O captador <strong>{{name}}</strong> concluiu o cadastro de acesso.<br><strong>Candidatura:</strong> {{application_number}}', '★', '', '', 'O captador já pode acessar o portal conforme permissões configuradas.'],
];

$tpl = new \App\Models\EmailTemplate();
foreach ($templates as $eventKey => [$eyebrow, $title, $summary, $body, $icon, $ctaLabel, $ctaUrl, $note]) {
    $tpl->upsert([
        'event_key' => $eventKey,
        'name' => $title,
        'subject' => $title . ' - Danca Carajas',
        'body_text' => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body))) . ($ctaUrl !== '' ? "\n\nLink: {$ctaUrl}" : ''),
        'body_html' => $layout($eyebrow, $title, $summary, $body, $icon, $ctaLabel, $ctaUrl, $note),
        'enabled' => 1,
    ]);
}

echo 'templates premium atualizados: ' . count($templates) . "\n";
echo "\nMigration ETAPA 21D concluida.\n";
