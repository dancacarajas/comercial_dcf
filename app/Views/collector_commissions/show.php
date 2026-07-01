<?php
$commission = $commission ?? [];
$snapshot = $snapshot ?? [];
$model = $model ?? null;
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
                    <dt>Calculado em</dt><dd><?= e($commission['calculated_at'] ?? '') ?></dd>
                    <dt>Bloqueio</dt><dd><?= e($commission['block_reason'] ?? '—') ?></dd>
                </dl>
            </article>
        </div>

        <article class="card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="file-json"></i> Snapshot do calculo</h3>
            <pre class="code-block"><?= e(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </article>
    </div>
</section>
