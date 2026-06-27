<?php
$items = $items ?? [];
$filters = $filters ?? [];
$types = $types ?? [];
$statuses = $statuses ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Administrativo</span>
        <h1 class="h2-section">Modelos de contrato</h1>
    </div>
    <?php if (can('contract_templates.create')): ?>
        <a href="<?= e(app_url('/contract-templates/create')) ?>" class="btn btn-yellow">Novo modelo</a>
    <?php endif; ?>
</div>

<form method="get" class="filter-bar card" style="margin-bottom:18px;">
    <div class="filter-grid">
        <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar..." class="input">
        <select name="template_type" class="input">
            <option value="">Todos os tipos</option>
            <?php foreach ($types as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= ($filters['template_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="input">
            <option value="">Todos os status</option>
            <?php foreach ($statuses as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Arquivados</label>
        <button type="submit" class="btn btn-sm btn-outline">Filtrar</button>
    </div>
</form>

<div class="card">
    <?php if ($items === []): ?>
        <p class="mb-0">Nenhum modelo encontrado.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Título</th><th>Tipo</th><th>Status</th><th>Versão</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><a href="<?= e(app_url('/contract-templates/' . (int) $item['id'])) ?>"><?= e($item['title'] ?? '') ?></a></td>
                    <td><?= e($types[$item['template_type'] ?? ''] ?? $item['template_type'] ?? '') ?></td>
                    <td><span class="badge badge-ct-<?= e($item['status'] ?? '') ?>"><?= e($statuses[$item['status'] ?? ''] ?? $item['status'] ?? '') ?></span></td>
                    <td><?= (int) ($item['version'] ?? 1) ?></td>
                    <td class="actions-row">
                        <a href="<?= e(app_url('/contract-templates/' . (int) $item['id'])) ?>" class="btn btn-sm btn-outline">Ver</a>
                        <?php if (can('contract_templates.preview')): ?>
                            <a href="<?= e(app_url('/contract-templates/' . (int) $item['id'] . '/preview')) ?>" class="btn btn-sm btn-outline">Preview</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
</div></section>
