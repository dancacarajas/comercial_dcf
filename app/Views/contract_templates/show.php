<?php
$item = $item ?? [];
$types = $types ?? [];
$statuses = $statuses ?? [];
$placeholders = $placeholders ?? [];
$id = (int) ($item['id'] ?? 0);
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Modelos</span>
        <h1 class="h2-section"><?= e($item['title'] ?? 'Modelo') ?></h1>
        <p class="page-sub"><?= e($types[$item['template_type'] ?? ''] ?? '') ?> · <?= e($statuses[$item['status'] ?? ''] ?? '') ?></p>
    </div>
    <div class="actions-row">
        <?php if (can('contract_templates.edit')): ?><a href="<?= e(app_url('/contract-templates/' . $id . '/edit')) ?>" class="btn btn-sm btn-outline">Editar</a><?php endif; ?>
        <?php if (can('contract_templates.preview')): ?><a href="<?= e(app_url('/contract-templates/' . $id . '/preview')) ?>" class="btn btn-sm btn-yellow">Pré-visualizar</a><?php endif; ?>
        <a href="<?= e(app_url('/contract-templates')) ?>" class="btn btn-sm btn-outline">Voltar</a>
    </div>
</div>
<div class="detail-grid">
    <div class="card">
        <h3 class="h3-card">Informações</h3>
        <dl class="detail-list">
            <dt>Chave</dt><dd><?= e($item['template_key'] ?? '—') ?></dd>
            <dt>Versão</dt><dd><?= (int) ($item['version'] ?? 1) ?></dd>
            <dt>Padrão</dt><dd><?= !empty($item['is_default']) ? 'Sim' : 'Não' ?></dd>
            <dt>Criado em</dt><dd><?= e($item['created_at'] ?? '') ?></dd>
        </dl>
        <?php if (!empty($item['description'])): ?><p><?= e($item['description']) ?></p><?php endif; ?>
    </div>
    <div class="card">
        <h3 class="h3-card">Campos dinâmicos</h3>
        <ul class="mb-0">
            <?php foreach ($placeholders as $key => $label): ?>
                <li><code>{{<?= e($key) ?>}}</code> — <?= e($label) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php if (can('contract_templates.archive')): ?>
<div class="card" style="margin-top:18px;">
    <h3 class="h3-card">Ações</h3>
    <div class="actions-row">
        <?php if (empty($item['archived_at'])): ?>
            <form method="post" action="<?= e(app_url('/contract-templates/' . $id . '/archive')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger" data-confirm="Excluir este modelo? Ele sairá da listagem padrão."><i data-lucide="trash-2"></i> Excluir</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(app_url('/contract-templates/' . $id . '/restore')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div></section>
