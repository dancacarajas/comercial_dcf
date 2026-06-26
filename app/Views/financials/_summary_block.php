<?php
/**
 * Bloco reutilizável "Financeiro".
 *
 * Variáveis: $blockTitle, $financials, $financialSummary, $financialModel,
 *            $upcomingDue, $createUrl, $allUrl, $emptyText
 */
$financials = $financials ?? [];
$financialSummary = $financialSummary ?? [
    'total' => 0, 'planned_total' => 0.0, 'received_total' => 0.0, 'remaining_total' => 0.0,
    'received' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0, 'reconciled' => 0,
];
$financialModel = $financialModel ?? null;
$upcomingDue = $upcomingDue ?? [];
$entryTypes = $financialModel ? $financialModel->getEntryTypes() : [];
$statuses = $financialModel ? $financialModel->getStatuses() : [];
$fiscalStatuses = $financialModel ? $financialModel->getFiscalDocumentStatuses() : [];
?>
<article class="card financial-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="wallet"></i> <?= e($blockTitle ?? 'Financeiro') ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($financialSummary['total'] ?? 0) ?> lançamento(s)</span>
                <span class="pill financial-value money-value"><?= e(money_br($financialSummary['planned_total'] ?? 0, 'R$ 0,00')) ?> previsto</span>
                <span class="pill financial-received money-value"><?= e(money_br($financialSummary['received_total'] ?? 0, 'R$ 0,00')) ?> recebido</span>
                <span class="pill financial-balance money-value"><?= e(money_br($financialSummary['remaining_total'] ?? 0, 'R$ 0,00')) ?> saldo</span>
                <span class="pill"><?= (int) ($financialSummary['received'] ?? 0) ?> recebido(s)</span>
                <span class="pill"><?= (int) ($financialSummary['partial'] ?? 0) ?> parcial(is)</span>
                <span class="pill <?= (int) ($financialSummary['overdue'] ?? 0) > 0 ? 'pill-danger' : '' ?>"><?= (int) ($financialSummary['overdue'] ?? 0) ?> atrasado(s)</span>
                <span class="pill"><?= (int) ($financialSummary['pending'] ?? 0) ?> pendente(s)</span>
            </p>
        </div>
        <div class="actions-row financial-actions">
            <?php if (can('financials.create')): ?>
                <a href="<?= e($createUrl ?? app_url('/financials/create')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Novo lançamento</a>
            <?php endif; ?>
            <a href="<?= e($allUrl ?? app_url('/financials')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todos</a>
        </div>
    </div>

    <?php if ($upcomingDue !== []): ?>
        <h4 class="h4-card" style="margin:0 0 10px;">Próximos vencimentos</h4>
        <div class="table-wrap" style="margin-bottom:16px;">
            <table class="financial-linked-list">
                <thead>
                    <tr><th>Título</th><th>Vencimento</th><th>Previsto</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingDue as $row): ?>
                        <?php
                        $fid = (int) ($row['id'] ?? 0);
                        $st = (string) ($row['status'] ?? '');
                        $overdue = $financialModel && $financialModel->isOverdue($row);
                        ?>
                        <tr class="<?= $overdue ? 'financial-overdue' : '' ?>">
                            <td><strong><?= e($row['title'] ?? '') ?></strong></td>
                            <td class="<?= $overdue ? 'overdue' : '' ?>"><?= e($row['due_date'] ?? '—') ?></td>
                            <td class="financial-value money-value"><?= isset($row['planned_amount']) ? e(money_br($row['planned_amount'])) : '—' ?></td>
                            <td><span class="financial-status badge-fin-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/financials/' . $fid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($financials === []): ?>
        <p class="empty-inline"><?= e($emptyText ?? 'Nenhum lançamento financeiro encontrado.') ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="financial-linked-list">
                <thead>
                    <tr><th>Título</th><th>Tipo</th><th>Vencimento</th><th>Previsto</th><th>Recebido</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($financials as $fe): ?>
                        <?php
                        $fid = (int) ($fe['id'] ?? 0);
                        $st = (string) ($fe['status'] ?? '');
                        $overdue = $financialModel && $financialModel->isOverdue($fe);
                        ?>
                        <tr class="<?= $overdue ? 'financial-overdue' : '' ?>">
                            <td><strong><?= e($fe['title'] ?? '') ?></strong></td>
                            <td><span class="financial-type"><?= e($entryTypes[$fe['entry_type'] ?? ''] ?? '') ?></span></td>
                            <td class="<?= $overdue ? 'overdue' : '' ?>"><?= e($fe['due_date'] ?? '—') ?></td>
                            <td class="financial-value money-value"><?= isset($fe['planned_amount']) ? e(money_br($fe['planned_amount'])) : '—' ?></td>
                            <td class="financial-received money-value"><?= isset($fe['received_amount']) ? e(money_br($fe['received_amount'])) : '—' ?></td>
                            <td><span class="financial-status badge-fin-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/financials/' . $fid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
