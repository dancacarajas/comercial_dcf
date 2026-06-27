<?php
$sponsor = $sponsor ?? [];
$model = $model ?? null;
$sponsorshipTypes = $sponsorshipTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$statuses = $statuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$documents = $documents ?? [];
$documentSummary = $documentSummary ?? [];
$documentModel = $documentModel ?? null;

$sid = (int) ($sponsor['id'] ?? 0);
$isArchived = !empty($sponsor['archived_at']);
$st = (string) ($sponsor['status'] ?? '');
$pay = (string) ($sponsor['payment_status'] ?? '');
$overdue = $model && $model->isOverdue($sponsor);
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Fechamento comercial</span>
                <h1 class="h2-section"><?= e($sponsor['sponsor_display_name'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="sponsor-status badge-sponsor-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="sponsor-payment-status badge-pay-<?= e($pay) ?>"><?= e($paymentStatuses[$pay] ?? $pay) ?></span>
                    <span class="sponsor-type"><?= e($sponsorshipTypes[$sponsor['sponsorship_type'] ?? ''] ?? '') ?></span>
                    <?php if ($overdue): ?><span class="sponsor-alert">Em atraso</span><?php endif; ?>
                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/sponsors')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="notice notice-info sponsor-alert" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Assinatura digital integrada e portal externo do patrocinador serão tratados em etapas futuras.</p>
        </div>

        <div class="detail-grid">
            <article class="card sponsor-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Empresa</dt><dd><a href="<?= e(app_url('/companies/' . (int) ($sponsor['company_id'] ?? 0))) ?>" class="link-strong"><?= e($sponsor['company_name'] ?? '—') ?></a></dd>
                    <dt>Contato</dt><dd><?php if (!empty($sponsor['contact_id'])): ?><a href="<?= e(app_url('/contacts/' . (int) $sponsor['contact_id'])) ?>" class="link-strong"><?= e($sponsor['contact_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Oportunidade</dt><dd><?php if (!empty($sponsor['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $sponsor['opportunity_id'])) ?>" class="link-strong"><?= e($sponsor['opportunity_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Proposta</dt><dd><?php if (!empty($sponsor['proposal_id'])): ?><a href="<?= e(app_url('/proposals/' . (int) $sponsor['proposal_id'])) ?>" class="link-strong"><?= e($sponsor['proposal_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Cota</dt><dd><?php if (!empty($sponsor['quota_id'])): ?><a href="<?= e(app_url('/quotas/' . (int) $sponsor['quota_id'])) ?>" class="link-strong"><?= e($sponsor['quota_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Documento principal</dt><dd><?php if (!empty($sponsor['primary_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $sponsor['primary_document_id'])) ?>" class="link-strong"><?= e($sponsor['primary_document_title'] ?? ('#' . (int) $sponsor['primary_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>

            <article class="card sponsor-card">
                <h3 class="h3-card"><i data-lucide="banknote"></i> Valores</h3>
                <dl class="meta-list">
                    <dt>Comprometido</dt><dd class="sponsor-value money-value"><?= $sponsor['committed_amount'] !== null ? e(money_br($sponsor['committed_amount'])) : '—' ?></dd>
                    <dt>Confirmado</dt><dd class="sponsor-value money-value"><?= $sponsor['confirmed_amount'] !== null ? e(money_br($sponsor['confirmed_amount'])) : '—' ?></dd>
                    <?php if (!empty($sponsor['quota_snapshot_name'])): ?>
                        <dt>Snapshot cota</dt><dd><?= e($sponsor['quota_snapshot_name']) ?> — <?= $sponsor['quota_snapshot_amount'] !== null ? e(money_br($sponsor['quota_snapshot_amount'])) : '—' ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($sponsor['in_kind_description'])): ?>
                        <dt>Permuta</dt><dd><?= nl2br(e($sponsor['in_kind_description'])) ?></dd>
                        <dt>Valor estimado permuta</dt><dd class="money-value"><?= $sponsor['in_kind_estimated_value'] !== null ? e(money_br($sponsor['in_kind_estimated_value'])) : '—' ?></dd>
                    <?php endif; ?>
                </dl>
            </article>

            <article class="card sponsor-card">
                <h3 class="h3-card"><i data-lucide="calendar-clock"></i> Datas e incentivo</h3>
                <dl class="meta-list">
                    <dt>Ano / Edição</dt><dd><?= e((string) ($sponsor['project_year'] ?? '')) ?> — <?= e($dash($sponsor['festival_edition'] ?? '')) ?></dd>
                    <dt>Fechamento</dt><dd><?= e($dash($sponsor['closed_at'] ?? '')) ?></dd>
                    <dt>Confirmação</dt><dd><?= e($dash($sponsor['confirmed_at'] ?? '')) ?></dd>
                    <dt>Previsão aporte</dt><dd class="<?= $overdue ? 'overdue' : '' ?>"><?= e($dash($sponsor['expected_payment_date'] ?? '')) ?></dd>
                    <dt>Recebimento</dt><dd><?= e($dash($sponsor['received_at'] ?? '')) ?></dd>
                    <dt>PRONAC</dt><dd><?= e($dash($sponsor['pronac_number'] ?? '')) ?></dd>
                    <dt>Mecanismo</dt><dd><?= e($fundingMechanisms[$sponsor['funding_mechanism'] ?? ''] ?? $dash($sponsor['funding_mechanism'] ?? '')) ?></dd>
                    <dt>Anúncio público</dt><dd><?= !empty($sponsor['public_announcement_allowed']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($sponsor['responsible_name'] ?? '')) ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($sponsor['notes']) || !empty($sponsor['internal_notes']) || !empty($sponsor['incentive_notes'])): ?>
            <article class="card sponsor-card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="message-square"></i> Observações</h3>
                <?php if (!empty($sponsor['notes'])): ?><p><strong>Geral:</strong><br><?= nl2br(e($sponsor['notes'])) ?></p><?php endif; ?>
                <?php if (!empty($sponsor['incentive_notes'])): ?><p><strong>Incentivo:</strong><br><?= nl2br(e($sponsor['incentive_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($sponsor['internal_notes']) && can('sponsors.edit')): ?><p><strong>Internas:</strong><br><?= nl2br(e($sponsor['internal_notes'])) ?></p><?php endif; ?>
            </article>
        <?php endif; ?>

        <?php require dirname(__DIR__) . '/partials/collector_trace.php'; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos do patrocinador';
            $createUrl = can('documents.create') ? app_url('/sponsors/' . $sid . '/documents/create') : '';
            $allUrl = app_url('/documents') . '?sponsor_id=' . $sid;
            $emptyText = 'Nenhum documento vinculado a este patrocinador.';
            require __DIR__ . '/../documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('counterparts.view')): ?>
            <?php
            $blockTitle = 'Contrapartidas';
            $createUrl = can('counterparts.create') ? app_url('/sponsors/' . $sid . '/counterparts/create') : '';
            $allUrl = app_url('/counterparts?sponsor_id=' . $sid);
            $emptyText = 'Nenhuma contrapartida registrada para este patrocinador.';
            require __DIR__ . '/../counterparts/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('contracts.view')): ?>
            <?php
            $blockTitle = 'Contratos';
            $createUrl = can('contracts.create') ? app_url('/sponsors/' . $sid . '/contracts/create') : '';
            $allUrl = app_url('/contracts?sponsor_id=' . $sid);
            $emptyText = 'Nenhum contrato registrado para este patrocinador.';
            require __DIR__ . '/../contracts/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('financials.view')): ?>
            <?php
            $blockTitle = 'Financeiro';
            $createUrl = can('financials.create') ? app_url('/sponsors/' . $sid . '/financials/create') : '';
            $allUrl = app_url('/financials?sponsor_id=' . $sid);
            $emptyText = 'Nenhum lançamento financeiro registrado para este patrocinador.';
            require __DIR__ . '/../financials/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('dossiers.view')): ?>
            <?php
            $blockTitle = 'Dossiês / Prestação Comercial';
            $createUrl = can('dossiers.create') ? app_url('/sponsors/' . $sid . '/dossiers/create') : '';
            $allUrl = app_url('/sponsor-dossiers?sponsor_id=' . $sid);
            $emptyText = 'Nenhum dossiê registrado para este patrocinador.';
            require __DIR__ . '/../sponsor_dossiers/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="sponsor-actions actions-row" style="margin-top:22px;">
            <?php if (can('sponsors.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/sponsors/' . $sid . '/edit')) ?>" class="btn btn-yellow"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>
            <?php if (can('sponsors.confirm') && !$isArchived && $st !== 'confirmado'): ?>
                <form method="post" action="<?= e(app_url('/sponsors/' . $sid . '/confirm')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light" data-confirm="Confirmar este fechamento comercial?"><i data-lucide="check-circle"></i> Confirmar fechamento</button>
                </form>
            <?php endif; ?>
            <?php if (can('sponsors.status') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/sponsors/' . $sid . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div><label>Status</label><select name="status"><?php foreach ($statuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div><label>Pagamento</label><select name="payment_status"><?php foreach ($paymentStatuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $pay===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div class="form-grid-full"><label>Observação</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('documents.create')): ?>
                <a href="<?= e(app_url('/sponsors/' . $sid . '/documents/create')) ?>" class="btn btn-outline"><i data-lucide="folder-plus"></i> Novo documento</a>
            <?php endif; ?>
            <?php if (can('sponsors.archive')): ?>
                <?php if (!$isArchived): ?>
                    <form method="post" action="<?= e(app_url('/sponsors/' . $sid . '/archive')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger" data-confirm="Arquivar este fechamento?"><i data-lucide="archive"></i> Arquivar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/sponsors/' . $sid . '/restore')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-light"><i data-lucide="rotate-ccw"></i> Restaurar</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <article class="card sponsor-card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="history"></i> Auditoria</h3>
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($sponsor['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($sponsor['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($sponsor['updated_by_name'] ?? '')) ?></dd>
                <dt>Confirmado por</dt><dd><?= e($dash($sponsor['confirmed_by_name'] ?? '')) ?></dd>
            </dl>
        </article>
    </div>
</section>
