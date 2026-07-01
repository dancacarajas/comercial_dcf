<?php
$commission = $commission ?? [];
$payments = $payments ?? [];
$paymentMethods = $paymentMethods ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$canPayCommission = can('commissions.pay')
    && (string) ($commission['approval_status'] ?? '') === 'aprovada'
    && in_array((string) ($commission['payment_status'] ?? ''), ['a_pagar', 'parcialmente_pago'], true)
    && (float) ($commission['payment_balance_amount'] ?? 0) > 0;
?>
<article class="card" style="margin-top:18px;">
    <h3 class="h3-card"><i data-lucide="receipt"></i> Pagamentos da comissao</h3>

    <?php if ($canPayCommission): ?>
        <form method="post" action="<?= e(app_url('/commissions/' . (int) ($commission['id'] ?? 0) . '/payments')) ?>" class="filters-bar" style="margin-bottom:16px;">
            <?= csrf_field() ?>
            <div>
                <label>Valor</label>
                <input type="text" name="amount" required placeholder="0,00">
            </div>
            <div>
                <label>Data</label>
                <input type="date" name="payment_date" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div>
                <label>Forma</label>
                <select name="payment_method" required>
                    <option value="">Selecione</option>
                    <?php foreach ($paymentMethods as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>ID comprovante</label>
                <input type="number" min="1" name="proof_document_id" required>
            </div>
            <div>
                <label>Observacao</label>
                <input type="text" name="notes">
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="receipt"></i> Registrar</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Valor</th>
                    <th>Forma</th>
                    <th>Comprovante</th>
                    <th>Status</th>
                    <th>Motivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>#<?= (int) $payment['id'] ?></td>
                        <td><?= e($payment['payment_date'] ?? '') ?></td>
                        <td><strong><?= e(money_br($payment['amount'] ?? 0)) ?></strong></td>
                        <td><?= e($paymentMethods[$payment['payment_method'] ?? ''] ?? ($payment['payment_method'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($payment['proof_document_id'])): ?>
                                <a href="<?= e(app_url('/documents/' . (int) $payment['proof_document_id'])) ?>"><?= e($payment['proof_document_title'] ?? ('#' . (int) $payment['proof_document_id'])) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= e($paymentStatuses[$payment['status'] ?? ''] ?? ($payment['status'] ?? '')) ?></td>
                        <td><?= e($payment['cancel_reason'] ?? '-') ?></td>
                        <td>
                            <?php if (can('commissions.cancel_payment') && (string) ($payment['status'] ?? '') === 'confirmado'): ?>
                                <form method="post" action="<?= e(app_url('/commissions/payments/' . (int) $payment['id'] . '/cancel')) ?>" class="stack">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="collector_commission_id" value="<?= (int) ($commission['id'] ?? 0) ?>">
                                    <select name="cancel_status" required>
                                        <option value="cancelado">Cancelar</option>
                                        <option value="estornado">Estornar</option>
                                    </select>
                                    <input type="text" name="cancel_reason" required placeholder="Motivo">
                                    <button type="submit" class="btn btn-sm btn-outline">Aplicar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($payments === []): ?>
                    <tr><td colspan="8" class="text-muted">Nenhum pagamento registrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
