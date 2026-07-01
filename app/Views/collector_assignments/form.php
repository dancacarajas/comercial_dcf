<?php
$collector = $collector ?? [];
$data = $data ?? [];
$errors = $errors ?? [];
$companies = $companies ?? [];
$projects = $projects ?? [];
$types = $types ?? [];

$collectorId = (int) ($collector['id'] ?? 0);
$action = app_url('/collectors/' . $collectorId . '/assignments');
$v = static fn (string $k, string $default = ''): string => e((string) ($data[$k] ?? $default));
$sel = static fn (string $k, string $val): string => (string) ($data[$k] ?? '') === $val ? 'selected' : '';
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Atribuição comercial</span>
        <h1 class="h2-section">Autorizar abordagem</h1>
        <p class="page-sub"><?= e($collector['name'] ?? 'Captador') ?> · <?= e($collector['collector_code'] ?? '') ?></p>
    </div>
    <a href="<?= e(app_url('/collectors/' . $collectorId)) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>

<div class="alert alert-info" style="margin-bottom:16px;">
    Registra qual empresa este captador está autorizado a abordar. Atribuições <strong>exclusivas</strong> impedem que outro captador receba a mesma empresa no período.
</div>

<form method="post" action="<?= e($action) ?>" class="form-card">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="form-grid-full"><label for="company_id">Empresa *</label>
            <select id="company_id" name="company_id" class="input" required>
                <option value="">Selecione a empresa</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $sel('company_id', (string) $c['id']) ?>><?= e($c['name'] ?? ('#' . (int) $c['id'])) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['company_id'])): ?><span class="field-error"><?= e($errors['company_id']) ?></span><?php endif; ?>
        </div>
        <div class="form-grid-full"><label for="incentive_project_id">Projeto incentivado *</label>
            <select id="incentive_project_id" name="incentive_project_id" class="input" required>
                <option value="">Selecione o projeto</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $sel('incentive_project_id', (string) $p['id']) ?>><?= e($p['label'] ?? ('#' . (int) $p['id'])) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['incentive_project_id'])): ?><span class="field-error"><?= e($errors['incentive_project_id']) ?></span><?php endif; ?>
        </div>
        <div><label for="assignment_type">Tipo *</label>
            <select id="assignment_type" name="assignment_type" class="input" required>
                <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('assignment_type', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <?php if (!empty($errors['assignment_type'])): ?><span class="field-error"><?= e($errors['assignment_type']) ?></span><?php endif; ?>
        </div>
        <div><label for="exclusive_until">Exclusiva até</label><input type="date" id="exclusive_until" name="exclusive_until" class="input" value="<?= $v('exclusive_until') ?>"><?php if (!empty($errors['exclusive_until'])): ?><span class="field-error"><?= e($errors['exclusive_until']) ?></span><?php endif; ?></div>
        <div class="form-grid-full"><label for="notes">Observações</label><textarea id="notes" name="notes" class="input" rows="3"><?= $v('notes') ?></textarea></div>
    </div>

    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow">Registrar atribuição</button>
        <a href="<?= e(app_url('/collectors/' . $collectorId)) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
</div></section>
