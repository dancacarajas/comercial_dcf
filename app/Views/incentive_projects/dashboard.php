<?php
$project = $project ?? [];
$metrics = $metrics ?? [];
$commissionBudget = (float) ($commissionBudget ?? 0);
$id = (int) ($project['id'] ?? 0);

$approved = (float) ($project['approved_total_amount'] ?? 0);
$received = (float) ($metrics['financial_received'] ?? 0);
$pipeline = (float) ($metrics['pipeline_value'] ?? 0);
$proposalsValue = (float) ($metrics['proposals_value'] ?? 0);
$remaining = max(0.0, $approved - $received);
$pctCaptado = $approved > 0 ? round($received / $approved * 100, 1) : 0.0;
$pctRubrica = $commissionBudget > 0 ? round(($received * (float) ($project['commission_factor'] ?? 0)) / $commissionBudget * 100, 1) : 0.0;
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Painel do projeto</span>
        <h1 class="h2-section"><?= e($project['project_name'] ?? '') ?></h1>
        <p class="page-sub">% captado: <strong><?= e(number_format($pctCaptado, 1, ',', '.')) ?>%</strong> · Saldo a captar: <strong><?= money_br($remaining) ?></strong></p>
    </div>
    <a href="<?= e(app_url('/projects/' . $id)) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>

<div class="card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:18px;">
    <div class="metric-card"><span class="metric-label">Total aprovado</span><strong class="metric-value"><?= money_br($approved) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Autorizado p/ captação</span><strong class="metric-value"><?= money_br($project['authorized_capture_amount'] ?? null) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Captado confirmado</span><strong class="metric-value"><?= money_br($received) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Saldo a captar</span><strong class="metric-value"><?= money_br($remaining) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Em propostas enviadas</span><strong class="metric-value"><?= money_br($proposalsValue) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Pipeline (aberto)</span><strong class="metric-value"><?= money_br($pipeline) ?></strong></div>
    <div class="metric-card"><span class="metric-label">Rubrica de captação</span><strong class="metric-value"><?= money_br($commissionBudget) ?></strong></div>
    <div class="metric-card"><span class="metric-label">% captado</span><strong class="metric-value"><?= e(number_format($pctCaptado, 1, ',', '.')) ?>%</strong></div>
</div>

<div class="form-card">
    <h2 class="h3-section">Volume por entidade</h2>
    <div class="card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:12px;">
        <div class="metric-card"><span class="metric-label">Empresas prospectadas</span><strong class="metric-value"><?= (int) ($metrics['companies_count'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Cotas</span><strong class="metric-value"><?= (int) ($metrics['quotas_count'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Oportunidades</span><strong class="metric-value"><?= (int) ($metrics['opportunities_total'] ?? 0) ?> (<?= (int) ($metrics['opportunities_open'] ?? 0) ?> abertas)</strong></div>
        <div class="metric-card"><span class="metric-label">Propostas</span><strong class="metric-value"><?= (int) ($metrics['proposals_total'] ?? 0) ?> (<?= (int) ($metrics['proposals_sent'] ?? 0) ?> enviadas)</strong></div>
        <div class="metric-card"><span class="metric-label">Patrocinadores</span><strong class="metric-value"><?= (int) ($metrics['sponsors_total'] ?? 0) ?></strong></div>
        <div class="metric-card"><span class="metric-label">Captadores ativos</span><strong class="metric-value"><?= (int) ($metrics['collectors_count'] ?? 0) ?></strong></div>
    </div>
</div>
</div></section>
