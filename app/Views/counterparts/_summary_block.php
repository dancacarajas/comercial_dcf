<?php
/**
 * Bloco reutilizável "Contrapartidas".
 *
 * Variáveis: $blockTitle, $counterparts, $counterpartSummary, $counterpartModel,
 *            $createUrl, $allUrl, $emptyText
 */
$counterparts = $counterparts ?? [];
$counterpartSummary = $counterpartSummary ?? ['total' => 0, 'delivered' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0];
$counterpartModel = $counterpartModel ?? null;
$categories = $counterpartModel ? $counterpartModel->getCategories() : [];
$statuses = $counterpartModel ? $counterpartModel->getStatuses() : [];
$priorities = $counterpartModel ? $counterpartModel->getPriorities() : [];
?>
<article class="card counterpart-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="list-checks"></i> <?= e($blockTitle) ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($counterpartSummary['total'] ?? 0) ?> cadastrada(s)</span>
                <span class="pill"><?= (int) ($counterpartSummary['delivered'] ?? 0) ?> entregue(s)</span>
                <span class="pill"><?= (int) ($counterpartSummary['partial'] ?? 0) ?> parcial(is)</span>
                <span class="pill"><?= (int) ($counterpartSummary['pending'] ?? 0) ?> pendente(s)</span>
                <span class="pill <?= (int) ($counterpartSummary['overdue'] ?? 0) > 0 ? 'pill-danger' : '' ?>"><?= (int) ($counterpartSummary['overdue'] ?? 0) ?> atrasada(s)</span>
            </p>
        </div>
        <div class="actions-row counterpart-actions">
            <?php if (can('counterparts.create')): ?>
                <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Nova contrapartida</a>
            <?php endif; ?>
            <a href="<?= e($allUrl) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todas</a>
        </div>
    </div>

    <?php if ($counterparts === []): ?>
        <p class="empty-inline"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="counterpart-linked-list">
                <thead>
                    <tr><th>Título</th><th>Categoria</th><th>Prioridade</th><th>Status</th><th>Prazo</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($counterparts as $cp): ?>
                        <?php
                        $cid = (int) ($cp['id'] ?? 0);
                        $st = (string) ($cp['status'] ?? '');
                        $pri = (string) ($cp['priority'] ?? '');
                        $overdue = $counterpartModel && $counterpartModel->isOverdue($cp);
                        ?>
                        <tr>
                            <td><strong><?= e($cp['title'] ?? '') ?></strong></td>
                            <td><span class="counterpart-category"><?= e($categories[$cp['category'] ?? ''] ?? '') ?></span></td>
                            <td><span class="counterpart-priority priority-<?= e($pri) ?>"><?= e($priorities[$pri] ?? $pri) ?></span></td>
                            <td><span class="counterpart-status badge-cp-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td class="<?= $overdue ? 'overdue' : '' ?>"><?= e($cp['due_date'] ?? '—') ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/counterparts/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
