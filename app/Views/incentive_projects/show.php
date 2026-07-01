<?php
$project = $project ?? [];
$statusLabels = $statusLabels ?? [];
$budgetItems = $budgetItems ?? [];
$metrics = $metrics ?? [];
$captureStatuses = $captureStatuses ?? [];
$id = (int) ($project['id'] ?? 0);
$archived = !empty($project['archived_at']);
$factorPct = $project['commission_factor'] !== null
    ? number_format((float) $project['commission_factor'] * 100, 4, ',', '.') . '%'
    : '—';
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Projeto incentivado<?= !empty($project['edition_year']) ? ' · ' . e($project['edition_year']) : '' ?></span>
        <h1 class="h2-section"><?= e($project['project_name'] ?? '') ?></h1>
        <p class="page-sub">
            Status: <strong><?= e($statusLabels[$project['project_status'] ?? ''] ?? $project['project_status'] ?? '') ?></strong>
            <?php if (!empty($project['pronac_number'])): ?> · PRONAC <?= e($project['pronac_number']) ?><?php endif; ?>
            <?php if ($archived): ?> · <span style="color:#b00;">ARQUIVADO</span><?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= e(app_url('/projects/' . $id . '/dashboard')) ?>" class="btn btn-sm btn-outline">Painel</a>
        <a href="<?= e(app_url('/projects/' . $id . '/budget')) ?>" class="btn btn-sm btn-outline">Orçamento</a>
        <?php if (can('incentive_projects.edit') && !$archived): ?><a href="<?= e(app_url('/projects/' . $id . '/edit')) ?>" class="btn btn-sm btn-yellow">Editar</a><?php endif; ?>
        <a href="<?= e(app_url('/projects')) ?>" class="btn btn-sm btn-outline">Voltar</a>
    </div>
</div>

<div class="card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:18px;">
    <div class="metric-card"><span class="metric-label">Total aprovado</span><strong class="metric-value"><?= money_br($project['approved_total_amount'] ?? null) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Autorizado p/ captação</span><strong class="metric-value"><?= money_br($project['authorized_capture_amount'] ?? null) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Rubrica de captação</span><strong class="metric-value"><?= money_br($project['capture_commission_budget'] ?? null) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Fator de comissão</span><strong class="metric-value"><?= e($factorPct) ?></strong></div>
</div>

<div class="form-card" style="margin-bottom:18px;">
    <h2 class="h3-section">Dados do projeto</h2>
    <div class="table-wrap"><table>
        <tbody>
            <tr><th>Proponente</th><td><?= e($project['proponent_name'] ?? '—') ?> <?= !empty($project['proponent_document']) ? '(' . e($project['proponent_document']) . ')' : '' ?></td></tr>
            <tr><th>Mecanismo</th><td><?= e($project['law_framework'] ?? '—') ?></td></tr>
            <tr><th>Proposta SALIC</th><td><?= e($project['salic_proposal_number'] ?? '—') ?></td></tr>
            <tr><th>Período de captação</th><td><?= e($project['capture_start_date'] ?? '—') ?> a <?= e($project['capture_end_date'] ?? '—') ?></td></tr>
            <tr><th>Conta bancária</th><td><?= e(trim((string)($project['bank_name'] ?? '') . ' ' . (string)($project['bank_agency'] ?? '') . ' ' . (string)($project['bank_account'] ?? ''))) ?: '—' ?></td></tr>
            <?php if (!empty($project['notes'])): ?><tr><th>Observações</th><td><?= nl2br(e($project['notes'])) ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="form-card" style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h2 class="h3-section" style="margin:0;">Captação do projeto</h2>
    </div>
    <div class="card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:12px;">
        <div class="metric-card"><span class="metric-label">Empresas</span><strong class="metric-value"><?= (int) ($metrics['companies_count'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Cotas</span><strong class="metric-value"><?= (int) ($metrics['quotas_count'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Oportunidades</span><strong class="metric-value"><?= (int) ($metrics['opportunities_total'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Propostas</span><strong class="metric-value"><?= (int) ($metrics['proposals_total'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Patrocinadores</span><strong class="metric-value"><?= (int) ($metrics['sponsors_total'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Captadores</span><strong class="metric-value"><?= (int) ($metrics['collectors_count'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Captado (recebido)</span><strong class="metric-value"><?= money_br($metrics['financial_received'] ?? 0) ?></strong></div>
    </div>
    <div class="actions-row" style="margin-top:12px;">
        <a href="<?= e(app_url('/quotas?incentive_project_id=' . $id)) ?>" class="btn btn-sm btn-outline">Ver cotas do projeto</a>
        <?php if (can('quotas.create')): ?>
            <a href="<?= e(app_url('/quotas/create?incentive_project_id=' . $id)) ?>" class="btn btn-sm btn-yellow">Nova cota para este projeto</a>
        <?php endif; ?>
    </div>
</div>

<div class="form-card" style="margin-bottom:18px;">
    <h2 class="h3-section">Rubricas (<?= count($budgetItems) ?>)</h2>
    <?php if ($budgetItems === []): ?>
        <p class="page-sub">Nenhuma rubrica cadastrada. <a href="<?= e(app_url('/projects/' . $id . '/budget')) ?>">Gerenciar orçamento</a>.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Item</th><th>Rubrica</th><th>Etapa</th><th>Valor</th><th>Comissão?</th></tr></thead>
            <tbody>
            <?php foreach ($budgetItems as $bi): ?>
                <tr>
                    <td><?= e($bi['item_number'] ?? '—') ?></td>
                    <td><?= e($bi['budget_item_name'] ?? '') ?></td>
                    <td><?= e($bi['stage'] ?? '—') ?></td>
                    <td><?= money_br($bi['requested_amount'] ?? null) ?></td>
                    <td><?= !empty($bi['is_capture_commission_item']) ? 'Sim' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<div class="actions-row" style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if (can('incentive_projects.activate_capture') && !$archived && !in_array((string) ($project['project_status'] ?? ''), $captureStatuses, true)): ?>
        <form method="post" action="<?= e(app_url('/projects/' . $id . '/activate-capture')) ?>" onsubmit="return confirm('Liberar este projeto para captação?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-yellow">Liberar captação</button>
        </form>
    <?php endif; ?>
    <?php if (can('incentive_projects.archive')): ?>
        <?php if ($archived): ?>
            <form method="post" action="<?= e(app_url('/projects/' . $id . '/restore')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Restaurar</button></form>
        <?php else: ?>
            <form method="post" action="<?= e(app_url('/projects/' . $id . '/archive')) ?>" onsubmit="return confirm('Arquivar este projeto?');"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Arquivar</button></form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div></section>
