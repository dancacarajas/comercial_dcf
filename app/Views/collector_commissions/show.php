<?php
$commission = $commission ?? [];
$snapshot = $snapshot ?? [];
$model = $model ?? null;
$canApprove = can('commissions.approve')
    && ($model?->validateApproval($commission) ?? []) === [];
$canBlock = can('commissions.block')
    && in_array((string) ($commission['payment_status'] ?? ''), ['nao_iniciado', 'a_pagar'], true)
    && (string) ($commission['approval_status'] ?? '') !== 'bloqueada';
$canReopen = can('commissions.reopen')
    && (string) ($commission['approval_status'] ?? '') === 'bloqueada'
    && (string) ($commission['payment_status'] ?? '') === 'nao_iniciado';
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Comissao</span>
                <h1 class="h2-section">Comissao #<?= (int) ($commission['id'] ?? 0) ?></h1>
                <p class="page-sub"><?= e($commission['collector_name'] ?? '') ?> - <?= e($commission['project_name'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/commissions')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card">
                <h3 class="h3-card"><i data-lucide="calculator"></i> Calculo</h3>
                <dl class="meta-list">
                    <dt>Valor recebido</dt><dd><?= e(money_br($commission['financial_received_amount'] ?? 0)) ?></dd>
                    <dt>Fator</dt><dd><?= e(number_format(((float) ($commission['commission_factor_snapshot'] ?? 0)) * 100, 6, ',', '.')) ?>%</dd>
                    <dt>Comissao bruta</dt><dd><?= e(money_br($commission['gross_commission_amount'] ?? 0)) ?></dd>
                    <dt>Comissao aplicada</dt><dd><strong><?= e(money_br($commission['capped_commission_amount'] ?? 0)) ?></strong></dd>
                    <dt>Saldo antes</dt><dd><?= e(money_br($commission['available_before'] ?? 0)) ?></dd>
                    <dt>Saldo depois</dt><dd><?= e(money_br($commission['available_after'] ?? 0)) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vinculos</h3>
                <dl class="meta-list">
                    <dt>Projeto</dt><dd><?= e($commission['project_name'] ?? '') ?></dd>
                    <dt>Captador</dt><dd><?= e($commission['collector_name'] ?? '') ?> <small><?= e($commission['collector_code'] ?? '') ?></small></dd>
                    <dt>Empresa</dt><dd><?= e($commission['company_name'] ?? '') ?></dd>
                    <dt>Patrocinador</dt><dd><?= e($commission['sponsor_name'] ?? '') ?></dd>
                    <dt>Financeiro</dt><dd><a href="<?= e(app_url('/financials/' . (int) ($commission['financial_entry_id'] ?? 0))) ?>"><?= e($commission['financial_title'] ?? '') ?></a></dd>
                    <dt>Tipo de atribuicao</dt><dd><?= e($commission['attribution_type'] ?? '') ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="shield-check"></i> Status</h3>
                <dl class="meta-list">
                    <dt>Calculo</dt><dd><?= e($model?->getCalculationStatuses()[$commission['calculation_status'] ?? ''] ?? ($commission['calculation_status'] ?? '')) ?></dd>
                    <dt>Aprovacao</dt><dd><?= e($model?->getApprovalStatuses()[$commission['approval_status'] ?? ''] ?? ($commission['approval_status'] ?? '')) ?></dd>
                    <dt>Pagamento</dt><dd><?= e($model?->getPaymentStatuses()[$commission['payment_status'] ?? ''] ?? ($commission['payment_status'] ?? '')) ?></dd>
                    <dt>Saldo a pagar</dt><dd><?= e(money_br($commission['payment_balance_amount'] ?? 0)) ?></dd>
                    <dt>Calculado em</dt><dd><?= e($commission['calculated_at'] ?? '') ?></dd>
                    <dt>Bloqueio</dt><dd><?= e($commission['block_reason'] ?? '—') ?></dd>
                </dl>
            </article>
        </div>

        <article class="card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="shield-check"></i> Governanca da comissao</h3>
            <div class="detail-grid">
                <dl class="meta-list">
                    <dt>Aprovado por</dt><dd><?= e($commission['approved_by'] ?? '-') ?></dd>
                    <dt>Aprovado em</dt><dd><?= e($commission['approved_at'] ?? '-') ?></dd>
                    <dt>Obs. aprovacao</dt><dd><?= e($commission['approval_notes'] ?? '-') ?></dd>
                </dl>
                <dl class="meta-list">
                    <dt>Bloqueado por</dt><dd><?= e($commission['blocked_by'] ?? '-') ?></dd>
                    <dt>Bloqueado em</dt><dd><?= e($commission['blocked_at'] ?? '-') ?></dd>
                    <dt>Motivo</dt><dd><?= e($commission['block_notes'] ?? ($commission['block_reason'] ?? '-')) ?></dd>
                </dl>
                <dl class="meta-list">
                    <dt>Reaberto por</dt><dd><?= e($commission['reopened_by'] ?? '-') ?></dd>
                    <dt>Reaberto em</dt><dd><?= e($commission['reopened_at'] ?? '-') ?></dd>
                    <dt>Motivo</dt><dd><?= e($commission['reopen_reason'] ?? '-') ?></dd>
                </dl>
            </div>

            <?php if ($canApprove || $canBlock || $canReopen): ?>
                <div class="detail-grid" style="margin-top:16px;">
                    <?php if ($canApprove): ?>
                        <form method="post" action="<?= e(app_url('/commissions/' . (int) ($commission['id'] ?? 0) . '/approve')) ?>" class="stack">
                            <?= csrf_field() ?>
                            <h4 class="h3-card"><i data-lucide="badge-check"></i> Aprovar</h4>
                            <label>Observacao</label>
                            <textarea name="approval_notes" rows="3"></textarea>
                            <button type="submit" class="btn btn-sm btn-yellow">Aprovar comissao</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canBlock): ?>
                        <form method="post" action="<?= e(app_url('/commissions/' . (int) ($commission['id'] ?? 0) . '/block')) ?>" class="stack">
                            <?= csrf_field() ?>
                            <h4 class="h3-card"><i data-lucide="ban"></i> Bloquear</h4>
                            <label>Motivo obrigatorio</label>
                            <textarea name="block_reason" rows="3" required></textarea>
                            <button type="submit" class="btn btn-sm btn-outline">Bloquear comissao</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canReopen): ?>
                        <form method="post" action="<?= e(app_url('/commissions/' . (int) ($commission['id'] ?? 0) . '/reopen')) ?>" class="stack">
                            <?= csrf_field() ?>
                            <h4 class="h3-card"><i data-lucide="rotate-ccw"></i> Reabrir</h4>
                            <label>Motivo obrigatorio</label>
                            <textarea name="reopen_reason" rows="3" required></textarea>
                            <button type="submit" class="btn btn-sm btn-outline">Reabrir para aprovacao</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="file-json"></i> Snapshot do calculo</h3>
            <pre class="code-block"><?= e(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </article>
    </div>
</section>
