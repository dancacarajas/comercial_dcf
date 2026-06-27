<?php
$application = $application ?? [];
$documents = $documents ?? [];
$statuses = $statuses ?? [];
$documentStatuses = $documentStatuses ?? [];
$reviewStatuses = $reviewStatuses ?? [];
$accessStatuses = $accessStatuses ?? [];
$docStatuses = $docStatuses ?? [];
$entityTypeLabel = $entityTypeLabel ?? '';
$optionalDocTypes = $optionalDocTypes ?? [];
$defaultDocTypes = $defaultDocTypes ?? [];
$publicUrl = $publicUrl ?? null;
$linkedUser = $linkedUser ?? null;
$signatureStageItems = $signatureStageItems ?? [];
$signatureProgress = $signatureProgress ?? ['signed_required' => 0, 'total_required' => 0, 'all_required_signed' => false, 'pending_required_titles' => []];
$collectorStageTemplatesConfigured = !empty($collectorStageTemplatesConfigured);
$hasSignedContract = !empty($hasSignedContract);
$hasAllRequiredSignatures = !empty($hasAllRequiredSignatures);
$journeySteps = $journeySteps ?? [];
$currentStep = $currentStep ?? 'manifestacao';
$docProgress = $docProgress ?? ['total' => 0, 'submitted' => 0, 'pending' => 0, 'approved' => 0];

$id = (int) ($application['id'] ?? 0);
$appStatus = (string) ($application['status'] ?? '');
$canRelease = $hasAllRequiredSignatures && in_array($appStatus, ['contrato_assinado', 'acesso_preparado'], true);
$showContractSection = in_array($appStatus, ['aprovado', 'aguardando_assinatura_contratual', 'contrato_assinado', 'acesso_preparado', 'acesso_liberado'], true);
$showApproveActions = can('collector_applications.approve')
    && !in_array($appStatus, ['reprovado', 'contrato_assinado', 'acesso_preparado', 'acesso_liberado', 'arquivado'], true);
$showGenerateSignatures = can('signature_requests.create')
    && in_array($appStatus, ['aprovado', 'aguardando_assinatura_contratual'], true)
    && !$hasAllRequiredSignatures;
$showRejectActions = $showApproveActions && $appStatus !== 'aprovado';
$allDocumentsSubmitted = !empty($allDocumentsSubmitted);
$signedRequired = (int) ($signatureProgress['signed_required'] ?? 0);
$totalRequired = (int) ($signatureProgress['total_required'] ?? 0);

$docTotal = (int) ($docProgress['total'] ?? 0);
$docSubmitted = (int) ($docProgress['submitted'] ?? 0);
$docApproved = (int) ($docProgress['approved'] ?? 0);
$docPending = (int) ($docProgress['pending'] ?? 0);
$docPct = $docTotal > 0 ? (int) round(($docSubmitted / $docTotal) * 100) : 0;
$docApprovedPct = $docTotal > 0 ? (int) round(($docApproved / $docTotal) * 100) : 0;

$requestedTypes = [];
foreach ($documents as $docRow) {
    $t = (string) ($docRow['document_type'] ?? '');
    if ($t !== '') {
        $requestedTypes[$t] = true;
    }
}
$hasRequestedDocs = $documents !== [];
$canRequestMore = can('collector_applications.request_documents')
    && !in_array($appStatus, ['reprovado', 'arquivado'], true)
    && empty($application['archived_at']);

$docBadgeClass = static function (string $st): string {
    return match ($st) {
        'aprovado' => 'collector-doc-badge collector-doc-badge--aprovado',
        'enviado', 'em_analise' => 'collector-doc-badge collector-doc-badge--enviado',
        'reprovado', 'substituir' => 'collector-doc-badge collector-doc-badge--reprovado',
        default => 'collector-doc-badge collector-doc-badge--pendente',
    };
};

$stepKeys = array_keys($journeySteps);
$currentIdx = array_search($currentStep, $stepKeys, true);
$isArchived = !empty($application['archived_at']) || $appStatus === 'arquivado';
?>
<section class="section collector-app-detail"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Captadores</span>
        <h1 class="h2-section"><?= e($application['name'] ?? 'Candidatura') ?></h1>
        <p class="page-sub">
            <?= e($application['application_number'] ?? '') ?>
            · <?= e($statuses[$appStatus] ?? $appStatus) ?>
        </p>
        <div class="collector-app-status-chips">
            <span class="badge badge-document badge-document-enviado"><?= e($documentStatuses[$application['document_status'] ?? ''] ?? '—') ?></span>
            <span class="badge badge-document badge-document-em_revisao"><?= e($reviewStatuses[$application['review_status'] ?? ''] ?? '—') ?></span>
            <span class="badge badge-document badge-document-ativo"><?= e($accessStatuses[$application['access_status'] ?? ''] ?? '—') ?></span>
        </div>
    </div>
    <div class="actions-row">
        <?php if (can('collector_applications.edit')): ?>
            <a href="<?= e(app_url('/collector-applications/' . $id . '/edit')) ?>" class="btn btn-sm btn-outline">Editar</a>
        <?php endif; ?>
        <?php if ($publicUrl): ?>
            <button type="button" class="btn btn-sm btn-outline" data-copy-link="<?= e($publicUrl) ?>">Copiar link público</button>
            <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-yellow">Abrir portal</a>
        <?php endif; ?>
        <a href="<?= e(app_url('/collector-applications')) ?>" class="btn btn-sm btn-outline">Voltar</a>
    </div>
</div>

<?php if ($journeySteps !== []): ?>
<div class="card collector-app-journey-card">
    <h3 class="h3-card">Jornada do credenciamento</h3>
    <ol class="collector-journey-steps collector-journey-steps--admin" aria-label="Etapas do credenciamento">
        <?php foreach ($journeySteps as $key => $label):
            $idx = array_search($key, $stepKeys, true);
            if ($isArchived && $idx !== false && $currentIdx !== false && $idx <= $currentIdx) {
                $state = 'done';
            } elseif ($idx !== false && $currentIdx !== false && $idx < $currentIdx) {
                $state = 'done';
            } elseif ($key === $currentStep) {
                $state = 'current';
            } else {
                $state = 'pending';
            }
        ?>
            <li class="collector-journey-step is-<?= e($state) ?>">
                <span class="collector-journey-step__num"><?= (int) $idx + 1 ?></span>
                <span class="collector-journey-step__label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<div class="detail-grid">
    <div class="card">
        <h3 class="h3-card">Dados da manifestação</h3>
        <dl class="detail-list">
            <dt>E-mail</dt><dd><a href="mailto:<?= e($application['email'] ?? '') ?>"><?= e($application['email'] ?? '') ?></a></dd>
            <dt>WhatsApp</dt><dd><?= e($application['phone_whatsapp'] ?? '—') ?></dd>
            <dt>CPF/CNPJ</dt><dd><?= e($application['document_number'] ?? '—') ?></dd>
            <dt>Perfil</dt><dd><?= e($entityTypeLabel) ?></dd>
            <dt>Empresa / atuação</dt><dd><?= e($application['company_or_activity'] ?? '—') ?></dd>
            <dt>Cidade/UF</dt><dd><?= e($application['city_state'] ?? '—') ?></dd>
            <dt>Experiência Rouanet</dt><dd><?= e($application['rouanet_experience'] ?? '—') ?></dd>
            <dt>Segmentos</dt><dd><?= e($application['segments'] ?? '—') ?></dd>
            <dt>Origem</dt><dd><?= e($application['source'] ?? '') ?><?= !empty($application['source_page']) ? ' · ' . e($application['source_page']) : '' ?></dd>
            <dt>Recebida em</dt><dd><?= e(format_datetime_br($application['created_at'] ?? null)) ?></dd>
        </dl>
        <?php if (!empty($application['message'])): ?>
            <p class="mb-0"><strong>Mensagem:</strong> <?= e($application['message']) ?></p>
        <?php endif; ?>
        <?php if (!empty($application['sponsor_network_description'])): ?>
            <p><strong>Carteira:</strong> <?= e($application['sponsor_network_description']) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="h3-card">Status e acesso</h3>

        <?php if ($docTotal > 0): ?>
            <div class="collector-doc-progress" style="margin-bottom:14px;">
                <p class="text-sm mb-1"><strong>Documentos cadastrais</strong> — <?= $docSubmitted ?> enviado(s), <?= $docApproved ?> aprovado(s) de <?= $docTotal ?></p>
                <div class="collector-doc-progress__bar" aria-hidden="true"><span style="width:<?= $docPct ?>%;"></span></div>
                <?php if ($docApproved < $docTotal): ?>
                    <p class="text-sm text-muted-dcx mb-0">Análise: <?= $docApprovedPct ?>% concluída<?= $docPending > 0 ? ' · ' . $docPending . ' pendente(s)' : '' ?></p>
                <?php else: ?>
                    <p class="text-sm text-muted-dcx mb-0">Todos os documentos cadastrais foram aprovados.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalRequired > 0): ?>
            <div class="collector-doc-progress" style="margin-bottom:14px;">
                <p class="text-sm mb-1"><strong>Assinaturas contratuais</strong> — <?= $signedRequired ?> de <?= $totalRequired ?> obrigatório(s)</p>
                <div class="collector-doc-progress__bar" aria-hidden="true"><span style="width:<?= (int) round(($signedRequired / max(1, $totalRequired)) * 100) ?>%;"></span></div>
            </div>
        <?php endif; ?>

        <dl class="detail-list">
            <dt>Responsável</dt><dd><?= e($application['assigned_name'] ?? '—') ?></dd>
            <?php if (!empty($application['documents_requested_at'])): ?>
                <dt>Docs solicitados</dt><dd><?= e(format_datetime_br($application['documents_requested_at'])) ?></dd>
            <?php endif; ?>
            <?php if (!empty($application['documents_submitted_at'])): ?>
                <dt>Pacote enviado</dt><dd><?= e(format_datetime_br($application['documents_submitted_at'])) ?></dd>
            <?php endif; ?>
        </dl>

        <?php if ($publicUrl): ?>
            <div class="collector-app-link-box">
                <span class="collector-app-link-box__label">Link do captador</span>
                <code class="collector-app-link-box__url"><?= e($publicUrl) ?></code>
                <div class="actions-row">
                    <button type="button" class="btn btn-sm btn-outline" data-copy-link="<?= e($publicUrl) ?>">Copiar</button>
                    <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-yellow">Abrir</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($linkedUser): ?>
            <p class="mb-0"><strong>Usuário vinculado:</strong> <?= e($linkedUser['email'] ?? '') ?> (<?= e($linkedUser['status'] ?? '') ?>)</p>
        <?php endif; ?>
        <?php if (!empty($application['internal_notes'])): ?>
            <p class="mb-0"><strong>Notas internas:</strong> <?= e($application['internal_notes']) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card collector-app-stage-card">
    <div class="collector-app-stage-card__head">
        <div>
            <h3 class="h3-card mb-1">Etapa 2 — Envio documental</h3>
            <p class="text-sm text-muted-dcx mb-0">
                Perfil: <strong><?= e($entityTypeLabel) ?></strong>.
                Documentos cadastrais, bancários e comprobatórios. Contratos na Etapa 5.
            </p>
        </div>
        <?php if ($hasRequestedDocs): ?>
            <span class="badge badge-document badge-document-enviado"><?= $docTotal ?> item(ns)</span>
        <?php endif; ?>
    </div>

    <?php if ($hasRequestedDocs): ?>
        <?php if (!$allDocumentsSubmitted): ?>
            <div class="alert alert-info" style="margin:14px 0;">
                Faltam documentos ou correções. O captador precisa concluir o envio integral pelo link público.
            </div>
        <?php elseif ($docApproved < $docTotal): ?>
            <div class="alert alert-warning" style="margin:14px 0;">
                Pacote recebido. Analise cada documento abaixo antes de aprovar a candidatura.
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="margin:14px 0;">
                Todos os documentos cadastrais foram aprovados nesta etapa.
            </div>
        <?php endif; ?>

        <div class="collector-doc-list">
            <?php foreach ($documents as $doc):
                $docId = (int) ($doc['id'] ?? 0);
                $docSt = (string) ($doc['status'] ?? '');
                $hasFile = !empty($doc['uploaded_at']) && !empty($doc['file_path']);
                $fileName = (string) ($doc['uploaded_original_name'] ?? '');
                $fileSize = (int) ($doc['file_size'] ?? 0);
                $fileExt = strtoupper((string) ($doc['file_extension'] ?? ''));
                $fileMime = (string) ($doc['file_mime'] ?? '');
                $sizeLabel = $fileSize >= 1048576
                    ? number_format($fileSize / 1048576, 2, ',', '.') . ' MB'
                    : ($fileSize >= 1024 ? number_format($fileSize / 1024, 1, ',', '.') . ' KB' : $fileSize . ' B');
                $canReviewDoc = can('collector_applications.review') && $docId > 0 && $hasFile;
            ?>
                <article class="collector-doc-slot collector-admin-doc-card">
                    <div class="collector-doc-slot__head">
                        <div>
                            <h4 class="collector-doc-slot__title"><?= e($doc['title'] ?? '') ?></h4>
                            <p class="collector-doc-slot__meta mb-0">
                                <span class="<?= e($docBadgeClass($docSt)) ?>"><?= e($docStatuses[$docSt] ?? $docSt) ?></span>
                                <?php if ($hasFile): ?>
                                    · Enviado em <?= e(format_datetime_br($doc['uploaded_at'] ?? null)) ?>
                                <?php else: ?>
                                    · Aguardando envio
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($hasFile && can('collector_applications.view')): ?>
                            <div class="actions-row">
                                <a href="<?= e(app_url('/collector-applications/' . $id . '/documents/' . $docId . '/view')) ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener">Visualizar</a>
                                <a href="<?= e(app_url('/collector-applications/' . $id . '/documents/' . $docId . '/download')) ?>" class="btn btn-sm btn-outline">Baixar</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasFile): ?>
                        <p class="text-sm text-muted-dcx mb-0">
                            <?= e($fileName) ?>
                            <?php if ($fileExt !== '' || $fileMime !== '' || $fileSize > 0): ?>
                                <span>(<?= e(trim($fileExt . ($fileMime !== '' ? ' · ' . $fileMime : '') . ' · ' . $sizeLabel, ' ·')) ?>)</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($doc['review_notes'])): ?>
                        <p class="text-sm mb-0"><strong>Obs.:</strong> <?= e((string) $doc['review_notes']) ?></p>
                    <?php endif; ?>

                    <?php if ($canReviewDoc): ?>
                        <div class="collector-admin-doc-card__review">
                            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/review-document')) ?>" class="collector-admin-doc-quick">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_id" value="<?= $docId ?>">
                                <input type="hidden" name="document_status" value="aprovado">
                                <button type="submit" class="btn btn-sm btn-yellow" <?= $docSt === 'aprovado' ? 'disabled' : '' ?>>Aprovar</button>
                            </form>
                            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/review-document')) ?>" class="collector-admin-doc-quick">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_id" value="<?= $docId ?>">
                                <input type="hidden" name="document_status" value="substituir">
                                <button type="submit" class="btn btn-sm btn-outline">Solicitar substituição</button>
                            </form>
                            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/review-document')) ?>" class="collector-admin-doc-quick">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_id" value="<?= $docId ?>">
                                <input type="hidden" name="document_status" value="reprovado">
                                <button type="submit" class="btn btn-sm btn-outline">Reprovar</button>
                            </form>
                            <details class="collector-admin-doc-notes">
                                <summary>Observação</summary>
                                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/review-document')) ?>" class="collector-admin-doc-notes-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="document_id" value="<?= $docId ?>">
                                    <select name="document_status" class="input input-sm">
                                        <?php foreach ($docStatuses as $k => $label): ?>
                                            <option value="<?= e($k) ?>" <?= $docSt === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="review_notes" class="input input-sm" placeholder="Observação para o captador" value="<?= e((string) ($doc['review_notes'] ?? '')) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline">Salvar</button>
                                </form>
                            </details>
                        </div>
                    <?php elseif (can('collector_applications.review') && $docId > 0 && !$hasFile): ?>
                        <p class="text-sm text-muted-dcx mb-0">Análise disponível após o envio do arquivo.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted-dcx mb-0">Nenhum documento solicitado ainda. Use o formulário abaixo para gerar o link e abrir a etapa ao captador.</p>
    <?php endif; ?>

    <?php if ($canRequestMore): ?>
        <details class="collector-app-request-panel" <?= !$hasRequestedDocs ? 'open' : '' ?>>
            <summary><?= $hasRequestedDocs ? 'Solicitar documentos adicionais' : 'Selecionar documentos e gerar link' ?></summary>
            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/request-documents')) ?>" class="collector-app-request-form" id="collector-doc-request-form">
                <?= csrf_field() ?>
                <?php if ($defaultDocTypes !== []): ?>
                    <p class="collector-app-request-form__group-title">Recomendados para <?= e($entityTypeLabel) ?></p>
                    <div class="collector-doc-request-grid collector-doc-request-grid--recommended">
                        <?php foreach ($defaultDocTypes as $type => $label):
                            $already = isset($requestedTypes[$type]);
                        ?>
                            <label class="collector-doc-request-item<?= $already ? ' is-requested' : '' ?>">
                                <input type="checkbox" name="document_types[]" value="<?= e($type) ?>" <?= $already ? 'disabled' : 'checked' ?>>
                                <span><?= e($label) ?></span>
                                <?php if ($already): ?><em class="collector-doc-request-item__tag">Já solicitado</em><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (($optionalDocTypes ?? []) !== []): ?>
                    <p class="collector-app-request-form__group-title">Opcionais</p>
                    <div class="collector-doc-request-grid">
                        <?php foreach ($optionalDocTypes as $type => $label):
                            $already = isset($requestedTypes[$type]);
                        ?>
                            <label class="collector-doc-request-item<?= $already ? ' is-requested' : '' ?>">
                                <input type="checkbox" name="document_types[]" value="<?= e($type) ?>" class="collector-doc-optional" <?= $already ? 'disabled' : '' ?>>
                                <span><?= e($label) ?></span>
                                <?php if ($already): ?><em class="collector-doc-request-item__tag">Já solicitado</em><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="actions-row" style="margin-top:12px;">
                    <button type="button" class="btn btn-sm btn-outline" id="collector-doc-select-recommended">Marcar recomendados</button>
                    <button type="button" class="btn btn-sm btn-outline" id="collector-doc-clear-optional">Limpar opcionais</button>
                    <button type="submit" class="btn btn-sm btn-yellow" id="collector-doc-submit">
                        <?= $hasRequestedDocs ? 'Adicionar à solicitação' : 'Gerar link e solicitar documentos' ?>
                    </button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</div>

<?php if ($showContractSection): ?>
<div class="card" style="margin-top:18px;">
    <h3 class="h3-card">Documentos contratuais (Etapa 5)</h3>
    <?php if (!$collectorStageTemplatesConfigured): ?>
        <div class="alert alert-warning" style="margin-bottom:12px;">
            Nenhum modelo obrigatório configurado para a Etapa 5 dos captadores. O sistema usará o modelo padrão de captador, se existir.
        </div>
    <?php endif; ?>
    <?php if ($showGenerateSignatures): ?>
        <div class="alert alert-info" style="margin-bottom:12px;">
            Candidatura aprovada. Gere os documentos de assinatura — a <strong>JA Produções assina automaticamente</strong> e o captador recebe links individuais.
        </div>
    <?php elseif (!$hasAllRequiredSignatures): ?>
        <div class="alert alert-warning" style="margin-bottom:12px;">
            O acesso só poderá ser liberado após assinatura de todos os documentos contratuais obrigatórios.
            <?php if ($totalRequired > 0): ?>
                Progresso: <strong><?= $signedRequired ?> de <?= $totalRequired ?></strong> documento(s) obrigatório(s) assinado(s).
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info" style="margin-bottom:12px;">
            Todos os documentos contratuais obrigatórios foram assinados.
        </div>
    <?php endif; ?>

    <?php if ($signatureStageItems === []): ?>
        <p class="mb-0 text-muted-dcx">Nenhum documento de assinatura gerado ainda.</p>
    <?php else: ?>
        <div class="collector-signature-cards">
            <?php foreach ($signatureStageItems as $item):
                $reqId = (int) ($item['request_id'] ?? 0);
                $captadorLink = (string) ($item['captador_link'] ?? '');
            ?>
                <div class="card collector-signature-card" style="margin-bottom:12px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                        <div>
                            <h4 class="h4-card" style="margin:0 0 6px;"><?= e($item['title'] ?? '') ?></h4>
                            <?php if (empty($item['is_required'])): ?><span class="badge">Opcional</span><?php endif; ?>
                            <p class="text-sm mb-0">
                                Status: <span class="badge"><?= e($item['request_status'] ?? '—') ?></span>
                                <?php if (!empty($item['sent_at'])): ?> · Enviado <?= e(format_datetime_br($item['sent_at'])) ?><?php endif; ?>
                                <?php if (!empty($item['signed_at'])): ?> · Assinado <?= e(format_datetime_br($item['signed_at'])) ?><?php endif; ?>
                            </p>
                            <p class="text-sm mb-0">
                                JA Produções: <?= !empty($item['contratante_signed']) ? 'assinado' : 'pendente' ?>
                                · Captador: <?= !empty($item['captador_signed']) ? 'assinado' : 'pendente' ?>
                            </p>
                        </div>
                        <div class="actions-row">
                            <?php if ($captadorLink !== ''): ?>
                                <button type="button" class="btn btn-sm btn-outline" data-copy-link="<?= e($captadorLink) ?>">Copiar link</button>
                                <a href="<?= e($captadorLink) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">Abrir link</a>
                            <?php endif; ?>
                            <?php if ($reqId > 0): ?>
                                <a href="<?= e(app_url('/signature-requests/' . $reqId)) ?>" class="btn btn-sm btn-outline">Ver processo</a>
                                <?php if (!empty($item['is_signed'])): ?>
                                    <a href="<?= e(app_url('/signature-requests/' . $reqId . '/pdf')) ?>" class="btn btn-sm btn-outline">Baixar PDF</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card collector-actions-card" style="margin-top:18px;">
    <h3 class="h3-card">Ações</h3>

    <?php if ($showApproveActions): ?>
    <div class="collector-actions-group">
        <h4 class="collector-actions-group__title">Análise da candidatura</h4>
        <div class="collector-actions-group__body">
            <?php if ($showRejectActions): ?>
                <?php if (!$allDocumentsSubmitted): ?>
                    <p class="text-sm text-muted-dcx">Aprovação disponível após envio completo de todos os documentos cadastrais.</p>
                <?php endif; ?>
                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/approve')) ?>" class="collector-action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approval_notes" value="Aprovado pela equipe comercial.">
                    <button type="submit" class="btn btn-sm btn-yellow" <?= !$allDocumentsSubmitted ? 'disabled' : '' ?>><?= can('signature_requests.create') ? 'Aprovar e gerar documentos de assinatura' : 'Aprovar' ?></button>
                </form>
                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/reject')) ?>" class="collector-action-form collector-reject-form">
                    <?= csrf_field() ?>
                    <input type="text" name="rejection_reason" class="input input-sm" placeholder="Motivo da reprovação" required>
                    <button type="submit" class="btn btn-sm btn-outline">Reprovar</button>
                </form>
            <?php elseif (in_array($appStatus, ['aprovado', 'aguardando_assinatura_contratual'], true)): ?>
                <p class="text-sm text-muted-dcx">
                    Candidatura aprovada.
                    <?= $hasAllRequiredSignatures ? 'Todos os documentos contratuais obrigatórios foram assinados.' : 'Aguardando assinatura do captador nos documentos contratuais.' ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showGenerateSignatures): ?>
    <div class="collector-actions-group">
        <h4 class="collector-actions-group__title">Documentos de assinatura</h4>
        <div class="collector-actions-group__body">
            <p class="text-sm text-muted-dcx">Gera todos os modelos obrigatórios configurados para a Etapa 5. A JA Produções assina automaticamente.</p>
            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/generate-contract')) ?>" class="collector-contract-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-yellow">Gerar documentos de assinatura obrigatórios</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (can('collector_applications.release_access')): ?>
    <div class="collector-actions-group">
        <h4 class="collector-actions-group__title">Acesso ao sistema</h4>
        <div class="collector-actions-group__body">
            <?php if (!$canRelease): ?>
                <p class="text-sm text-muted-dcx">Liberação bloqueada até conclusão de todos os documentos contratuais obrigatórios.</p>
            <?php endif; ?>
            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/prepare-access')) ?>" class="collector-action-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline" <?= !$canRelease ? 'disabled' : '' ?>>Preparar acesso</button>
            </form>
            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/release-access')) ?>" class="collector-action-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-yellow" <?= !$canRelease ? 'disabled' : '' ?>>Liberar acesso</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (can('collector_applications.archive')): ?>
    <div class="collector-actions-group">
        <h4 class="collector-actions-group__title">Registro</h4>
        <div class="collector-actions-group__body">
            <?php if (empty($application['archived_at'])): ?>
                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/archive')) ?>" class="collector-action-form"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Arquivar</button></form>
            <?php else: ?>
                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/restore')) ?>" class="collector-action-form"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Restaurar</button></form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div></section>

<script>
(function () {
    document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-copy-link') || '';
            if (!url || !navigator.clipboard) return;
            navigator.clipboard.writeText(url).then(function () {
                var prev = btn.textContent;
                btn.textContent = 'Copiado!';
                setTimeout(function () { btn.textContent = prev; }, 1800);
            });
        });
    });

    var form = document.getElementById('collector-doc-request-form');
    if (!form) return;

    var selectRecommended = document.getElementById('collector-doc-select-recommended');
    var clearOptional = document.getElementById('collector-doc-clear-optional');
    var submitBtn = document.getElementById('collector-doc-submit');

    function enabledCheckboxes() {
        return Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
    }

    function updateSubmitState() {
        if (!submitBtn) return;
        submitBtn.disabled = enabledCheckboxes().filter(function (cb) { return cb.checked; }).length === 0;
    }

    if (selectRecommended) {
        selectRecommended.addEventListener('click', function () {
            form.querySelectorAll('.collector-doc-request-grid--recommended input[type="checkbox"]:not(:disabled)').forEach(function (cb) {
                cb.checked = true;
            });
            updateSubmitState();
        });
    }

    if (clearOptional) {
        clearOptional.addEventListener('click', function () {
            form.querySelectorAll('.collector-doc-optional:not(:disabled)').forEach(function (cb) {
                cb.checked = false;
            });
            updateSubmitState();
        });
    }

    form.addEventListener('change', updateSubmitState);
    updateSubmitState();
})();
</script>
