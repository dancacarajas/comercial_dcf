<?php
/**
 * Visualizações analíticas CSS — relatórios gerenciais.
 *
 * Variáveis: $visualizations, $reportKey
 */
$visualizations = $visualizations ?? [];
$reportKey      = $reportKey ?? 'executive';

$financialBars = $visualizations['financial_bars'] ?? [];
$funnel          = $visualizations['funnel'] ?? [];
$donut           = $visualizations['donut'] ?? [];
$progress        = $visualizations['progress'] ?? [];
$barChart        = $visualizations['bar_chart'] ?? [];
$rankings        = $visualizations['rankings'] ?? [];

$hasFinancial = $financialBars !== [];
$hasFunnel    = $funnel !== [];
$hasDonut     = $donut !== [] && array_sum(array_column($donut, 'value')) > 0;
$hasProgress  = $progress !== [];
$hasBars      = $barChart !== [];
$hasRankings  = $rankings !== [];

if (!$hasFinancial && !$hasFunnel && !$hasDonut && !$hasProgress && !$hasBars && !$hasRankings) {
    return;
}
?>

<div class="dcx-analytics-wrap">

    <?php if ($hasFunnel): ?>
        <?php
        $funnelTitle = $reportKey === 'pipeline' ? 'Funil de oportunidades' : 'Funil comercial';
        $funnelSubtitle = $reportKey === 'pipeline'
            ? 'Abertas, fechadas e perdidas no escopo filtrado'
            : 'Etapas da captação e conversão entre fases';
        $funnelLinkLabel = $reportKey === 'pipeline' ? 'Ver pipeline' : 'Ver relatório completo';
        require __DIR__ . '/_funnel.php';
        ?>
    <?php endif; ?>

<div class="dcx-chart-grid">

    <?php if ($hasFinancial): ?>
        <article class="dcx-chart-card dcx-chart-card--wide dcx-chart-card--financial">
            <h3 class="dcx-chart-title"><i data-lucide="wallet" aria-hidden="true"></i> Resumo financeiro</h3>
            <p class="dcx-chart-subtitle">Valores consolidados — barras proporcionais ao maior valor do grupo</p>
            <div class="dcx-bar-chart">
                <?php foreach ($financialBars as $bar): ?>
                    <div class="dcx-bar-row">
                        <span class="dcx-bar-label"><?= e((string) ($bar['label'] ?? '')) ?></span>
                        <div class="dcx-bar-track" role="presentation">
                            <div class="dcx-bar-fill" style="width: <?= (float) ($bar['pct'] ?? 0) ?>%;"></div>
                        </div>
                        <strong class="dcx-bar-value money-value"><?= e((string) ($bar['display'] ?? '')) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endif; ?>

    <?php if ($hasDonut): ?>
        <article class="dcx-chart-card">
            <h3 class="dcx-chart-title"><i data-lucide="pie-chart" aria-hidden="true"></i> Distribuição por status</h3>
            <?php
            $donutTotal = array_sum(array_column($donut, 'value'));
            $donutStops = chart_donut_stops($donut);
            ?>
            <div class="dcx-donut-wrap">
                <div class="dcx-donut" style="background: conic-gradient(<?= e($donutStops) ?>);" role="img"
                     aria-label="Gráfico de distribuição">
                    <div class="dcx-donut__center">
                        <strong><?= (int) $donutTotal ?></strong>
                        <small>total</small>
                    </div>
                </div>
                <ul class="dcx-donut-legend">
                    <?php foreach ($donut as $seg): ?>
                        <?php if ((float) ($seg['value'] ?? 0) <= 0) continue; ?>
                        <li>
                            <span class="dcx-donut-legend__dot" style="background:<?= e((string) ($seg['color'] ?? '#f7c400')) ?>;"></span>
                            <?= e((string) ($seg['label'] ?? '')) ?>
                            <strong><?= (int) ($seg['value'] ?? 0) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>
    <?php elseif ($donut !== []): ?>
        <article class="dcx-chart-card">
            <h3 class="dcx-chart-title">Distribuição por status</h3>
            <div class="dcx-empty-visual">
                <i data-lucide="pie-chart" aria-hidden="true"></i>
                <p>Sem dados suficientes para o gráfico.</p>
            </div>
        </article>
    <?php endif; ?>

    <?php if ($hasProgress): ?>
        <article class="dcx-chart-card">
            <h3 class="dcx-chart-title"><i data-lucide="trending-up" aria-hidden="true"></i> Indicadores de progresso</h3>
            <?php foreach ($progress as $prog): ?>
                <div class="dcx-progress-block">
                    <div class="dcx-progress-block__head">
                        <span><?= e((string) ($prog['label'] ?? '')) ?></span>
                        <strong><?= e((string) ($prog['text'] ?? '0,0%')) ?></strong>
                    </div>
                    <div class="dcx-progress" role="presentation">
                        <div class="dcx-progress__fill" style="width: <?= (float) ($prog['pct'] ?? 0) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </article>
    <?php endif; ?>

    <?php if ($hasBars): ?>
        <article class="dcx-chart-card dcx-chart-card--wide">
            <h3 class="dcx-chart-title"><i data-lucide="bar-chart-2" aria-hidden="true"></i>
                <?= $reportKey === 'pipeline' ? 'Oportunidades por status' : ($reportKey === 'leads' ? 'Leads por status' : 'Distribuição') ?>
            </h3>
            <div class="dcx-bar-chart">
                <?php foreach ($barChart as $bar): ?>
                    <div class="dcx-bar-row">
                        <span class="dcx-bar-label"><?= e((string) ($bar['label'] ?? '')) ?></span>
                        <div class="dcx-bar-track">
                            <div class="dcx-bar-fill" style="width: <?= (float) ($bar['pct'] ?? 0) ?>%;"></div>
                        </div>
                        <strong class="dcx-bar-value"><?= e((string) ($bar['display'] ?? '')) ?></strong>
                    </div>
                    <?php if (!empty($bar['sub'])): ?>
                        <div class="dcx-bar-row-sub money-value"><?= e((string) $bar['sub']) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endif; ?>

    <?php foreach ($rankings as $ranking): ?>
        <article class="dcx-chart-card">
            <h3 class="dcx-chart-title"><i data-lucide="trophy" aria-hidden="true"></i> <?= e((string) ($ranking['title'] ?? 'Ranking')) ?></h3>
            <?php if (($ranking['items'] ?? []) === []): ?>
                <div class="dcx-empty-visual"><p>Sem itens no ranking.</p></div>
            <?php else: ?>
                <div class="dcx-ranking">
                    <?php foreach ($ranking['items'] as $item): ?>
                        <div class="dcx-ranking-row">
                            <span class="dcx-ranking-row__label"><?= e((string) ($item['label'] ?? '')) ?></span>
                            <div class="dcx-ranking-bar">
                                <div class="dcx-ranking-bar__fill" style="width: <?= (float) ($item['pct'] ?? 0) ?>%;"></div>
                            </div>
                            <strong class="dcx-ranking-row__value"><?= e((string) ($item['value'] ?? '')) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

</div>
</div>
