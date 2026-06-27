<?php
$collector = $collector ?? [];
$data = $data ?? [];
$errors = $errors ?? [];
$isEdit = !empty($isEdit);
$companies = $companies ?? [];
$opportunities = $opportunities ?? [];
$proposals = $proposals ?? [];
$sponsors = $sponsors ?? [];
$statuses = $statuses ?? [];
$attributionTypes = $attributionTypes ?? [];

$collectorId = (int) ($collector['id'] ?? 0);
$dealId = (int) ($data['id'] ?? 0);
$action = $isEdit
    ? app_url('/collector-deals/' . $dealId . '/update')
    : app_url('/collectors/' . $collectorId . '/deals');
$v = static fn (string $k, string $default = ''): string => e((string) ($data[$k] ?? $default));
$sel = static fn (string $k, int|string $val): string => (string) ($data[$k] ?? '') === (string) $val ? 'selected' : '';
$optLabel = static fn (array $o): string => (string) ($o['title'] ?? ('#' . (int) $o['id'])) . (($o['company_name'] ?? '') !== '' ? ' — ' . (string) $o['company_name'] : '');
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Captação rastreada</span>
        <h1 class="h2-section"><?= $isEdit ? 'Editar captação' : 'Nova captação rastreada' ?></h1>
        <p class="page-sub"><?= e($collector['name'] ?? 'Captador') ?> · <?= e($collector['collector_code'] ?? '') ?></p>
    </div>
    <a href="<?= e(app_url('/collectors/' . $collectorId)) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>

<div class="alert alert-info" style="margin-bottom:16px;">
    Vincule a captação às entidades do funil. Os vínculos permitem rastrear qual oportunidade, proposta ou fechamento nasceu deste captador.
</div>

<form method="post" action="<?= e($action) ?>" class="form-card">
    <?= csrf_field() ?>
    <h3 class="h3-card">Origem</h3>
    <div class="form-grid">
        <div class="form-grid-full"><label for="company_id">Empresa *</label>
            <select id="company_id" name="company_id" class="input" required>
                <option value="">Selecione a empresa</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $sel('company_id', (int) $c['id']) ?>><?= e($c['name'] ?? ('#' . (int) $c['id'])) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['company_id'])): ?><span class="field-error"><?= e($errors['company_id']) ?></span><?php endif; ?>
        </div>
        <div><label for="deal_status">Status da captação *</label>
            <select id="deal_status" name="deal_status" class="input" required>
                <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('deal_status', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <?php if (!empty($errors['deal_status'])): ?><span class="field-error"><?= e($errors['deal_status']) ?></span><?php endif; ?>
        </div>
        <div><label for="attribution_type">Tipo de atribuição *</label>
            <select id="attribution_type" name="attribution_type" class="input" required>
                <?php foreach ($attributionTypes as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('attribution_type', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <?php if (!empty($errors['attribution_type'])): ?><span class="field-error"><?= e($errors['attribution_type']) ?></span><?php endif; ?>
        </div>
        <div class="form-grid-full"><label for="source">Origem (texto)</label><input type="text" id="source" name="source" class="input" value="<?= $v('source') ?>" placeholder="ex.: indicação direta, evento, networking"></div>
    </div>

    <h3 class="h3-card" style="margin-top:18px;">Vínculos com o funil (opcionais)</h3>
    <div class="form-grid">
        <div><label for="opportunity_id">Oportunidade</label>
            <select id="opportunity_id" name="opportunity_id" class="input">
                <option value="">—</option>
                <?php foreach ($opportunities as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('opportunity_id', (int) $o['id']) ?>><?= e($optLabel($o)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label for="proposal_id">Proposta</label>
            <select id="proposal_id" name="proposal_id" class="input">
                <option value="">—</option>
                <?php foreach ($proposals as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('proposal_id', (int) $o['id']) ?>><?= e($optLabel($o)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label for="sponsor_id">Patrocinador</label>
            <select id="sponsor_id" name="sponsor_id" class="input">
                <option value="">—</option>
                <?php foreach ($sponsors as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('sponsor_id', (int) $o['id']) ?>><?= e($optLabel($o)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid-full"><label for="notes">Observações</label><textarea id="notes" name="notes" class="input" rows="3"><?= $v('notes') ?></textarea></div>
    </div>

    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow"><?= $isEdit ? 'Salvar captação' : 'Registrar captação' ?></button>
        <a href="<?= e(app_url('/collectors/' . $collectorId)) ?>" class="btn btn-outline">Cancelar</a>
        <?php if ($isEdit): ?>
            <form method="post" action="<?= e(app_url('/collector-deals/' . $dealId . '/archive')) ?>" style="display:inline;" onsubmit="return confirm('Arquivar esta captação?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline">Arquivar</button>
            </form>
        <?php endif; ?>
    </div>
</form>
</div></section>
