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
$activeSignature = $activeSignature ?? null;
$signatureSigners = $signatureSigners ?? [];
$signatureLink = $signatureLink ?? null;
$contractTemplates = $contractTemplates ?? [];
$hasSignedContract = !empty($hasSignedContract);
$id = (int) ($application['id'] ?? 0);
$appStatus = (string) ($application['status'] ?? '');
$canRelease = $hasSignedContract && in_array($appStatus, ['contrato_assinado', 'acesso_preparado'], true);
$showContractSection = in_array($appStatus, ['aprovado', 'aguardando_assinatura_contratual', 'contrato_assinado', 'acesso_preparado', 'acesso_liberado'], true);
$showApproveActions = can('collector_applications.approve')
    && !in_array($appStatus, ['reprovado', 'contrato_assinado', 'acesso_preparado', 'acesso_liberado', 'arquivado'], true);
$showGenerateContract = can('signature_requests.create') && $appStatus === 'aprovado' && !$activeSignature;
$showRejectActions = $showApproveActions && $appStatus !== 'aprovado';
$awaitingCaptadorSignature = $activeSignature && !$hasSignedContract && in_array((string) ($activeSignature['status'] ?? ''), ['aguardando_assinatura', 'parcialmente_assinado'], true);
$allDocumentsSubmitted = !empty($allDocumentsSubmitted);
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
    <h3 class="h3-card">Solicitar documentos</h3>
    <p class="text-sm text-muted-dcx" style="margin-bottom:12px;">
        Perfil na manifestação: <strong><?= e($entityTypeLabel ?? 'Pessoa física (CPF)') ?></strong>.
        Os documentos abaixo são sugeridos automaticamente conforme CPF ou CNPJ.
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
    <?php if ($documents === []): ?>
        <p class="mb-0">Nenhum documento solicitado ainda.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Tipo</th><th>Status</th><th>Enviado</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= e($doc['title'] ?? '') ?></td>
                    <td><?= e($docStatuses[$doc['status'] ?? ''] ?? $doc['status'] ?? '') ?></td>
                    <td><?= e($doc['uploaded_at'] ?? '—') ?></td>
                    <td>
                        <?php if (can('collector_applications.review') && !empty($doc['id'])): ?>
                        <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/review-document')) ?>" class="inline-form" style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                            <select name="document_status" class="input input-sm">
                                <?php foreach ($docStatuses as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                            <input type="text" name="review_notes" class="input input-sm" placeholder="Observação">
                            <button type="submit" class="btn btn-sm btn-outline">Salvar</button>
                        </form>
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
    <h3 class="h3-card">Contrato e assinatura</h3>
    <?php if ($showGenerateContract): ?>
        <div class="alert alert-info" style="margin-bottom:12px;">
            Candidatura aprovada. Gere o contrato abaixo — a <strong>JA Produções assina automaticamente</strong> e o captador recebe o link para concluir.
        </div>
    <?php elseif ($awaitingCaptadorSignature): ?>
        <div class="alert alert-info" style="margin-bottom:12px;">
            Contrato gerado. <strong>JA Produções já assinou.</strong> Aguardando assinatura do captador externo.
        </div>
    <?php elseif (!$hasSignedContract): ?>
        <div class="alert alert-warning" style="margin-bottom:12px;">
            O acesso só poderá ser liberado após assinatura contratual concluída.
        </div>
    <?php endif; ?>
    <?php if ($activeSignature): ?>
        <dl class="detail-list">
            <dt>Status assinatura</dt><dd><?= e($activeSignature['status'] ?? '') ?></dd>
            <dt>Enviado em</dt><dd><?= e($activeSignature['sent_at'] ?? '—') ?></dd>
            <dt>Assinado em</dt><dd><?= e($activeSignature['signed_at'] ?? '—') ?></dd>
            <?php if ($signatureLink): ?>
                <dt>Link do captador</dt><dd><a href="<?= e($signatureLink) ?>" target="_blank" rel="noopener"><?= e($signatureLink) ?></a></dd>
            <?php endif; ?>
        </dl>
        <?php if ($signatureSigners !== []): ?>
            <div class="collector-signers-summary">
                <?php foreach ($signatureSigners as $sigSigner): ?>
                    <p><strong><?= e(($sigSigner['signer_role'] ?? '') === 'contratante' ? 'Contratante (JA Produções)' : 'Captador externo') ?>:</strong>
                        <?= e($sigSigner['signer_name'] ?? '') ?> · <span class="badge"><?= e($sigSigner['status'] ?? '') ?></span></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($appStatus === 'aprovado'): ?>
        <p class="mb-0 text-muted-dcx">Nenhum contrato gerado ainda. Use o botão <strong>Gerar contrato</strong> na seção Ações.</p>
    <?php else: ?>
        <p class="mb-0">Nenhum processo de assinatura gerado.</p>
    <?php endif; ?>
    <?php if ($activeSignature): ?>
    <div class="actions-row" style="margin-top:12px;">
        <?php if ($signatureLink): ?>
            <button type="button" class="btn btn-sm btn-yellow" onclick="navigator.clipboard.writeText('<?= e($signatureLink) ?>')">Copiar link do captador</button>
        <?php endif; ?>
        <?php if (!empty($hasSignedContract) && !empty($activeSignature['id'])): ?>
            <a href="<?= e(app_url('/signature-requests/' . (int) $activeSignature['id'] . '/pdf')) ?>" class="btn btn-sm btn-outline">Baixar PDF assinado</a>
        <?php endif; ?>
        <?php if (can('signature_requests.cancel') && (string) ($activeSignature['status'] ?? '') !== 'assinado'): ?>
            <form method="post" action="<?= e(app_url('/signature-requests/' . (int) $activeSignature['id'] . '/cancel')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Cancelar assinatura</button></form>
        <?php endif; ?>
        <a href="<?= e(app_url('/signature-requests/' . (int) $activeSignature['id'])) ?>" class="btn btn-sm btn-outline">Ver processo de assinatura</a>
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
                    <p class="text-sm text-muted-dcx">Aprovação disponível após envio completo de todos os documentos.</p>
                <?php endif; ?>
                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/approve')) ?>" class="collector-action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approval_notes" value="Aprovado pela equipe comercial.">
                    <button type="submit" class="btn btn-sm btn-yellow" <?= !$allDocumentsSubmitted ? 'disabled' : '' ?>><?= can('signature_requests.create') ? 'Aprovar e gerar contrato' : 'Aprovar' ?></button>
                </form>
                <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/reject')) ?>" class="collector-action-form collector-reject-form">
                    <?= csrf_field() ?>
                    <input type="text" name="rejection_reason" class="input input-sm" placeholder="Motivo da reprovação" required>
                    <button type="submit" class="btn btn-sm btn-outline">Reprovar</button>
                </form>
            <?php elseif ($appStatus === 'aprovado'): ?>
                <p class="text-sm text-muted-dcx">Candidatura aprovada. <?= $activeSignature ? 'Contrato já gerado — aguardando assinatura do captador.' : 'Gere o contrato na seção abaixo.' ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showGenerateContract): ?>
    <div class="collector-actions-group">
        <h4 class="collector-actions-group__title">Contrato</h4>
        <div class="collector-actions-group__body">
            <p class="text-sm text-muted-dcx">Selecione o modelo e clique em <strong>Gerar contrato</strong>. A JA Produções assina automaticamente.</p>
            <form method="post" action="<?= e(app_url('/collector-applications/' . $id . '/generate-contract')) ?>" class="collector-contract-form">
                <?= csrf_field() ?>
                <select name="contract_template_id" class="input input-sm">
                    <?php foreach ($contractTemplates as $tpl): ?>
                        <option value="<?= (int) ($tpl['id'] ?? 0) ?>" <?= (int) ($tpl['id'] ?? 0) === (int) ($defaultContractTemplateId ?? 0) ? 'selected' : '' ?>><?= e($tpl['title'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-yellow">Gerar contrato</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (can('collector_applications.release_access')): ?>
    <div class="collector-actions-group">
        <h4 class="collector-actions-group__title">Acesso ao sistema</h4>
        <div class="collector-actions-group__body">
            <?php if (!$canRelease): ?>
                <p class="text-sm text-muted-dcx">Liberação bloqueada até conclusão da assinatura contratual.</p>
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
