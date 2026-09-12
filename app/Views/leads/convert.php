<?php
$lead = $lead ?? []; $companies = $companies ?? []; $owners = $owners ?? []; $projects = $projects ?? [];
$simulation = $simulation ?? null;
$suggestedValue = $suggestedValue ?? null;
$investmentLabel = $investmentLabel ?? null;
$id = (int)($lead['id']??0);
$selectedProject = (int)($lead['incentive_project_id'] ?? 0);
$simModel = new \App\Models\SponsorshipSimulation();
$projectLockedBySimulation = false;
if (is_array($simulation) && !empty($simulation['incentive_project_id'])) {
    $selectedProject = (int) $simulation['incentive_project_id'];
    $projectLockedBySimulation = true;
}
?>
<section class="section"><div class="container">
<div class="page-head"><div><span class="kicker kicker-dark">Conversão</span><h1 class="h2-section">Converter lead</h1>
<p class="page-sub"><?= e($lead['name']??'') ?> — <?= e($lead['company_name']??'') ?></p></div>
<a href="<?= e(app_url('/leads/'.$id)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a></div>

<?php if (is_array($simulation) && !empty($simulation['id'])): ?>
<article class="card" style="margin-bottom:18px;">
<h3 class="h3-card">Referência da simulação</h3>
<dl class="meta-list">
<dt>Configuração recomendada</dt><dd><?= e($simModel->tierLabel($simulation['primary_tier_ref'] ?? null)) ?></dd>
<dt>Território</dt><dd><?= e((string)($simulation['primary_axis_ref'] ?? '—')) ?></dd>
<dt>Aderência</dt><dd><?= e($simModel->fitLabel($simulation['fit_level'] ?? null)) ?></dd>
<dt>Disponibilidade</dt><dd><?= e($simModel->availabilityLabel($simulation['availability_status'] ?? null)) ?></dd>
<dt>Investimento declarado</dt><dd><?= e((string)($investmentLabel ?: '—')) ?></dd>
</dl>
<p class="page-sub mb-0">A recomendação é referência. A cota (<code>quota_id</code>) não é pré-selecionada — escolha humana na oportunidade.</p>
</article>
<?php endif; ?>

<form method="post" action="<?= e(app_url('/leads/'.$id.'/convert')) ?>" class="form-box conversion-box">
<?= csrf_field() ?>

<article class="conversion-step"><h3 class="h3-card"><i data-lucide="building-2"></i> 1. Empresa</h3>
<label class="check-inline"><input type="checkbox" name="do_company" value="1" checked> Converter em empresa</label>
<div class="form-grid" style="margin-top:12px;">
<div><label>Criar nova empresa</label><input type="checkbox" name="create_company" value="1" checked></div>
<div><label>Nome da empresa</label><input type="text" name="company_name" value="<?= e($lead['company_name']??$lead['name']??'') ?>"></div>
<div><label>Ou vincular existente</label><select name="existing_company_id"><option value="">—</option>
<?php foreach ($companies as $co): ?><option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?></option><?php endforeach; ?>
</select></div></div></article>

<article class="conversion-step"><h3 class="h3-card"><i data-lucide="user"></i> 2. Contato</h3>
<label class="check-inline"><input type="checkbox" name="do_contact" value="1" checked> Converter em contato</label>
<div class="form-grid" style="margin-top:12px;">
<div><label>Criar novo contato</label><input type="checkbox" name="create_contact" value="1" checked></div>
<div><label>Nome do contato</label><input type="text" name="contact_name" value="<?= e($lead['name']??'') ?>"></div>
<div><label>Ou vincular existente (ID)</label><input type="number" name="existing_contact_id" placeholder="ID do contato"></div>
</div></article>

<article class="conversion-step"><h3 class="h3-card"><i data-lucide="target"></i> 3. Oportunidade</h3>
<label class="check-inline"><input type="checkbox" name="do_opportunity" value="1" <?= $simulation ? 'checked' : '' ?>> Criar oportunidade</label>
<div class="form-grid" style="margin-top:12px;">
<div class="col-span-2"><label>Título</label><input type="text" name="opportunity_title" value="Oportunidade — <?= e($lead['company_name']??$lead['name']??'Lead') ?>"></div>
<div class="col-span-2"><label>Projeto incentivado *</label>
<?php if ($projectLockedBySimulation): ?>
<input type="hidden" name="incentive_project_id" value="<?= $selectedProject ?>">
<select disabled><option value="">— Selecione —</option>
<?php foreach ($projects as $project): ?>
<option value="<?= (int)$project['id'] ?>" <?= $selectedProject === (int)$project['id'] ? 'selected' : '' ?>><?= e($project['label'] ?? '') ?></option>
<?php endforeach; ?>
</select>
<small>Projeto fixado pela simulação de patrocínio.</small>
<?php else: ?>
<select name="incentive_project_id"><option value="">— Selecione —</option>
<?php foreach ($projects as $project): ?>
<option value="<?= (int)$project['id'] ?>" <?= $selectedProject === (int)$project['id'] ? 'selected' : '' ?>><?= e($project['label'] ?? '') ?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>
</div>
<div><label>Valor estimado</label>
<input type="number" step="0.01" name="estimated_value" value="<?= $suggestedValue !== null ? e((string)$suggestedValue) : '' ?>" placeholder="<?= $simulation && $suggestedValue === null ? 'Deixe vazio (range/aberto)' : '' ?>">
<?php if ($investmentLabel): ?><small>Declarado na simulação: <?= e($investmentLabel) ?></small><?php endif; ?>
</div>
<div><label>Criar nova</label><input type="checkbox" name="create_opportunity" value="1" checked></div>
</div></article>

<article class="conversion-step"><h3 class="h3-card"><i data-lucide="list-checks"></i> 4. Tarefa de follow-up</h3>
<label class="check-inline"><input type="checkbox" name="do_task" value="1" checked> Criar tarefa</label>
<div class="form-grid" style="margin-top:12px;">
<div class="col-span-2"><label>Título da tarefa</label><input type="text" name="task_title" value="Follow-up lead — <?= e($lead['name']??'') ?>"></div>
<div><label>Responsável</label><select name="task_assigned_user_id"><option value="">Eu</option>
<?php foreach ($owners as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
</select></div>
<div><label>Criar tarefa</label><input type="checkbox" name="create_task" value="1" checked></div>
</div></article>

<div class="actions-row" style="margin-top:22px;"><button type="submit" class="btn btn-yellow"><i data-lucide="git-merge"></i> Executar conversão</button></div>
</form></div></section>
