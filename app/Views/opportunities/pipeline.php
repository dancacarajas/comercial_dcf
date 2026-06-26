<?php
/**
 * Visão de pipeline (funil) — colunas por status do funil.
 *
 * Variáveis: $summary, $columns, $statusLabels, $filters
 */
$summary      = $summary ?? [];
$columns      = $columns ?? [];
$statusLabels = $statusLabels ?? [];

$probClass = static function (int $p): string {
    if ($p >= 90) {
        return 'top';
    }
    if ($p >= 60) {
        return 'high';
    }
    if ($p >= 25) {
        return 'mid';
    }
    return 'low';
};

$statusIcons = [
    'prospect_identificado'          => 'search',
    'contato_localizado'             => 'contact',
    'primeiro_contato_enviado'       => 'send',
    'respondeu'                      => 'message-circle',
    'one_page_enviado'               => 'file-text',
    'reuniao_solicitada'             => 'calendar-plus',
    'reuniao_agendada'               => 'calendar-check',
    'reuniao_realizada'              => 'users',
    'deck_enviado'                   => 'presentation',
    'em_analise_interna'             => 'search-check',
    'aguardando_dados_oficiais'      => 'clock',
    'proposta_personalizada_enviada' => 'file-signature',
    'negociacao'                     => 'handshake',
    'reserva_de_cota'                => 'ticket',
    'fechado'                        => 'check-circle',
    'perdido'                        => 'x-circle',
    'retomar_depois'                 => 'rotate-ccw',
];

$slugOrder  = array_keys($statusLabels);
$counts     = [];
$totalValue = 0.0;
$totalOps   = 0;
foreach ($slugOrder as $slug) {
    $c = (int) ($summary[$slug]['count'] ?? 0);
    $v = (float) ($summary[$slug]['total'] ?? 0);
    $counts[$slug] = $c;
    $totalOps += $c;
    $totalValue += $v;
}
$maxCount = $counts !== [] ? max($counts) : 0;
$baseCount = max(1, (int) ($counts[$slugOrder[0] ?? ''] ?? 0));
$peakSlug = null;
if ($maxCount > 0) {
    foreach ($slugOrder as $slug) {
        if (($counts[$slug] ?? 0) === $maxCount) {
            $peakSlug = $slug;
            break;
        }
    }
}

$now = time();
?>

<section class="section pipeline-page">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Pipeline</h1>
                <p class="page-sub">Etapas do funil distribuídas em grade — leitura clara de cada fase.</p>
            </div>
            <div class="actions-row">
                <a href="<?= e(app_url('/opportunities')) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Lista</a>
                <?php if (can('reports.view')): ?>
                    <a href="<?= e(app_url('/reports/pipeline')) ?>" class="btn btn-sm btn-ghost"><i data-lucide="chart-no-axes-combined"></i> Relatório</a>
                <?php endif; ?>
                <?php if (can('opportunities.create')): ?>
                    <a href="<?= e(app_url('/opportunities/create')) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Nova oportunidade</a>
                <?php endif; ?>
            </div>
        </div>

        <article class="dcx-funnel-card pipeline-board-shell">
            <div class="dcx-funnel-header pipeline-board-shell__head">
                <div class="dcx-funnel-header__copy">
                    <h2 class="dcx-funnel-header__title">
                        <span class="dcx-funnel-header__badge" aria-hidden="true">
                            <i data-lucide="kanban-square"></i>
                        </span>
                        Funil por status
                    </h2>
                    <p class="dcx-funnel-header__subtitle">
                        <?= (int) $totalOps ?> oportunidade(s) ·
                        <span class="money-value"><?= e(money_br($totalValue, 'R$ 0,00')) ?></span> em valor estimado
                    </p>
                </div>
            </div>

            <div class="dcx-funnel-track pipeline-board-track">
                <div class="dcx-pipeline-board" role="list" aria-label="Colunas do pipeline">
                    <?php
                    $prevCount = null;
                    foreach ($statusLabels as $slug => $label):
                        $count = (int) ($summary[$slug]['count'] ?? 0);
                        $total = (float) ($summary[$slug]['total'] ?? 0);
                        $cards = $columns[$slug] ?? [];
                        $conversion = $prevCount !== null ? chart_conversion_pct((float) $count, (float) $prevCount) : null;
                        $stripPct   = chart_pct_width((float) $count, (float) $baseCount);
                        $icon       = $statusIcons[$slug] ?? 'circle-dot';

                        $stepClass = 'dcx-funnel-step dcx-pipeline-step-head';
                        if ($count > 0) {
                            $stepClass .= ' is-active';
                        }
                        if ($slug === $peakSlug) {
                            $stepClass .= ' is-peak';
                        }
                        if ($count === 0) {
                            $stepClass .= ' is-empty';
                        }
                        $convActive = $conversion !== null && $conversion !== '0,0%';
                        ?>
                        <div class="dcx-pipeline-column" role="listitem">
                            <header class="<?= e($stepClass) ?>">
                                <?php if ($conversion !== null): ?>
                                    <span class="dcx-pipeline-step-conv<?= $convActive ? ' is-active' : '' ?>">
                                        Conversão <?= e($conversion) ?>
                                    </span>
                                <?php endif; ?>
                                <div class="dcx-funnel-step__head">
                                    <span class="dcx-funnel-step__icon" aria-hidden="true">
                                        <i data-lucide="<?= e($icon) ?>"></i>
                                    </span>
                                    <span class="dcx-funnel-step__label"><?= e($label) ?></span>
                                </div>
                                <strong class="dcx-funnel-step__value"><?= $count ?></strong>
                                <small class="dcx-funnel-step__meta money-value"><?= e(money_br($total, 'R$ 0,00')) ?></small>
                                <div class="dcx-funnel-step__strip" role="presentation" aria-hidden="true">
                                    <div class="dcx-funnel-step__strip-fill" style="width: <?= $stripPct ?>%;"></div>
                                </div>
                            </header>

                            <div class="dcx-pipeline-column__body">
                                <?php if ($cards === []): ?>
                                    <div class="dcx-pipeline-empty" title="Sem oportunidades">
                                        <i data-lucide="inbox" aria-hidden="true"></i>
                                        <span>Sem oportunidades</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($cards as $c): ?>
                                        <?php
                                        $prob = (int) ($c['probability'] ?? 0);
                                        $next = (string) ($c['next_action_at'] ?? '');
                                        $overdue = $next !== '' && strtotime($next) !== false && strtotime($next) < $now;
                                        ?>
                                        <a class="pipeline-card pipeline-card--slim" href="<?= e(app_url('/opportunities/' . (int) $c['id'])) ?>"
                                           title="<?= e($c['title'] . ' · ' . ($c['company_name'] ?? '')) ?>">
                                            <strong class="pipeline-card-title"><?= e($c['title']) ?></strong>
                                            <span class="pipeline-card-row">
                                                <span class="money-value"><?= e(money_br($c['estimated_value'] ?? null)) ?></span>
                                                <span class="badge-probability badge-probability-<?= $probClass($prob) ?>"><?= $prob ?>%</span>
                                            </span>
                                            <span class="pipeline-card-foot pipeline-card-foot--slim">
                                                <?php if ($next !== ''): ?>
                                                    <span class="<?= $overdue ? 'overdue' : '' ?>"><i data-lucide="calendar-clock"></i> <?= e($next) ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if ($count > count($cards)): ?>
                                        <a class="pipeline-more" href="<?= e(app_url('/opportunities?status=' . $slug)) ?>" title="Ver todas">
                                            +<?= $count - count($cards) ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        $prevCount = $count;
                    endforeach;
                    ?>
                </div>
            </div>
        </article>
    </div>
</section>
