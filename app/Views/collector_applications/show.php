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
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Captadores</span>
        <h1 class="h2-section"><?= e($application['name'] ?? 'Candidatura') ?></h1>
        <p class="page-sub"><?= e($application['application_number'] ?? '') ?> · <?= e($statuses[$application['status'] ?? ''] ?? '') ?></p>
    </div>
    <div class="actions-row">
        <?php if (can('collector_applications.edit')): ?><a href="<?= e(app_url('/collector-applications/' . $id . '/edit')) ?>" class="btn btn-sm btn-outline">Editar</a><?php endif; ?>
        <a href="<?= e(app_url('/collector-applications')) ?>" class="btn btn-sm btn-outline">Voltar</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h3 class="h3-card">Dados da manifestação</h3>
        <dl class="detail-list">
            <dt>E-mail</dt><dd><?= e($application['email'] ?? '') ?></dd>
            <dt>WhatsApp</dt><dd><?= e($application['phone_whatsapp'] ?? '—') ?></dd>
            <dt>CPF/CNPJ</dt><dd><?= e($application['document_number'] ?? '—') ?></dd>
            <dt>Empresa / atuação</dt><dd><?= e($application['company_or_activity'] ?? '—') ?></dd>
            <dt>Cidade/UF</dt><dd><?= e($application['city_state'] ?? '—') ?></dd>
            <dt>Experiência Rouanet</dt><dd><?= e($application['rouanet_experience'] ?? '—') ?></dd>
            <dt>Segmentos</dt><dd><?= e($application['segments'] ?? '—') ?></dd>
            <dt>Origem</dt><dd><?= e($application['source'] ?? '') ?> <?= !empty($application['source_page']) ? '· ' . e($application['source_page']) : '' ?></dd>
            <dt>Recebida em</dt><dd><?= e($application['created_at'] ?? '') ?></dd>
        </dl>
        <?php if (!empty($application['message'])): ?><p class="mb-0"><strong>Mensagem:</strong> <?= e($application['message']) ?></p><?php endif; ?>
        <?php if (!empty($application['sponsor_network_description'])): ?><p><strong>Carteira:</strong> <?= e($application['sponsor_network_description']) ?></p><?php endif; ?>
    </div>

    <div class="card">
        <h3 class="h3-card">Status e acesso</h3>
        <dl class="detail-list">
            <dt>Documentos</dt><dd><?= e($documentStatuses[$application['document_status'] ?? ''] ?? '') ?></dd>
            <dt>Análise</dt><dd><?= e($reviewStatuses[$application['review_status'] ?? ''] ?? '') ?></dd>
            <dt>Acesso</dt><dd><?= e($accessStatuses[$application['access_status'] ?? ''] ?? '') ?></dd>
            <dt>Responsável</dt><dd><?= e($application['assigned_name'] ?? '—') ?></dd>
        </dl>
        <?php if ($publicUrl): ?><p><strong>Link documental:</strong> <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a></p><?php endif; ?>
        <?php if ($linkedUser): ?><p><strong>Usuário vinculado:</strong> <?= e($linkedUser['email'] ?? '') ?> (<?= e($linkedUser['status'] ?? '') ?>)</p><?php endif; ?>
        <?php if (!empty($application['internal_notes'])): ?><p><strong>Notas internas:</strong> <?= e($application['internal_notes']) ?></p><?php endif; ?>
    </div>
</div>

<?php if (can('collector_applications.request_documents')): ?>
<div class="card" style="margin-top:18px;">
    <h3 class="h3-card">Solicitar documentos (Etapa 2)</h3>
    <p class="text-sm text-muted-dcx" style="margin-bottom:12px;">
        Perfil na manifestação: <strong><?= e($entityTypeLabel ?? 'Pessoa física (CPF)') ?></strong>.
        Documentos cadastrais, bancários e comprobatórios de experiência. Documentos contratuais serão assinados apenas após aprovação, na Etapa 5.
    </p>
    <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/request-documents')) ?>">
        <?= csrf_field() ?>
        <div class="filter-checks" style="margin-bottom:12px;">
            <?php foreach ($defaultDocTypes as $type => $label): ?>
                <label class="check-inline"><input type="checkbox" name="document_types[]" value="<?= e($type) ?>" checked> <?= e($label) ?></label>
            <?php endforeach; ?>
            <?php foreach (($optionalDocTypes ?? []) as $type => $label): ?>
                <label class="check-inline"><input type="checkbox" name="document_types[]" value="<?= e($type) ?>"> <?= e($label) ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-sm btn-yellow">Gerar link e solicitar documentos</button>
    </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:18px;">
    <h3 class="h3-card">Documentos do credenciamento</h3>
    <?php if (can('collector_applications.review')): ?>
        <p class="text-sm text-muted-dcx" style="margin-bottom:12px;">Visualize ou baixe cada anexo antes de aprovar, reprovar ou solicitar substituição.</p>
    <?php endif; ?>
    <?php if ($documents === []): ?>
        <p class="mb-0">Nenhum documento solicitado ainda.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Documento</th><th>Arquivo</th><th>Status</th><th>Enviado em</th><th>Análise</th></tr></thead>
            <tbody>
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
            ?>
                <tr>
                    <td><strong><?= e($doc['title'] ?? '') ?></strong></td>
                    <td>
                        <?php if ($hasFile): ?>
                            <div><?= e($fileName) ?></div>
                            <div class="text-sm text-muted-dcx"><?= e($fileExt) ?><?= $fileMime !== '' ? ' · ' . e($fileMime) : '' ?> · <?= e($sizeLabel) ?></div>
                            <?php if (can('collector_applications.view')): ?>
                                <div class="actions-row" style="margin-top:6px;">
                                    <a href="<?= e(app_url('/collector-applications/' . $id . '/documents/' . $docId . '/view')) ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener">Visualizar</a>
                                    <a href="<?= e(app_url('/collector-applications/' . $id . '/documents/' . $docId . '/download')) ?>" class="btn btn-sm btn-outline">Baixar</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted-dcx">Aguardando envio pelo captador.</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($docStatuses[$docSt] ?? $docSt) ?></td>
                    <td><?= e(format_datetime_br($doc['uploaded_at'] ?? null)) ?></td>
                    <td>
                        <?php if (can('collector_applications.review') && $docId > 0): ?>
                        <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/review-document')) ?>" class="inline-form" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= $docId ?>">
                            <select name="document_status" class="input input-sm">
                                <?php foreach ($docStatuses as $k => $label): ?>
                                    <option value="<?= e($k) ?>" <?= $docSt === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="review_notes" class="input input-sm" placeholder="Observação" value="<?= e((string) ($doc['review_notes'] ?? '')) ?>">
                            <button type="submit" class="btn btn-sm btn-yellow">Salvar análise</button>
                        </form>
                        <?php elseif (!empty($doc['review_notes'])): ?>
                            <span class="text-sm"><?= e((string) $doc['review_notes']) ?></span>
                        <?php else: ?>
                            <span class="text-muted-dcx">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
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
        <div class="table-wrap"><table>
            <thead><tr><th>Documento</th><th>Status</th><th>Enviado</th><th>Assinado</th><th>Signatários</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($signatureStageItems as $item):
                $reqId = (int) ($item['request_id'] ?? 0);
                $captadorLink = (string) ($item['captador_link'] ?? '');
            ?>
                <tr>
                    <td>
                        <strong><?= e($item['title'] ?? '') ?></strong>
                        <?php if (empty($item['is_required'])): ?><span class="badge">Opcional</span><?php endif; ?>
                    </td>
                    <td><?= e($item['request_status'] ?? '—') ?></td>
                    <td><?= e(format_datetime_br($item['sent_at'] ?? null)) ?></td>
                    <td><?= e(format_datetime_br($item['signed_at'] ?? null)) ?></td>
                    <td>
                        JA Produções: <?= !empty($item['contratante_signed']) ? 'assinado' : 'pendente' ?><br>
                        Captador: <?= !empty($item['captador_signed']) ? 'assinado' : 'pendente' ?>
                    </td>
                    <td>
                        <div class="actions-row" style="flex-wrap:wrap;">
                            <?php if ($captadorLink !== ''): ?>
                                <button type="button" class="btn btn-sm btn-outline" onclick="navigator.clipboard.writeText('<?= e($captadorLink) ?>')">Copiar link</button>
                                <a href="<?= e($captadorLink) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">Abrir link</a>
                            <?php endif; ?>
                            <?php if ($reqId > 0): ?>
                                <a href="<?= e(app_url('/signature-requests/' . $reqId)) ?>" class="btn btn-sm btn-outline">Ver processo</a>
                                <?php if (!empty($item['is_signed'])): ?>
                                    <a href="<?= e(app_url('/signature-requests/' . $reqId . '/pdf')) ?>" class="btn btn-sm btn-outline">Baixar PDF</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
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
