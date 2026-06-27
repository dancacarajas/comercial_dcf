<?php
$proposal = $proposal ?? [];
$model    = $model ?? null;
$types    = $types ?? [];
$statuses = $statuses ?? [];

$pid        = (int) ($proposal['id'] ?? 0);
$isArchived = !empty($proposal['archived_at']);
$st         = (string) ($proposal['status'] ?? '');
$expired    = $model && $model->isExpired($proposal);
$dash       = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Proposta comercial</span>
                <h1 class="h2-section"><?= e($proposal['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-proposal proposal-status badge-proposal-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="badge-proposal badge-proposal-type"><?= e($types[$proposal['type'] ?? ''] ?? ($proposal['type'] ?? '')) ?></span>
                    <span class="proposal-version">v<?= (int) ($proposal['version_number'] ?? 1) ?></span>
                    <?php if ($expired): ?><span class="badge-proposal badge-proposal-expirada">Vencida</span><?php endif; ?>
                    <?php if ($isArchived): ?>
                        <span class="badge-status badge-status-arquivado">Arquivada em <?= e($proposal['archived_at']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/proposals')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card proposal-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Empresa</dt>
                    <dd><a href="<?= e(app_url('/companies/' . (int) ($proposal['company_id'] ?? 0))) ?>" class="link-strong"><?= e($proposal['company_name'] ?? '—') ?></a></dd>
                    <dt>Contato</dt>
                    <dd>
                        <?php if (!empty($proposal['contact_id'])): ?>
                            <a href="<?= e(app_url('/contacts/' . (int) $proposal['contact_id'])) ?>" class="link-strong"><?= e($proposal['contact_name'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Oportunidade</dt>
                    <dd>
                        <?php if (!empty($proposal['opportunity_id'])): ?>
                            <a href="<?= e(app_url('/opportunities/' . (int) $proposal['opportunity_id'])) ?>" class="link-strong"><?= e($proposal['opportunity_title'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Cota</dt>
                    <dd>
                        <?php if (!empty($proposal['quota_id'])): ?>
                            <a href="<?= e(app_url('/quotas/' . (int) $proposal['quota_id'])) ?>" class="link-strong"><?= e($proposal['quota_name'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                </dl>
            </article>

            <article class="card proposal-card">
                <h3 class="h3-card"><i data-lucide="banknote"></i> Valores e versão</h3>
                <dl class="meta-list">
                    <dt>Valor proposto</dt>
                    <dd class="money-value"><?= $proposal['proposed_value'] !== null ? e(money_br($proposal['proposed_value'])) : '—' ?></dd>
                    <dt>Versão</dt>
                    <dd><span class="proposal-version">v<?= (int) ($proposal['version_number'] ?? 1) ?></span></dd>
                    <?php if (!empty($proposal['parent_proposal_id'])): ?>
                        <dt>Versão anterior</dt>
                        <dd><a href="<?= e(app_url('/proposals/' . (int) $proposal['parent_proposal_id'])) ?>" class="link-strong">Proposta #<?= (int) $proposal['parent_proposal_id'] ?></a></dd>
                    <?php endif; ?>
                    <dt>Responsável</dt>
                    <dd><?= e($dash($proposal['responsible_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card proposal-card">
                <h3 class="h3-card"><i data-lucide="calendar-clock"></i> Datas</h3>
                <dl class="meta-list">
                    <dt>Data de criação</dt><dd><?= e($dash($proposal['created_on'] ?? '')) ?></dd>
                    <dt>Data de envio</dt><dd><?= e($dash($proposal['sent_at'] ?? '')) ?></dd>
                    <dt>Validade</dt><dd class="<?= $expired ? 'overdue' : '' ?>"><?= e($dash($proposal['valid_until'] ?? '')) ?></dd>
                </dl>
            </article>

            <?php if (!empty($proposal['pdf_file_path'])): ?>
                <article class="card proposal-card proposal-upload">
                    <h3 class="h3-card"><i data-lucide="file-text"></i> PDF anexado</h3>
                    <p><?= e($proposal['pdf_original_name'] ?? 'proposta.pdf') ?></p>
                    <a href="<?= e(app_url('/proposals/' . $pid . '/pdf')) ?>" class="btn btn-sm btn-yellow" target="_blank" rel="noopener"><i data-lucide="download"></i> Visualizar PDF</a>
                </article>
            <?php endif; ?>
        </div>

        <?php if (!empty($proposal['revision_notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="history"></i> Histórico / notas de revisão</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($proposal['revision_notes']) ?></p>
            </article>
        <?php endif; ?>

        <?php if (!empty($proposal['notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="sticky-note"></i> Observações</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($proposal['notes']) ?></p>
            </article>
        <?php endif; ?>

        <?php require dirname(__DIR__) . '/partials/collector_trace.php'; ?>

        <div class="notice" style="margin-top:20px;">
            <p class="mb-0"><i data-lucide="info"></i> Links públicos, assinatura digital, contratos e portal externo serão tratados em etapas futuras.</p>
        </div>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos da proposta';
            $createUrl  = app_url('/proposals/' . $pid . '/documents/create');
            $allUrl     = app_url('/documents?proposal_id=' . $pid);
            $emptyText  = 'Nenhum documento vinculado a esta proposta ainda.';
            require dirname(__DIR__) . '/documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('sponsors.view')): ?>
            <?php
            $blockTitle = 'Patrocinadores / Fechamentos';
            $createUrl  = app_url('/proposals/' . $pid . '/sponsors/create');
            $allUrl     = app_url('/sponsors?proposal_id=' . $pid);
            $emptyText  = 'Nenhum fechamento comercial vinculado a esta proposta ainda.';
            require dirname(__DIR__) . '/sponsors/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('counterparts.view')): ?>
            <?php
            $blockTitle = 'Contrapartidas';
            $createUrl  = can('counterparts.create') ? app_url('/proposals/' . $pid . '/counterparts/create') : '';
            $allUrl     = app_url('/counterparts?proposal_id=' . $pid);
            $emptyText  = 'Nenhuma contrapartida vinculada a esta proposta ainda.';
            require dirname(__DIR__) . '/counterparts/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('contracts.view')): ?>
            <?php
            $blockTitle = 'Contratos';
            $createUrl  = can('contracts.create') ? app_url('/proposals/' . $pid . '/contracts/create') : '';
            $allUrl     = app_url('/contracts?proposal_id=' . $pid);
            $emptyText  = 'Nenhum contrato vinculado a esta proposta ainda.';
            require dirname(__DIR__) . '/contracts/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('financials.view')): ?>
            <?php
            $blockTitle = 'Financeiro';
            $createUrl  = can('financials.create') ? app_url('/proposals/' . $pid . '/financials/create') : '';
            $allUrl     = app_url('/financials?proposal_id=' . $pid);
            $emptyText  = 'Nenhum lançamento financeiro vinculado a esta proposta ainda.';
            require dirname(__DIR__) . '/financials/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('dossiers.view')): ?>
            <?php
            $blockTitle = 'Dossiês / Prestação Comercial';
            $createUrl  = can('dossiers.create') ? app_url('/proposals/' . $pid . '/dossiers/create') : '';
            $allUrl     = app_url('/sponsor-dossiers?proposal_id=' . $pid);
            $emptyText  = 'Nenhum dossiê vinculado a esta proposta ainda.';
            require dirname(__DIR__) . '/sponsor_dossiers/_summary_block.php';
            ?>
        <?php endif; ?>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($proposal['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($proposal['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($proposal['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($proposal['updated_at'] ?? '')) ?></dd>
                <dt>Enviado por</dt><dd><?= e($dash($proposal['sent_by_name'] ?? '')) ?></dd>
            </dl>
        </article>

        <div class="proposal-actions actions-row" style="margin-top:22px;">
            <?php if (can('proposals.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/proposals/' . $pid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>

            <?php if (can('proposals.version')): ?>
                <a href="<?= e(app_url('/proposals/' . $pid . '/version')) ?>" class="btn btn-outline"><i data-lucide="git-branch"></i> Nova versão</a>
            <?php endif; ?>

            <?php if (can('proposals.send') && !$isArchived && empty($proposal['sent_at'])): ?>
                <form method="post" action="<?= e(app_url('/proposals/' . $pid . '/mark-sent')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="send"></i> Marcar como enviada</button>
                </form>
            <?php endif; ?>

            <?php if (can('proposals.edit') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/proposals/' . $pid . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label for="status">Novo status</label>
                                <select id="status" name="status" required>
                                    <?php foreach ($statuses as $k => $label): ?>
                                        <option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grid-full">
                                <label for="notes">Observação (opcional)</label>
                                <textarea id="notes" name="notes" rows="2" placeholder="Motivo ou contexto da mudança"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar status</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (can('proposals.archive') && !$isArchived): ?>
                <form method="post" action="<?= e(app_url('/proposals/' . $pid . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar esta proposta? Ela sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>

            <?php if (can('proposals.archive') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/proposals/' . $pid . '/restore')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
