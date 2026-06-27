<?php
$items = $items ?? [];
$filters = $filters ?? [];
$statuses = $statuses ?? [];
$documentStatuses = $documentStatuses ?? [];
$reviewStatuses = $reviewStatuses ?? [];
$accessStatuses = $accessStatuses ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);
$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);
$baseQuery = array_filter([
    'q' => $f('q'), 'status' => $f('status'), 'document_status' => $f('document_status'),
    'review_status' => $f('review_status'), 'access_status' => $f('access_status'),
    'source' => $f('source'), 'assigned_user_id' => (int) ($filters['assigned_user_id'] ?? 0) ?: '',
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);
$pageUrl = static fn (int $p): string => app_url('/collector-applications') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captadores</span>
                <h1 class="h2-section">Credenciamento de captadores</h1>
                <p class="page-sub"><?= $total ?> candidatura(s) encontrada(s).</p>
            </div>
            <?php if (can('collector_applications.create')): ?>
                <a href="<?= e(app_url('/collector-applications/create')) ?>" class="btn btn-yellow"><i data-lucide="user-plus"></i> Nova candidatura</a>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn" style="margin-bottom:14px;">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos. <a href="<?= e(app_url('/collector-applications')) ?>" class="link-strong">Limpar filtros</a></p>
            </div>
        <?php endif; ?>

        <form method="get" class="filter-box filter-box--financial">
            <div class="filter-grid filter-grid--financial">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Nome, e-mail, CPF/CNPJ, cidade, segmento"></div>
                <div><label for="fstatus">Status</label><select id="fstatus" name="status" class="input"><option value="">Todos</option>
                    <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="fdoc">Documentos</label><select id="fdoc" name="document_status" class="input"><option value="">Todos</option>
                    <?php foreach ($documentStatuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('document_status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="freview">Análise</label><select id="freview" name="review_status" class="input"><option value="">Todos</option>
                    <?php foreach ($reviewStatuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('review_status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="faccess">Acesso</label><select id="faccess" name="access_status" class="input"><option value="">Todos</option>
                    <?php foreach ($accessStatuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('access_status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="fsource">Origem</label><select id="fsource" name="source" class="input"><option value="">Todas</option>
                    <option value="site" <?= $f('source') === 'site' ? 'selected' : '' ?>>Site</option>
                    <option value="manual" <?= $f('source') === 'manual' ? 'selected' : '' ?>>Manual</option>
                </select></div>
                <div><label for="fresp">Responsável</label><select id="fresp" name="assigned_user_id" class="input"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) ($filters['assigned_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div class="filter-flags">
                <span class="filter-flags__title">Situação rápida</span>
                <div class="filter-checks">
                    <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Arquivadas</label>
                </div>
            </div>
            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                <a href="<?= e(app_url('/collector-applications')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state"><p>Nenhuma candidatura encontrada.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Nº</th><th>Nome</th><th>E-mail</th><th>Cidade/UF</th><th>Status</th><th>Documentos</th><th>Acesso</th><th>Origem</th><th>Responsável</th><th>Recebida</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr class="<?= !empty($it['archived_at']) ? 'row-archived' : '' ?>">
                            <td><?= e($it['application_number'] ?? '—') ?></td>
                            <td><strong><?= e($it['name'] ?? '') ?></strong></td>
                            <td><?= e($it['email'] ?? '') ?></td>
                            <td><?= e($it['city_state'] ?? '—') ?></td>
                            <td><?= e($statuses[$it['status'] ?? ''] ?? $it['status'] ?? '') ?></td>
                            <td><?= e($documentStatuses[$it['document_status'] ?? ''] ?? '') ?></td>
                            <td><?= e($accessStatuses[$it['access_status'] ?? ''] ?? '') ?></td>
                            <td><?= e($it['source'] ?? '') ?></td>
                            <td><?= e($it['assigned_name'] ?? '—') ?></td>
                            <td><?= e(substr((string) ($it['created_at'] ?? ''), 0, 10)) ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/collector-applications/' . (int) $it['id'])) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
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
