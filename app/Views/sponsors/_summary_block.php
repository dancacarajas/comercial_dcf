<?php
/**
 * Bloco reutilizável "Patrocinadores / Fechamentos".
 *
 * Variáveis: $blockTitle, $sponsors, $sponsorSummary, $sponsorModel,
 *            $createUrl, $allUrl, $emptyText
 */
$sponsors = $sponsors ?? [];
$sponsorSummary = $sponsorSummary ?? ['total' => 0, 'confirmed' => 0, 'committed' => 0.0, 'confirmed_amount' => 0.0];
$sponsorModel = $sponsorModel ?? null;
$sponsorshipTypes = $sponsorModel ? $sponsorModel->getSponsorshipTypes() : [];
$statuses = $sponsorModel ? $sponsorModel->getStatuses() : [];
$paymentStatuses = $sponsorModel ? $sponsorModel->getPaymentStatuses() : [];
?>
<article class="card sponsor-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="badge-dollar-sign"></i> <?= e($blockTitle) ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($sponsorSummary['total'] ?? 0) ?> cadastrado(s)</span>
                <span class="pill"><?= (int) ($sponsorSummary['confirmed'] ?? 0) ?> confirmado(s)</span>
                <span class="pill money-value"><?= e(money_br($sponsorSummary['committed'] ?? 0, 'R$ 0,00')) ?> comprometido</span>
                <span class="pill money-value"><?= e(money_br($sponsorSummary['confirmed_amount'] ?? 0, 'R$ 0,00')) ?> confirmado</span>
            </p>
        </div>
        <div class="actions-row">
            <?php if (can('sponsors.create')): ?>
                <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Novo fechamento</a>
            <?php endif; ?>
            <a href="<?= e($allUrl) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todos</a>
        </div>
    </div>

    <?php if ($sponsors === []): ?>
        <p class="empty-inline"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="sponsor-linked-list">
                <thead>
                    <tr><th>Patrocinador</th><th>Tipo</th><th>Comprometido</th><th>Status</th><th>Pagamento</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($sponsors as $s): ?>
                        <?php $sid = (int) ($s['id'] ?? 0); $st = (string) ($s['status'] ?? ''); $pay = (string) ($s['payment_status'] ?? ''); ?>
                        <tr>
                            <td><strong><?= e($s['sponsor_display_name'] ?? '') ?></strong></td>
                            <td><span class="sponsor-type"><?= e($sponsorshipTypes[$s['sponsorship_type'] ?? ''] ?? '') ?></span></td>
                            <td class="sponsor-value money-value"><?= isset($s['committed_amount']) && $s['committed_amount'] !== null ? e(money_br($s['committed_amount'])) : '—' ?></td>
                            <td><span class="sponsor-status badge-sponsor-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td><span class="sponsor-payment-status badge-pay-<?= e($pay) ?>"><?= e($paymentStatuses[$pay] ?? $pay) ?></span></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/sponsors/' . $sid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
