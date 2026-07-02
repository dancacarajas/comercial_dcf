<?php
/** @var array $collector @var array $assignments @var array $deals @var array $dealStatuses @var array $assignStatuses @var array $assignTypes */
$deals = $deals ?? [];
$assignments = $assignments ?? [];
$dealStatuses = $dealStatuses ?? [];
$assignStatuses = $assignStatuses ?? [];
$assignTypes = $assignTypes ?? [];
$commissionSummary = $commissionSummary ?? [];
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Portal do captador</span>
                <h1 class="h2-section">Minha carteira</h1>
                <p class="page-sub">Empresas e prospects que voce captou. Cadastre novas empresas para ampliar sua carteira.</p>
            </div>
            <a class="btn btn-yellow" href="<?= e(app_url('/portal/prospects/create')) ?>">
                <i data-lucide="plus"></i> Novo prospect
            </a>
        </div>

        <div class="report-summary">
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Comissao gerada</span>
                <strong class="report-metric-value"><?= e(money_br($commissionSummary['generated_total'] ?? 0)) ?></strong>
            </div>
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Comissao paga</span>
                <strong class="report-metric-value"><?= e(money_br($commissionSummary['paid_total'] ?? 0)) ?></strong>
            </div>
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Saldo</span>
                <strong class="report-metric-value"><?= e(money_br($commissionSummary['balance_total'] ?? 0)) ?></strong>
            </div>
            <div class="report-metric">
                <span class="report-metric-label">Extrato</span>
                <a class="btn btn-sm btn-outline" href="<?= e(app_url('/portal/commissions')) ?>">
                    <i data-lucide="receipt"></i> Ver comissoes
                </a>
            </div>
        </div>

        <section class="report-section">
            <h2 class="h3-card">Captacoes</h2>
            <p class="result-count"><?= count($deals) ?> captacao(oes) encontrada(s).</p>
            <?php if ($deals === []): ?>
                <div class="empty-state">
                    <span class="card-icon"><i data-lucide="briefcase-business"></i></span>
                    <h3 class="h3-card">Nenhuma captacao registrada</h3>
                    <p>Comece cadastrando um novo prospect para ampliar sua carteira.</p>
                    <a class="btn btn-yellow" href="<?= e(app_url('/portal/prospects/create')) ?>">
                        <i data-lucide="plus"></i> Novo prospect
                    </a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Status</th>
                                <th>Origem</th>
                                <th>Atualizado</th>
                                <th style="text-align:right;">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deals as $d): ?>
                            <tr>
                                <td><strong><?= e($d['company_name'] ?? '-') ?></strong></td>
                                <td><span class="badge-status"><?= e($dealStatuses[$d['deal_status'] ?? ''] ?? ($d['deal_status'] ?? '-')) ?></span></td>
                                <td><?= e(($d['source'] ?? '') === 'portal_captador' ? 'Portal do captador' : ($d['source'] ?? '-')) ?></td>
                                <td><?= e(!empty($d['updated_at']) ? date('d/m/Y', strtotime((string) $d['updated_at'])) : (!empty($d['created_at']) ? date('d/m/Y', strtotime((string) $d['created_at'])) : '-')) ?></td>
                                <td>
                                    <div class="actions-row" style="justify-content:flex-end;">
                                        <a class="btn btn-sm btn-outline" href="<?= e(app_url('/portal/deals/' . (int) $d['id'])) ?>">
                                            <i data-lucide="eye"></i> Abrir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="report-section">
            <h2 class="h3-card">Minhas atribuicoes</h2>
            <p class="result-count"><?= count($assignments) ?> atribuicao(oes) registrada(s).</p>
            <?php if ($assignments === []): ?>
                <div class="empty-state">
                    <span class="card-icon"><i data-lucide="clipboard-list"></i></span>
                    <h3 class="h3-card">Nenhuma atribuicao registrada</h3>
                    <p>Quando houver atribuicoes vinculadas ao seu cadastro, elas aparecerao aqui.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Exclusiva ate</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td><strong><?= e($a['company_name'] ?? '-') ?></strong></td>
                                <td><?= e($assignTypes[$a['assignment_type'] ?? ''] ?? ($a['assignment_type'] ?? '-')) ?></td>
                                <td><span class="badge-status"><?= e($assignStatuses[$a['status'] ?? ''] ?? ($a['status'] ?? '-')) ?></span></td>
                                <td><?= e(!empty($a['exclusive_until']) ? date('d/m/Y', strtotime((string) $a['exclusive_until'])) : '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
