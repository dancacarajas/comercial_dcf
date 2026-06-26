<?php
/**
 * Visão de pipeline (funil) — colunas por status do funil.
 *
 * Variáveis: $summary (status => [count,total]), $columns (status => itens[]),
 * $statusLabels, $filters
 */
$summary      = $summary ?? [];
$columns      = $columns ?? [];
$statusLabels = $statusLabels ?? [];

$probClass = static function (int $p): string {
    if ($p >= 90) { return 'top'; }
    if ($p >= 60) { return 'high'; }
    if ($p >= 25) { return 'mid'; }
    return 'low';
};
$now = time();
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Pipeline</h1>
                <p class="page-sub">Funil de captação por status. Limite de 10 cards por coluna.</p>
            </div>
            <div class="actions-row">
                <a href="<?= e(app_url('/opportunities')) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Lista</a>
                <?php if (can('opportunities.create')): ?>
                    <a href="<?= e(app_url('/opportunities/create')) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Nova oportunidade</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="pipeline-board">
            <?php foreach ($statusLabels as $slug => $label): ?>
                <?php
                $count = (int) ($summary[$slug]['count'] ?? 0);
                $total = (float) ($summary[$slug]['total'] ?? 0);
                $cards = $columns[$slug] ?? [];
                ?>
                <div class="pipeline-column">
                    <header class="pipeline-column-head">
                        <h3 class="pipeline-column-title"><?= e($label) ?></h3>
                        <div class="pipeline-column-meta">
                            <span class="pill"><?= $count ?></span>
                            <span class="money-value"><?= e(money_br($total, 'R$ 0,00')) ?></span>
                        </div>
                    </header>

                    <?php if ($cards === []): ?>
                        <p class="pipeline-empty">Sem oportunidades.</p>
                    <?php else: ?>
                        <?php foreach ($cards as $c): ?>
                            <?php
                            $prob = (int) ($c['probability'] ?? 0);
                            $next = (string) ($c['next_action_at'] ?? '');
                            $overdue = $next !== '' && strtotime($next) !== false && strtotime($next) < $now;
                            ?>
                            <a class="pipeline-card" href="<?= e(app_url('/opportunities/' . (int) $c['id'])) ?>">
                                <strong class="pipeline-card-title"><?= e($c['title']) ?></strong>
                                <span class="pipeline-card-company"><i data-lucide="building-2"></i> <?= e($c['company_name'] ?? '—') ?></span>
                                <?php if (!empty($c['contact_name'])): ?>
                                    <span class="pipeline-card-contact"><i data-lucide="contact"></i> <?= e($c['contact_name']) ?></span>
                                <?php endif; ?>
                                <?php
                                $cQuota = trim((string) ($c['quota_name'] ?? ''));
                                if ($cQuota === '') { $cQuota = trim((string) ($c['quota_interest'] ?? '')); }
                                ?>
                                <?php if ($cQuota !== ''): ?>
                                    <span class="pipeline-card-quota"><i data-lucide="ticket"></i> <?= e($cQuota) ?></span>
                                <?php endif; ?>
                                <span class="pipeline-card-row">
                                    <span class="money-value"><?= e(money_br($c['estimated_value'] ?? null)) ?></span>
                                    <span class="badge-probability badge-probability-<?= $probClass($prob) ?>"><?= $prob ?>%</span>
                                </span>
                                <span class="pipeline-card-foot">
                                    <?php if ($next !== ''): ?>
                                        <span class="<?= $overdue ? 'overdue' : '' ?>"><i data-lucide="calendar-clock"></i> <?= e($next) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($c['owner_name'])): ?>
                                        <span class="pipeline-card-owner"><i data-lucide="user"></i> <?= e($c['owner_name']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($count > count($cards)): ?>
                            <a class="pipeline-more" href="<?= e(app_url('/opportunities?status=' . $slug)) ?>">Ver todas (<?= $count ?>)</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
