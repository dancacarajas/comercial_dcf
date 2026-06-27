<?php
$items = $items ?? [];
$filters = $filters ?? [];
$statuses = $statuses ?? [];
?>
<section class="section"><div class="container">
<div class="page-head">
    <div><span class="kicker kicker-dark">Administrativo</span><h1 class="h2-section">Assinaturas</h1></div>
</div>
<form method="get" class="filter-bar card" style="margin-bottom:18px;">
    <div class="filter-grid">
        <select name="status" class="input">
            <option value="">Todos os status</option>
            <?php foreach ($statuses as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="source_type" class="input">
            <option value="">Todas as origens</option>
            <option value="collector_application" <?= ($filters['source_type'] ?? '') === 'collector_application' ? 'selected' : '' ?>>Credenciamento captador</option>
        </select>
        <button type="submit" class="btn btn-sm btn-outline">Filtrar</button>
    </div>
</form>
<div class="card">
    <?php if ($items === []): ?><p class="mb-0">Nenhum processo encontrado.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Título</th><th>Origem</th><th>Status</th><th>Enviado</th><th>Assinado</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="<?= e(app_url('/signature-requests/' . (int) $item['id'])) ?>"><?= e($item['title'] ?? '') ?></a></td>
                <td><?= e($item['source_type'] ?? '') ?> #<?= (int) ($item['source_id'] ?? 0) ?></td>
                <td><span class="badge badge-sig-<?= e($item['status'] ?? '') ?>"><?= e($statuses[$item['status'] ?? ''] ?? $item['status'] ?? '') ?></span></td>
                <td><?= e(format_datetime_br($item['sent_at'] ?? null)) ?></td>
                <td><?= e(format_datetime_br($item['signed_at'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
</div></section>
