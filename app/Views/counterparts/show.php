<?php
$counterpart = $counterpart ?? [];
$model = $model ?? null;
$categories = $categories ?? [];
$deliveryTypes = $deliveryTypes ?? [];
$statuses = $statuses ?? [];
$priorities = $priorities ?? [];
$documents = $documents ?? [];
$documentSummary = $documentSummary ?? [];
$documentModel = $documentModel ?? null;

$cid = (int) ($counterpart['id'] ?? 0);
$isArchived = !empty($counterpart['archived_at']);
$st = (string) ($counterpart['status'] ?? '');
$pri = (string) ($counterpart['priority'] ?? '');
$overdue = $model && $model->isOverdue($counterpart);
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Contrapartida</span>
                <h1 class="h2-section"><?= e($counterpart['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="counterpart-status badge-cp-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="counterpart-category"><?= e($categories[$counterpart['category'] ?? ''] ?? '') ?></span>
                    <span class="counterpart-priority priority-<?= e($pri) ?>"><?= e($priorities[$pri] ?? $pri) ?></span>
                    <?php if ($overdue): ?><span class="counterpart-alert">Em atraso</span><?php endif; ?>
                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivada</span><?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/counterparts')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="notice notice-info counterpart-alert" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Assinatura digital, portal externo e relatórios avançados serão tratados em etapas futuras.</p>
        </div>

        <div class="detail-grid">
            <article class="card counterpart-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Patrocinador</dt><dd><a href="<?= e(app_url('/sponsors/' . (int) ($counterpart['sponsor_id'] ?? 0))) ?>" class="link-strong"><?= e($counterpart['sponsor_name'] ?? '—') ?></a></dd>
                    <dt>Empresa</dt><dd><?php if (!empty($counterpart['company_id'])): ?><a href="<?= e(app_url('/companies/' . (int) $counterpart['company_id'])) ?>" class="link-strong"><?= e($counterpart['company_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Contato</dt><dd><?php if (!empty($counterpart['contact_id'])): ?><a href="<?= e(app_url('/contacts/' . (int) $counterpart['contact_id'])) ?>" class="link-strong"><?= e($counterpart['contact_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Oportunidade</dt><dd><?php if (!empty($counterpart['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $counterpart['opportunity_id'])) ?>" class="link-strong"><?= e($counterpart['opportunity_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Proposta</dt><dd><?php if (!empty($counterpart['proposal_id'])): ?><a href="<?= e(app_url('/proposals/' . (int) $counterpart['proposal_id'])) ?>" class="link-strong"><?= e($counterpart['proposal_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Cota</dt><dd><?php if (!empty($counterpart['quota_id'])): ?><a href="<?= e(app_url('/quotas/' . (int) $counterpart['quota_id'])) ?>" class="link-strong"><?= e($counterpart['quota_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>

            <article class="card counterpart-card">
                <h3 class="h3-card"><i data-lucide="clipboard-list"></i> Entrega</h3>
                <dl class="meta-list">
                    <dt>Tipo de entrega</dt><dd><?= e($deliveryTypes[$counterpart['delivery_type'] ?? ''] ?? $dash($counterpart['delivery_type'] ?? '')) ?></dd>
                    <dt>Quantidade prometida</dt><dd><?= $counterpart['promised_quantity'] !== null ? e((string) $counterpart['promised_quantity']) . ' ' . e($dash($counterpart['unit'] ?? '')) : '—' ?></dd>
                    <dt>Quantidade entregue</dt><dd class="counterpart-progress"><?= $counterpart['delivered_quantity'] !== null ? e((string) $counterpart['delivered_quantity']) : '—' ?></dd>
                    <dt>Prazo</dt><dd class="<?= $overdue ? 'overdue' : '' ?>"><?= e($dash($counterpart['due_date'] ?? '')) ?></dd>
                    <dt>Início</dt><dd><?= e($dash($counterpart['started_at'] ?? '')) ?></dd>
                    <dt>Entrega</dt><dd><?= e($dash($counterpart['delivered_at'] ?? '')) ?></dd>
                    <dt>Aprovação</dt><dd><?= e($dash($counterpart['approved_at'] ?? '')) ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($counterpart['responsible_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card counterpart-card">
                <h3 class="h3-card"><i data-lucide="file-check"></i> Evidências</h3>
                <dl class="meta-list">
                    <dt>Descrição</dt><dd><?= !empty($counterpart['evidence_description']) ? nl2br(e($counterpart['evidence_description'])) : '—' ?></dd>
                    <dt>URL</dt><dd><?php if (!empty($counterpart['evidence_url'])): ?><a href="<?= e($counterpart['evidence_url']) ?>" target="_blank" rel="noopener" class="link-strong"><?= e($counterpart['evidence_url']) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Documento</dt><dd><?php if (!empty($counterpart['evidence_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $counterpart['evidence_document_id'])) ?>" class="link-strong"><?= e($counterpart['evidence_document_title'] ?? ('#' . (int) $counterpart['evidence_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($counterpart['description']) || !empty($counterpart['notes']) || (!empty($counterpart['internal_notes']) && can('counterparts.edit'))): ?>
            <article class="card counterpart-card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="message-square"></i> Observações</h3>
                <?php if (!empty($counterpart['description'])): ?><p><strong>Descrição:</strong><br><?= nl2br(e($counterpart['description'])) ?></p><?php endif; ?>
                <?php if (!empty($counterpart['notes'])): ?><p><strong>Geral:</strong><br><?= nl2br(e($counterpart['notes'])) ?></p><?php endif; ?>
                <?php if (!empty($counterpart['internal_notes']) && can('counterparts.edit')): ?><p><strong>Internas:</strong><br><?= nl2br(e($counterpart['internal_notes'])) ?></p><?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos da contrapartida';
            $createUrl = can('documents.create') ? app_url('/counterparts/' . $cid . '/documents/create') : '';
            $allUrl = app_url('/documents') . '?counterpart_id=' . $cid;
            $emptyText = 'Nenhum documento vinculado a esta contrapartida.';
            require __DIR__ . '/../documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="counterpart-actions actions-row" style="margin-top:22px;">
            <?php if (can('counterparts.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/counterparts/' . $cid . '/edit')) ?>" class="btn btn-yellow"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>
            <?php if (can('counterparts.deliver') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="package-check"></i> Registrar entrega</summary>
                    <form method="post" action="<?= e(app_url('/counterparts/' . $cid . '/deliver')) ?>" class="form-box counterpart-evidence" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div><label>Quantidade entregue</label><input type="text" name="delivered_quantity" value="<?= e((string) ($counterpart['delivered_quantity'] ?? '')) ?>"></div>
                            <div class="form-grid-full"><label>Descrição evidência</label><textarea name="evidence_description" rows="2"></textarea></div>
                            <div class="form-grid-full"><label>URL evidência</label><input type="url" name="evidence_url" maxlength="255"></div>
                            <div class="form-grid-full"><label>Observação</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Confirmar entrega</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('counterparts.status') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/counterparts/' . $cid . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div><label>Status</label><select name="status"><?php foreach ($statuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $st===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                            <div class="form-grid-full"><label>Observação</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('documents.create')): ?>
                <a href="<?= e(app_url('/counterparts/' . $cid . '/documents/create')) ?>" class="btn btn-outline"><i data-lucide="folder-plus"></i> Novo documento</a>
            <?php endif; ?>
            <?php if (can('counterparts.archive')): ?>
                <?php if (!$isArchived): ?>
                    <form method="post" action="<?= e(app_url('/counterparts/' . $cid . '/archive')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger" data-confirm="Arquivar esta contrapartida?"><i data-lucide="archive"></i> Arquivar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/counterparts/' . $cid . '/restore')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-light"><i data-lucide="rotate-ccw"></i> Restaurar</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <article class="card counterpart-card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="history"></i> Auditoria</h3>
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($counterpart['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($counterpart['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($counterpart['updated_by_name'] ?? '')) ?></dd>
                <dt>Entregue por</dt><dd><?= e($dash($counterpart['delivered_by_name'] ?? '')) ?></dd>
                <dt>Aprovado por</dt><dd><?= e($dash($counterpart['approved_by_name'] ?? '')) ?></dd>
            </dl>
        </article>
    </div>
</section>
