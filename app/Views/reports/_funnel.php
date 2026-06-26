<?php
/**
 * Funil comercial horizontal — dashboard e relatórios.
 *
 * Variáveis: $funnel, $funnelTitle, $funnelSubtitle, $funnelLink, $funnelLinkLabel, $reportKey
 */
$funnel           = $funnel ?? [];
$funnelTitle      = $funnelTitle ?? 'Funil comercial';
$funnelSubtitle   = $funnelSubtitle ?? 'Etapas da captação e conversão entre fases';
$funnelLink       = $funnelLink ?? null;
$funnelLinkLabel  = $funnelLinkLabel ?? 'Ver relatório completo';
$reportKey        = $reportKey ?? 'executive';

if ($funnel === []) {
    return;
}

if ($funnelLink === null && function_exists('can') && can('reports.view')) {
    $funnelLink = $reportKey === 'pipeline'
        ? app_url('/opportunities/pipeline')
        : app_url('/reports/pipeline');
}
?>

<article class="dcx-funnel-card">
    <div class="dcx-funnel-header">
        <div class="dcx-funnel-header__copy">
            <h3 class="dcx-funnel-header__title">
                <span class="dcx-funnel-header__badge" aria-hidden="true">
                    <i data-lucide="git-branch"></i>
                </span>
                <?= e($funnelTitle) ?>
            </h3>
            <p class="dcx-funnel-header__subtitle"><?= e($funnelSubtitle) ?></p>
        </div>
        <?php if ($funnelLink !== null && $funnelLink !== ''): ?>
            <a href="<?= e($funnelLink) ?>" class="dcx-funnel-header__link">
                <?= e($funnelLinkLabel) ?>
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>

    <div class="dcx-funnel-track">
        <div class="dcx-funnel-horizontal" role="list" aria-label="<?= e($funnelTitle) ?>">
            <?php foreach ($funnel as $i => $step): ?>
                <?php if ($i > 0): ?>
                    <?php
                    $conversion = (string) ($step['conversion'] ?? '');
                    $connectorActive = $conversion !== '' && $conversion !== '0,0%';
                    ?>
                    <div class="dcx-funnel-connector<?= $connectorActive ? ' is-active' : ' is-idle' ?>" aria-hidden="true">
                        <span class="dcx-funnel-connector__pct">
                            <?php if ($conversion !== ''): ?>
                                <?= e($conversion) ?>
                            <?php else: ?>
                                <span class="dcx-funnel-connector__muted">—</span>
                            <?php endif; ?>
                        </span>
                        <span class="dcx-funnel-connector__rail">
                            <span class="dcx-funnel-connector__arrow"></span>
                        </span>
                    </div>
                <?php endif; ?>
                <?php
                $count = (int) ($step['count'] ?? 0);
                $stepClass = 'dcx-funnel-step';
                if (!empty($step['active'])) {
                    $stepClass .= ' is-active';
                }
                if (!empty($step['highlight'])) {
                    $stepClass .= ' is-peak';
                }
                if ($count === 0) {
                    $stepClass .= ' is-empty';
                }
                ?>
                <div class="<?= e($stepClass) ?>" role="listitem">
                    <div class="dcx-funnel-step__head">
                        <?php if (!empty($step['icon'])): ?>
                            <span class="dcx-funnel-step__icon" aria-hidden="true">
                                <i data-lucide="<?= e((string) $step['icon']) ?>"></i>
                            </span>
                        <?php endif; ?>
                        <span class="dcx-funnel-step__label"><?= e((string) ($step['label'] ?? '')) ?></span>
                    </div>
                    <strong class="dcx-funnel-step__value"><?= $count ?></strong>
                    <small class="dcx-funnel-step__meta"><?= e((string) ($step['meta'] ?? '')) ?></small>
                    <div class="dcx-funnel-step__strip" role="presentation" aria-hidden="true">
                        <div class="dcx-funnel-step__strip-fill" style="width: <?= (float) ($step['strip_pct'] ?? 0) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</article>
