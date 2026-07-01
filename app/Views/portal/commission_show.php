<?php
$commission = $commission ?? [];
$payments = $payments ?? [];
$paymentMethods = $paymentMethods ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$calculationStatuses = $calculationStatuses ?? [];
$approvalStatuses = $approvalStatuses ?? [];
$commissionPaymentStatuses = $commissionPaymentStatuses ?? [];
$snapshot = $snapshot ?? [];
?>
<div class="pt-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <div>
            <h2 style="margin-bottom:.2rem">Comissão #<?= (int) ($commission['id'] ?? 0) ?></h2>
            <p class="pt-muted" style="margin:0"><?= e($commission['project_name'] ?? 'Projeto') ?> · <?= e($commission['financial_title'] ?? 'Financeiro') ?></p>
        </div>
        <a class="pt-btn secondary" href="<?= e(app_url('/portal/commissions')) ?>"><i data-lucide="arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="pt-card">
    <h3>Resumo</h3>
    <div class="pt-grid">
        <div><span class="pt-muted">Comissão bruta</span><br><strong><?= e(money_br($commission['gross_commission_amount'] ?? 0)) ?></strong></div>
        <div><span class="pt-muted">Comissão aplicada</span><br><strong><?= e(money_br($commission['capped_commission_amount'] ?? 0)) ?></strong></div>
        <div><span class="pt-muted">Pago</span><br><strong><?= e(money_br($commission['payment_total_amount'] ?? 0)) ?></strong></div>
        <div><span class="pt-muted">Saldo</span><br><strong><?= e(money_br($commission['payment_balance_amount'] ?? 0)) ?></strong></div>
    </div>
</div>

<div class="pt-card">
    <h3>Status e origem</h3>
    <table class="pt-table">
        <tbody>
            <tr><th>Projeto</th><td><?= e($commission['project_name'] ?? '—') ?></td></tr>
            <tr><th>Empresa</th><td><?= e($commission['company_name'] ?? '—') ?></td></tr>
            <tr><th>Patrocinador</th><td><?= e($commission['sponsor_name'] ?? '—') ?></td></tr>
            <tr><th>Financeiro</th><td><?= e($commission['financial_title'] ?? ('#' . (int) ($commission['financial_entry_id'] ?? 0))) ?></td></tr>
            <tr><th>Valor recebido base</th><td><?= e(money_br($commission['financial_received_amount'] ?? 0)) ?></td></tr>
            <tr><th>Tipo de captação</th><td><?= e($commission['attribution_type'] ?? '—') ?></td></tr>
            <tr><th>Cálculo</th><td><span class="pt-badge"><?= e($calculationStatuses[$commission['calculation_status'] ?? ''] ?? ($commission['calculation_status'] ?? '—')) ?></span></td></tr>
            <tr><th>Aprovação</th><td><span class="pt-badge"><?= e($approvalStatuses[$commission['approval_status'] ?? ''] ?? ($commission['approval_status'] ?? '—')) ?></span></td></tr>
            <tr><th>Pagamento</th><td><span class="pt-badge"><?= e($commissionPaymentStatuses[$commission['payment_status'] ?? ''] ?? ($commission['payment_status'] ?? '—')) ?></span></td></tr>
            <?php if (!empty($commission['calculated_at'])): ?><tr><th>Calculada em</th><td><?= e(date('d/m/Y H:i', strtotime((string) $commission['calculated_at']))) ?></td></tr><?php endif; ?>
            <?php if (!empty($commission['approved_at'])): ?><tr><th>Aprovada em</th><td><?= e(date('d/m/Y H:i', strtotime((string) $commission['approved_at']))) ?></td></tr><?php endif; ?>
            <?php if (!empty($commission['paid_at'])): ?><tr><th>Quitada em</th><td><?= e(date('d/m/Y H:i', strtotime((string) $commission['paid_at']))) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($snapshot !== []): ?>
<div class="pt-card">
    <h3>Memória do cálculo</h3>
    <div class="pt-grid">
        <?php if (isset($snapshot['commission_factor'])): ?><div><span class="pt-muted">Fator</span><br><strong><?= e(number_format((float) $snapshot['commission_factor'] * 100, 6, ',', '.')) ?>%</strong></div><?php endif; ?>
        <?php if (isset($snapshot['available_before'])): ?><div><span class="pt-muted">Saldo antes</span><br><strong><?= e(money_br($snapshot['available_before'])) ?></strong></div><?php endif; ?>
        <?php if (isset($snapshot['available_after'])): ?><div><span class="pt-muted">Saldo depois</span><br><strong><?= e(money_br($snapshot['available_after'])) ?></strong></div><?php endif; ?>
        <?php if (isset($snapshot['share_percent'])): ?><div><span class="pt-muted">Rateio</span><br><strong><?= e(number_format((float) $snapshot['share_percent'], 2, ',', '.')) ?>%</strong></div><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="pt-card">
    <h3>Pagamentos realizados</h3>
    <?php if ($payments === []): ?>
        <div class="pt-empty">Nenhum pagamento registrado para esta comissão.</div>
    <?php else: ?>
        <table class="pt-table">
            <thead><tr><th>Data</th><th>Valor</th><th>Forma</th><th>Status</th><th>Comprovante</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e(!empty($payment['payment_date']) ? date('d/m/Y', strtotime((string) $payment['payment_date'])) : '—') ?></td>
                    <td><strong><?= e(money_br($payment['amount'] ?? 0)) ?></strong></td>
                    <td><?= e($paymentMethods[$payment['payment_method'] ?? ''] ?? ($payment['payment_method'] ?? '—')) ?></td>
                    <td><span class="pt-badge"><?= e($paymentStatuses[$payment['status'] ?? ''] ?? ($payment['status'] ?? '—')) ?></span></td>
                    <td><?= e($payment['proof_document_title'] ?? (!empty($payment['proof_document_id']) ? ('Comprovante #' . (int) $payment['proof_document_id']) : '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
