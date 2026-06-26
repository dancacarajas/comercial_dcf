<?php
/**
 * Bloco reutilizável "Dossiês do Patrocinador".
 *
 * Variáveis: $blockTitle, $dossiers, $dossierSummary, $dossierModel,
 *            $createUrl, $allUrl, $emptyText
 */
$dossiers = $dossiers ?? [];
$dossierSummary = $dossierSummary ?? [
    'total' => 0,
    'approved' => 0,
    'delivered' => 0,
    'pending' => 0,
    'with_pending_counterparts' => 0,
    'with_overdue_counterparts' => 0,
    'with_balance' => 0,
];
$dossierModel = $dossierModel ?? null;
$dossierTypes = $dossierModel ? $dossierModel->getDossierTypes() : [];
$statuses = $dossierModel ? $dossierModel->getStatuses() : [];
$deliveryStatuses = $dossierModel ? $dossierModel->getDeliveryStatuses() : [];
?>
<article class="card dossier-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="folder-open"></i> <?= e($blockTitle ?? 'Dossiês') ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($dossierSummary['total'] ?? 0) ?> dossiê(s)</span>
                <span class="pill"><?= (int) ($dossierSummary['approved'] ?? 0) ?> aprovado(s)</span>
                <span class="pill"><?= (int) ($dossierSummary['delivered'] ?? 0) ?> entregue(s)</span>
                <span class="pill"><?= (int) ($dossierSummary['pending'] ?? 0) ?> pendente(s)</span>
                <span class="pill <?= (int) ($dossierSummary['with_overdue_counterparts'] ?? 0) > 0 ? 'pill-danger' : '' ?>"><?= (int) ($dossierSummary['with_overdue_counterparts'] ?? 0) ?> c/ contrap. atrasada(s)</span>
                <span class="pill"><?= (int) ($dossierSummary['with_balance'] ?? 0) ?> c/ saldo</span>
            </p>
        </div>
        <div class="actions-row dossier-actions">
            <?php if (can('dossiers.create')): ?>
                <a href="<?= e($createUrl ?? app_url('/sponsor-dossiers/create')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Novo dossiê</a>
            <?php endif; ?>
            <a href="<?= e($allUrl ?? app_url('/sponsor-dossiers')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todos</a>
        </div>
    </div>

    <?php if ($dossiers === []): ?>
        <p class="empty-inline"><?= e($emptyText ?? 'Nenhum dossiê encontrado.') ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="dossier-linked-list">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Período</th>
                        <th>Status</th>
                        <th>Entrega</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $d): ?>
                        <?php
                        $did = (int) ($d['id'] ?? 0);
                        $st = (string) ($d['status'] ?? '');
                        $delivery = (string) ($d['delivery_status'] ?? '');
                        $period = '';
                        if (!empty($d['period_start']) || !empty($d['period_end'])) {
                            $period = trim(($d['period_start'] ?? '') . ' — ' . ($d['period_end'] ?? ''), ' —');
                        }
                        ?>
                        <tr>
                            <td><strong><?= e($d['title'] ?? '') ?></strong></td>
                            <td><span class="dossier-type"><?= e($dossierTypes[$d['dossier_type'] ?? ''] ?? '') ?></span></td>
                            <td><?= e($period !== '' ? $period : '—') ?></td>
                            <td><span class="dossier-status badge-dossier-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td><span class="dossier-delivery-status badge-delivery-<?= e($delivery) ?>"><?= e($deliveryStatuses[$delivery] ?? $delivery) ?></span></td>
                            <td style="text-align:right;">
                                <a href="<?= e(app_url('/sponsor-dossiers/' . $did)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
