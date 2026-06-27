<?php
$items = $items ?? [];
$filters = $filters ?? [];
$types = $types ?? [];
$statuses = $statuses ?? [];
$registrationStatuses = $registrationStatuses ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);
$baseQuery = array_filter([
    'q' => $f('q'), 'status' => $f('status'), 'registration_status' => $f('registration_status'),
    'type' => $f('type'), 'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);
$pageUrl = static fn (int $p): string => app_url('/collectors') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$regBadge = static fn (string $st): string => match ($st) {
    'validado' => 'collector-doc-badge collector-doc-badge--aprovado',
    'completo' => 'collector-doc-badge collector-doc-badge--enviado',
    default    => 'collector-doc-badge collector-doc-badge--pendente',
};
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captadores</span>
                <h1 class="h2-section">Captadores credenciados</h1>
                <p class="page-sub"><?= $total ?> captador(es) no cadastro mestre.</p>
            </div>
        </div>

        <form method="get" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Nome, documento, código, e-mail"></div>
                <div><label for="ftype">Tipo</label><select id="ftype" name="type" class="input"><option value="">Todos</option>
                    <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('type') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="freg">Cadastro</label><select id="freg" name="registration_status" class="input"><option value="">Todos</option>
                    <?php foreach ($registrationStatuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('registration_status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="fstatus">Status</label><select id="fstatus" name="status" class="input"><option value="">Todos</option>
                    <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow">Filtrar</button>
                <a href="<?= e(app_url('/collectors')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state"><p>Nenhum captador credenciado encontrado.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Código</th><th>Nome</th><th>Tipo</th><th>Documento</th><th>Comissão</th><th>Cadastro</th><th>Status</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr class="<?= !empty($it['archived_at']) ? 'row-archived' : '' ?>">
                            <td><?= e($it['collector_code'] ?? '—') ?></td>
                            <td><strong><?= e($it['name'] ?? '') ?></strong></td>
                            <td><?= e($types[$it['type'] ?? ''] ?? $it['type'] ?? '') ?></td>
                            <td><?= e($it['document_number'] ?? '—') ?></td>
                            <td><?= $it['commission_percentage'] !== null && $it['commission_percentage'] !== '' ? e(rtrim(rtrim(number_format((float) $it['commission_percentage'], 3, ',', '.'), '0'), ',')) . '%' : '—' ?></td>
                            <td><span class="<?= e($regBadge((string) ($it['registration_status'] ?? ''))) ?>"><?= e($registrationStatuses[$it['registration_status'] ?? ''] ?? '') ?></span></td>
                            <td><?= e($statuses[$it['status'] ?? ''] ?? '') ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/collectors/' . (int) $it['id'])) ?>" class="btn btn-sm btn-outline">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>" class="page-link">Anterior</a><?php endif; ?>
                    <span class="page-info">Página <?= $page ?> de <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>" class="page-link">Próxima</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
