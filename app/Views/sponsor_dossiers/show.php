<?php
$dossier = $dossier ?? [];
$model = $model ?? null;
$itemModel = $itemModel ?? null;
$items = $items ?? [];
$dossierTypes = $dossierTypes ?? [];
$statuses = $statuses ?? [];
$deliveryStatuses = $deliveryStatuses ?? [];
$itemTypes = $itemTypes ?? [];
$itemStatuses = $itemStatuses ?? [];
$evidenceStatuses = $evidenceStatuses ?? [];
$documents = $documents ?? [];
$documentSummary = $documentSummary ?? [];
$documentModel = $documentModel ?? null;

$did = (int) ($dossier['id'] ?? 0);
$sid = (int) ($dossier['sponsor_id'] ?? 0);
$isArchived = !empty($dossier['archived_at']);
$st = (string) ($dossier['status'] ?? '');
$delivery = (string) ($dossier['delivery_status'] ?? '');
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;

$periodLabel = '';
if (!empty($dossier['period_start']) || !empty($dossier['period_end'])) {
    $periodLabel = trim(($dossier['period_start'] ?? '') . ' — ' . ($dossier['period_end'] ?? ''), ' —');
}

$pendingCp = (int) ($dossier['counterparts_pending_count'] ?? 0);
$overdueCp = (int) ($dossier['counterparts_overdue_count'] ?? 0);
$remaining = (float) ($dossier['financial_remaining_amount'] ?? 0);
$overdueFin = (int) ($dossier['financial_overdue_count'] ?? 0);
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Prestação de contas</span>
                <h1 class="h2-section"><?= e($dossier['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <?php if (!empty($dossier['dossier_number'])): ?><span class="pill"><?= e($dossier['dossier_number']) ?></span><?php endif; ?>
                    <?php if ($periodLabel !== ''): ?><span class="pill"><?= e($periodLabel) ?></span><?php endif; ?>
                    <span class="dossier-status badge-dossier-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="dossier-delivery-status badge-delivery-<?= e($delivery) ?>"><?= e($deliveryStatuses[$delivery] ?? $delivery) ?></span>
                    <span class="dossier-type"><?= e($dossierTypes[$dossier['dossier_type'] ?? ''] ?? '') ?></span>
                    <?php if ($overdueCp > 0): ?><span class="dossier-alert">Contrapartidas em atraso</span><?php endif; ?>
                    <?php if ($remaining > 0): ?><span class="dossier-alert">Saldo financeiro pendente</span><?php endif; ?>
                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/sponsor-dossiers')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="notice notice-info dossier-alert" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Portal externo do patrocinador, assinatura digital integrada e entrega automatizada serão tratados em etapas futuras. Este módulo consolida dados internos para prestação comercial.</p>
        </div>

        <div class="dossier-metrics metrics-grid" style="margin-bottom:18px;">
            <article class="card dossier-card metric-card">
                <h4 class="h4-card"><i data-lucide="file-signature"></i> Contratos</h4>
                <p class="metric-value"><?= (int) ($dossier['contracts_count'] ?? 0) ?></p>
                <p class="metric-sub"><?= (int) ($dossier['signed_contracts_count'] ?? 0) ?> assinado(s)</p>
            </article>
            <article class="card dossier-card metric-card">
                <h4 class="h4-card"><i data-lucide="list-checks"></i> Contrapartidas</h4>
                <p class="metric-value"><?= (int) ($dossier['counterparts_count'] ?? 0) ?></p>
                <p class="metric-sub">
                    <?= (int) ($dossier['counterparts_delivered_count'] ?? 0) ?> entregue(s)
                    · <?= $pendingCp ?> pendente(s)
                    <?php if ($overdueCp > 0): ?><span class="dossier-alert">· <?= $overdueCp ?> atrasada(s)</span><?php endif; ?>
                </p>
            </article>
            <article class="card dossier-card metric-card">
                <h4 class="h4-card"><i data-lucide="wallet"></i> Financeiro</h4>
                <p class="metric-value money-value"><?= e(money_br($dossier['financial_planned_amount'] ?? 0)) ?></p>
                <p class="metric-sub">
                    Recebido <?= e(money_br($dossier['financial_received_amount'] ?? 0)) ?>
                    · Saldo <?= e(money_br($remaining)) ?>
                    <?php if ($overdueFin > 0): ?><span class="dossier-alert">· <?= $overdueFin ?> atraso(s)</span><?php endif; ?>
                </p>
            </article>
            <article class="card dossier-card metric-card">
                <h4 class="h4-card"><i data-lucide="folder"></i> Documentos</h4>
                <p class="metric-value"><?= (int) ($dossier['documents_count'] ?? 0) ?></p>
                <p class="metric-sub"><?= (int) ($dossier['evidence_documents_count'] ?? 0) ?> evidência(s)</p>
            </article>
        </div>

        <div class="detail-grid">
            <article class="card dossier-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Patrocinador</dt><dd><a href="<?= e(app_url('/sponsors/' . $sid)) ?>" class="link-strong"><?= e($dossier['sponsor_name'] ?? '—') ?></a></dd>
                    <dt>Contrato</dt><dd><?php if (!empty($dossier['main_contract_id'])): ?><a href="<?= e(app_url('/contracts/' . (int) $dossier['main_contract_id'])) ?>" class="link-strong"><?= e($dossier['contract_title'] ?? $dossier['contract_number'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Empresa</dt><dd><?php if (!empty($dossier['company_id'])): ?><a href="<?= e(app_url('/companies/' . (int) $dossier['company_id'])) ?>" class="link-strong"><?= e($dossier['company_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Contato</dt><dd><?php if (!empty($dossier['contact_id'])): ?><a href="<?= e(app_url('/contacts/' . (int) $dossier['contact_id'])) ?>" class="link-strong"><?= e($dossier['contact_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Oportunidade</dt><dd><?php if (!empty($dossier['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $dossier['opportunity_id'])) ?>" class="link-strong"><?= e($dossier['opportunity_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Proposta</dt><dd><?php if (!empty($dossier['proposal_id'])): ?><a href="<?= e(app_url('/proposals/' . (int) $dossier['proposal_id'])) ?>" class="link-strong"><?= e($dossier['proposal_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Cota</dt><dd><?php if (!empty($dossier['quota_id'])): ?><a href="<?= e(app_url('/quotas/' . (int) $dossier['quota_id'])) ?>" class="link-strong"><?= e($dossier['quota_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($dossier['responsible_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card dossier-card">
                <h3 class="h3-card"><i data-lucide="layers"></i> Inclusões</h3>
                <dl class="meta-list meta-list-inline">
                    <dt>Contratos</dt><dd><?= !empty($dossier['include_contracts']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Contrapartidas</dt><dd><?= !empty($dossier['include_counterparts']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Financeiro</dt><dd><?= !empty($dossier['include_financials']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Documentos</dt><dd><?= !empty($dossier['include_documents']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Evidências</dt><dd><?= !empty($dossier['include_evidence']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Clipping</dt><dd><?= !empty($dossier['include_clipping']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Mídia</dt><dd><?= !empty($dossier['include_media']) ? 'Sim' : 'Não' ?></dd>
                </dl>
            </article>

            <article class="card dossier-card dossier-documents">
                <h3 class="h3-card"><i data-lucide="folder"></i> Documentos vinculados</h3>
                <dl class="meta-list">
                    <dt>Documento principal</dt><dd><?php if (!empty($dossier['main_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $dossier['main_document_id'])) ?>" class="link-strong"><?= e($dossier['main_document_title'] ?? ('#' . (int) $dossier['main_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Documento final</dt><dd><?php if (!empty($dossier['final_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $dossier['final_document_id'])) ?>" class="link-strong"><?= e($dossier['final_document_title'] ?? ('#' . (int) $dossier['final_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Comprovante de entrega</dt><dd><?php if (!empty($dossier['delivery_receipt_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $dossier['delivery_receipt_document_id'])) ?>" class="link-strong"><?= e($dossier['delivery_document_title'] ?? ('#' . (int) $dossier['delivery_receipt_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>

            <article class="card dossier-card">
                <h3 class="h3-card"><i data-lucide="calendar-clock"></i> Marcos</h3>
                <dl class="meta-list">
                    <dt>Gerado em</dt><dd><?= e($dash($dossier['generated_at'] ?? '')) ?> <?= !empty($dossier['generated_by_name']) ? '· ' . e($dossier['generated_by_name']) : '' ?></dd>
                    <dt>Aprovado em</dt><dd><?= e($dash($dossier['approved_at'] ?? '')) ?> <?= !empty($dossier['approved_by_name']) ? '· ' . e($dossier['approved_by_name']) : '' ?></dd>
                    <dt>Entregue em</dt><dd><?= e($dash($dossier['delivered_at'] ?? '')) ?> <?= !empty($dossier['delivered_by_name']) ? '· ' . e($dossier['delivered_by_name']) : '' ?></dd>
                </dl>
            </article>
        </div>

        <?php
        $summaryFields = [
            'executive_summary' => 'Resumo executivo',
            'commercial_summary' => 'Resumo comercial',
            'counterparts_summary' => 'Contrapartidas',
            'financial_summary' => 'Financeiro',
            'documents_summary' => 'Documentos',
            'pending_notes' => 'Pendências',
            'approval_notes' => 'Aprovação',
            'delivery_notes' => 'Entrega',
            'notes' => 'Observações gerais',
        ];
        $hasSummaries = false;
        foreach ($summaryFields as $key => $label) {
            if (!empty($dossier[$key])) {
                $hasSummaries = true;
                break;
            }
        }
        if (!empty($dossier['internal_notes']) && can('dossiers.edit')) {
            $hasSummaries = true;
        }
        ?>
        <?php if ($hasSummaries): ?>
            <article class="card dossier-card dossier-section" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="file-text"></i> Resumos e observações</h3>
                <?php foreach ($summaryFields as $key => $label): ?>
                    <?php if (!empty($dossier[$key])): ?>
                        <p><strong><?= e($label) ?>:</strong><br><?= nl2br(e($dossier[$key])) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($dossier['internal_notes']) && can('dossiers.edit')): ?>
                    <p><strong>Internas:</strong><br><?= nl2br(e($dossier['internal_notes'])) ?></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <?php require __DIR__ . '/_items.php'; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos do patrocinador';
            $createUrl = can('documents.create') ? app_url('/sponsor-dossiers/' . $did . '/documents/create') : '';
            $allUrl = app_url('/documents') . '?sponsor_id=' . $sid;
            $emptyText = 'Nenhum documento vinculado ao patrocinador deste dossiê.';
            require __DIR__ . '/../documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="dossier-actions actions-row" style="margin-top:22px;">
            <?php if (can('dossiers.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/sponsor-dossiers/' . $did . '/edit')) ?>" class="btn btn-yellow"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>
            <?php if (can('dossiers.generate') && !$isArchived): ?>
                <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/generate')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light" data-confirm="Gerar/atualizar consolidação deste dossiê?"><i data-lucide="refresh-cw"></i> Gerar consolidação</button>
                </form>
            <?php endif; ?>
            <?php if (can('dossiers.approve') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="check-circle"></i> Aprovar</summary>
                    <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/approve')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="form-grid-full">
                                <label>Observações de aprovação</label>
                                <textarea name="approval_notes" rows="2"><?= e((string) ($dossier['approval_notes'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Aprovar internamente</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('dossiers.deliver') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="send"></i> Registrar entrega</summary>
                    <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/deliver')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label>Entregue em</label>
                                <input type="datetime-local" name="delivered_at" value="<?= e(str_replace(' ', 'T', substr((string) (date('Y-m-d H:i')), 0, 16))) ?>">
                            </div>
                            <div>
                                <label>Status de entrega</label>
                                <select name="delivery_status">
                                    <?php foreach ($deliveryStatuses as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= $delivery === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grid-full">
                                <label>Observações de entrega</label>
                                <textarea name="delivery_notes" rows="2"><?= e((string) ($dossier['delivery_notes'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Registrar entrega</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('dossiers.status') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label>Status</label>
                                <select name="status">
                                    <?php foreach ($statuses as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Entrega</label>
                                <select name="delivery_status">
                                    <?php foreach ($deliveryStatuses as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= $delivery === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grid-full"><label>Observação</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar</button>
                    </form>
                </details>
            <?php endif; ?>
            <a href="#dossier-items" class="btn btn-outline"><i data-lucide="list"></i> Itens</a>
            <?php if (can('documents.create')): ?>
                <a href="<?= e(app_url('/documents/create') . '?sponsor_id=' . $sid . '&sponsor_dossier_id=' . $did) ?>" class="btn btn-outline"><i data-lucide="folder-plus"></i> Novo documento</a>
            <?php endif; ?>
            <a href="<?= e(app_url('/sponsor-dossiers/' . $did . '/print')) ?>" class="btn btn-outline" target="_blank" rel="noopener"><i data-lucide="printer"></i> Imprimir</a>
            <?php if (can('dossiers.archive')): ?>
                <?php if (!$isArchived): ?>
                    <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/archive')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger" data-confirm="Arquivar este dossiê?"><i data-lucide="archive"></i> Arquivar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/restore')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-light"><i data-lucide="rotate-ccw"></i> Restaurar</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <article class="card dossier-card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="history"></i> Auditoria</h3>
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($dossier['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($dossier['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($dossier['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($dossier['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>
    </div>
</section>
