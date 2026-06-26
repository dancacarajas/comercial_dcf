<?php
$contract = $contract ?? [];
$model = $model ?? null;
$contractTypes = $contractTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$statuses = $statuses ?? [];
$reviewStatuses = $reviewStatuses ?? [];
$signatureStatuses = $signatureStatuses ?? [];
$documents = $documents ?? [];
$documentSummary = $documentSummary ?? [];
$documentModel = $documentModel ?? null;

$cid = (int) ($contract['id'] ?? 0);
$sid = (int) ($contract['sponsor_id'] ?? 0);
$isArchived = !empty($contract['archived_at']);
$st = (string) ($contract['status'] ?? '');
$rev = (string) ($contract['review_status'] ?? '');
$sig = (string) ($contract['signature_status'] ?? '');
$expired = $model && $model->isExpired($contract);
$expiringSoon = $model && $model->isExpiringSoon($contract);
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Contrato</span>
                <h1 class="h2-section"><?= e($contract['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <?php if (!empty($contract['contract_number'])): ?><span class="pill"><?= e($contract['contract_number']) ?></span><?php endif; ?>
                    <span class="contract-status badge-ct-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="contract-review-status badge-rev-<?= e($rev) ?>"><?= e($reviewStatuses[$rev] ?? $rev) ?></span>
                    <span class="contract-signature-status badge-sig-<?= e($sig) ?>"><?= e($signatureStatuses[$sig] ?? $sig) ?></span>
                    <span class="contract-type"><?= e($contractTypes[$contract['contract_type'] ?? ''] ?? '') ?></span>
                    <?php if ($expired): ?><span class="contract-alert">Vencido</span><?php elseif ($expiringSoon): ?><span class="contract-alert">Vence em breve</span><?php endif; ?>
                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/contracts')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="notice notice-info contract-alert" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Assinatura digital integrada, portal externo do patrocinador e relatórios avançados serão tratados em etapas futuras.</p>
        </div>

        <div class="detail-grid">
            <article class="card contract-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Patrocinador</dt><dd><a href="<?= e(app_url('/sponsors/' . $sid)) ?>" class="link-strong"><?= e($contract['sponsor_name'] ?? '—') ?></a></dd>
                    <dt>Empresa</dt><dd><?php if (!empty($contract['company_id'])): ?><a href="<?= e(app_url('/companies/' . (int) $contract['company_id'])) ?>" class="link-strong"><?= e($contract['company_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Contato</dt><dd><?php if (!empty($contract['contact_id'])): ?><a href="<?= e(app_url('/contacts/' . (int) $contract['contact_id'])) ?>" class="link-strong"><?= e($contract['contact_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Oportunidade</dt><dd><?php if (!empty($contract['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $contract['opportunity_id'])) ?>" class="link-strong"><?= e($contract['opportunity_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Proposta</dt><dd><?php if (!empty($contract['proposal_id'])): ?><a href="<?= e(app_url('/proposals/' . (int) $contract['proposal_id'])) ?>" class="link-strong"><?= e($contract['proposal_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Cota</dt><dd><?php if (!empty($contract['quota_id'])): ?><a href="<?= e(app_url('/quotas/' . (int) $contract['quota_id'])) ?>" class="link-strong"><?= e($contract['quota_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>

            <article class="card contract-card">
                <h3 class="h3-card"><i data-lucide="file-signature"></i> Instrumento</h3>
                <dl class="meta-list">
                    <dt>Tipo</dt><dd><span class="contract-type"><?= e($contractTypes[$contract['contract_type'] ?? ''] ?? $dash($contract['contract_type'] ?? '')) ?></span></dd>
                    <dt>Mecanismo</dt><dd><?= e($fundingMechanisms[$contract['funding_mechanism'] ?? ''] ?? $dash($contract['funding_mechanism'] ?? '')) ?></dd>
                    <dt>Valor formalizado</dt><dd class="contract-value money-value"><?= $contract['formalized_value'] !== null ? e(money_br($contract['formalized_value'])) : '—' ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($contract['responsible_name'] ?? '')) ?></dd>
                    <dt>Aprovado por</dt><dd><?= e($dash($contract['approved_by_name'] ?? '')) ?></dd>
                    <dt>Aprovado em</dt><dd><?= e($dash($contract['approved_at'] ?? '')) ?></dd>
                    <dt>Assinatura registrada por</dt><dd><?= e($dash($contract['signed_registered_by_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card contract-card">
                <h3 class="h3-card"><i data-lucide="calendar"></i> Vigência e marcos</h3>
                <dl class="meta-list">
                    <dt>Período</dt><dd class="<?= $expired ? 'overdue' : '' ?>"><?= e($dash($contract['start_date'] ?? '')) ?> — <?= e($dash($contract['end_date'] ?? '')) ?></dd>
                    <dt>Enviado para assinatura</dt><dd><?= e($dash($contract['sent_for_signature_at'] ?? '')) ?></dd>
                    <dt>Assinado em</dt><dd><?= e($dash($contract['signed_at'] ?? '')) ?></dd>
                    <dt>Início de vigência</dt><dd><?= e($dash($contract['effective_at'] ?? '')) ?></dd>
                    <dt>Encerrado em</dt><dd><?= e($dash($contract['ended_at'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card contract-card contract-documents">
                <h3 class="h3-card"><i data-lucide="folder"></i> Documentos vinculados</h3>
                <dl class="meta-list">
                    <dt>Minuta</dt><dd><?php if (!empty($contract['draft_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $contract['draft_document_id'])) ?>" class="link-strong"><?= e($contract['draft_document_title'] ?? ('#' . (int) $contract['draft_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Final</dt><dd><?php if (!empty($contract['final_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $contract['final_document_id'])) ?>" class="link-strong"><?= e($contract['final_document_title'] ?? ('#' . (int) $contract['final_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Assinado</dt><dd><?php if (!empty($contract['signed_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $contract['signed_document_id'])) ?>" class="link-strong"><?= e($contract['signed_document_title'] ?? ('#' . (int) $contract['signed_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>

            <article class="card contract-card">
                <h3 class="h3-card"><i data-lucide="pen-line"></i> Signatários</h3>
                <dl class="meta-list">
                    <dt>Patrocinador</dt><dd><?= e($dash($contract['sponsor_signatory_name'] ?? '')) ?><?php if (!empty($contract['sponsor_signatory_position'])): ?> · <?= e($contract['sponsor_signatory_position']) ?><?php endif; ?></dd>
                    <dt>E-mail (patrocinador)</dt><dd><?= e($dash($contract['sponsor_signatory_email'] ?? '')) ?></dd>
                    <dt>Documento</dt><dd><?= e($dash($contract['sponsor_signatory_document'] ?? '')) ?></dd>
                    <dt>Organização</dt><dd><?= e($dash($contract['organization_signatory_name'] ?? '')) ?><?php if (!empty($contract['organization_signatory_position'])): ?> · <?= e($contract['organization_signatory_position']) ?><?php endif; ?></dd>
                    <dt>E-mail (organização)</dt><dd><?= e($dash($contract['organization_signatory_email'] ?? '')) ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($contract['approval_notes']) || !empty($contract['signature_notes']) || !empty($contract['legal_notes']) || !empty($contract['notes']) || (!empty($contract['internal_notes']) && can('contracts.edit'))): ?>
            <article class="card contract-card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="message-square"></i> Observações</h3>
                <?php if (!empty($contract['approval_notes'])): ?><p><strong>Aprovação:</strong><br><?= nl2br(e($contract['approval_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($contract['signature_notes'])): ?><p><strong>Assinatura:</strong><br><?= nl2br(e($contract['signature_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($contract['legal_notes'])): ?><p><strong>Jurídico:</strong><br><?= nl2br(e($contract['legal_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($contract['notes'])): ?><p><strong>Geral:</strong><br><?= nl2br(e($contract['notes'])) ?></p><?php endif; ?>
                <?php if (!empty($contract['internal_notes']) && can('contracts.edit')): ?><p><strong>Internas:</strong><br><?= nl2br(e($contract['internal_notes'])) ?></p><?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos do patrocinador';
            $createUrl = can('documents.create') ? app_url('/sponsors/' . $sid . '/documents/create') : '';
            $allUrl = app_url('/documents') . '?sponsor_id=' . $sid;
            $emptyText = 'Nenhum documento vinculado ao patrocinador deste contrato.';
            require __DIR__ . '/../documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('financials.view')): ?>
            <?php
            $blockTitle = 'Financeiro';
            $createUrl = can('financials.create') ? app_url('/contracts/' . $cid . '/financials/create') : '';
            $allUrl = app_url('/financials?contract_id=' . $cid);
            $emptyText = 'Nenhum lançamento financeiro vinculado a este contrato.';
            require __DIR__ . '/../financials/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="contract-actions actions-row" style="margin-top:22px;">
            <?php if (can('contracts.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/contracts/' . $cid . '/edit')) ?>" class="btn btn-yellow"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>
            <?php if (can('contracts.approve') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="check-circle"></i> Aprovar internamente</summary>
                    <form method="post" action="<?= e(app_url('/contracts/' . $cid . '/approve')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="form-grid-full"><label>Notas de aprovação</label><textarea name="approval_notes" rows="2"><?= e((string) ($contract['approval_notes'] ?? '')) ?></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Confirmar aprovação</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('contracts.mark_signed') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="pen-tool"></i> Registrar assinatura</summary>
                    <form method="post" action="<?= e(app_url('/contracts/' . $cid . '/mark-signed')) ?>" class="form-box contract-documents" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div><label>Status</label><select name="status"><?php foreach ($statuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= in_array($k, ['assinado','vigente'], true) ? ($k==='assinado'?'selected':'') : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div><label>Assinado em</label><input type="datetime-local" name="signed_at" value="<?= e(str_replace(' ', 'T', substr((string) ($contract['signed_at'] ?? ''), 0, 16))) ?>"></div>
                            <div class="form-grid-full"><label>Notas de assinatura</label><textarea name="signature_notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Registrar assinatura</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('contracts.status') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/contracts/' . $cid . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div><label>Status</label><select name="status"><?php foreach ($statuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div><label>Revisão</label><select name="review_status"><?php foreach ($reviewStatuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $rev===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div><label>Assinatura</label><select name="signature_status"><?php foreach ($signatureStatuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $sig===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div class="form-grid-full"><label>Observação</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('documents.create')): ?>
                <a href="<?= e(app_url('/sponsors/' . $sid . '/documents/create')) ?>" class="btn btn-outline"><i data-lucide="folder-plus"></i> Novo documento</a>
            <?php endif; ?>
            <?php if (can('contracts.archive')): ?>
                <?php if (!$isArchived): ?>
                    <form method="post" action="<?= e(app_url('/contracts/' . $cid . '/archive')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger" data-confirm="Arquivar este contrato?"><i data-lucide="archive"></i> Arquivar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/contracts/' . $cid . '/restore')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-light"><i data-lucide="rotate-ccw"></i> Restaurar</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <article class="card contract-card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="history"></i> Auditoria</h3>
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($contract['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($contract['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($contract['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($contract['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>
    </div>
</section>
