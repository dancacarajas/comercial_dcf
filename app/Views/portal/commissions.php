<?php
$items = $items ?? [];
$filters = $filters ?? [];
$summary = $summary ?? [];
$projects = $projects ?? [];
$calculationStatuses = $calculationStatuses ?? [];
$approvalStatuses = $approvalStatuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$statusGroups = $statusGroups ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>
<style>
.pt-commission-metrics{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:.75rem;margin-bottom:1rem}
.pt-commission-metric{border:1px solid var(--dcx-line);border-radius:8px;padding:.8rem;background:#fff}
.pt-commission-metric span{display:block;color:var(--dcx-muted);font-size:.74rem;text-transform:uppercase;font-weight:800}
.pt-commission-metric strong{display:block;margin-top:.2rem;font-size:1.05rem;color:var(--dcx-black)}
.pt-commission-filters{display:grid;grid-template-columns:minmax(180px,2fr) minmax(160px,1.4fr) minmax(150px,1fr) minmax(130px,1fr) 120px 120px auto;gap:.55rem;align-items:end}
.pt-commission-filters .pt-field{margin:0}
.pt-commission-filters input,.pt-commission-filters select{min-height:36px}
@media(max-width:980px){.pt-commission-metrics{grid-template-columns:1fr 1fr}.pt-commission-filters{grid-template-columns:1fr 1fr}.pt-commission-filters .pt-actions{grid-column:span 2}}
</style>

<div class="pt-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <div>
            <h2 style="margin-bottom:.2rem">Minhas comissões</h2>
            <p class="pt-muted" style="margin:0">Extrato somente leitura das suas comissões por projeto, financeiro e patrocinador.</p>
        </div>
        <a class="pt-btn secondary" href="<?= e(app_url('/portal')) ?>"><i data-lucide="arrow-left"></i> Carteira</a>
    </div>
</div>

<div class="pt-commission-metrics">
    <div class="pt-commission-metric"><span>Gerada</span><strong><?= e(money_br($summary['generated_total'] ?? 0)) ?></strong></div>
    <div class="pt-commission-metric"><span>Aprovada</span><strong><?= e(money_br($summary['approved_total'] ?? 0)) ?></strong></div>
    <div class="pt-commission-metric"><span>Pago</span><strong><?= e(money_br($summary['paid_total'] ?? 0)) ?></strong></div>
    <div class="pt-commission-metric"><span>Saldo</span><strong><?= e(money_br($summary['balance_total'] ?? 0)) ?></strong></div>
</div>

<div class="pt-card">
    <form method="get" class="pt-commission-filters">
        <div class="pt-field">
            <label>Busca</label>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Projeto, financeiro, patrocinador">
        </div>
        <div class="pt-field">
            <label>Projeto</label>
            <select name="incentive_project_id">
                <option value="">Todos</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) ($project['group_id'] ?? 0) ?>" <?= (int) ($filters['incentive_project_id'] ?? 0) === (int) ($project['group_id'] ?? 0) ? 'selected' : '' ?>><?= e($project['group_label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pt-field">
            <label>Status</label>
            <select name="status_group">
                <option value="">Todos</option>
                <?php foreach ($statusGroups as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= ($filters['status_group'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pt-field">
            <label>Pagamento</label>
            <select name="payment_status">
                <option value="">Todos</option>
                <?php foreach ($paymentStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= ($filters['payment_status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pt-field">
            <label>De</label>
            <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>
        <div class="pt-field">
            <label>Ate</label>
            <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
        </div>
        <div class="pt-actions">
            <button type="submit" class="pt-btn"><i data-lucide="search"></i> Filtrar</button>
            <a class="pt-btn secondary" href="<?= e(app_url('/portal/commissions')) ?>">Limpar</a>
        </div>
    </form>
</div>

<div class="pt-card">
    <h3>Extrato (<?= $total ?>)</h3>
    <?php if ($items === []): ?>
        <div class="pt-empty">Nenhuma comissão encontrada para os filtros informados.</div>
    <?php else: ?>
        <table class="pt-table">
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Financeiro / patrocinador</th>
                    <th>Valores</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['project_name'] ?? '—') ?><br><span class="pt-muted"><?= e($item['attribution_type'] ?? '') ?></span></td>
                    <td>
                        <?= e($item['financial_title'] ?? ('#' . (int) ($item['financial_entry_id'] ?? 0))) ?><br>
                        <span class="pt-muted"><?= e($item['sponsor_name'] ?? ($item['company_name'] ?? '—')) ?></span>
                    </td>
                    <td>
                        <strong><?= e(money_br($item['capped_commission_amount'] ?? 0)) ?></strong><br>
                        <span class="pt-muted">pago <?= e(money_br($item['payment_total_amount'] ?? 0)) ?> · saldo <?= e(money_br($item['payment_balance_amount'] ?? 0)) ?></span>
                    </td>
                    <td>
                        <span class="pt-badge"><?= e($approvalStatuses[$item['approval_status'] ?? ''] ?? ($item['approval_status'] ?? '')) ?></span><br>
                        <span class="pt-muted"><?= e($paymentStatuses[$item['payment_status'] ?? ''] ?? ($item['payment_status'] ?? '')) ?></span>
                    </td>
                    <td><a class="pt-btn secondary" href="<?= e(app_url('/portal/commissions/' . (int) $item['id'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="pt-muted">Página <?= $page ?> de <?= $pages ?>.</p>
    <?php endif; ?>
</div>
