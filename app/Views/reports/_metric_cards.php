<?php
$metrics = $metrics ?? [];
?>
<?php if ($metrics === []): ?>
    <div class="empty-state" style="margin:20px 0;padding:28px;text-align:center;background:#fafaf8;border:1px dashed #dededb;border-radius:12px;">
        <p class="mb-0"><i data-lucide="bar-chart-2"></i> Nenhum indicador disponível para os filtros selecionados.</p>
    </div>
<?php else: ?>
    <div class="summary-grid report-summary">
        <?php foreach ($metrics as $metric): ?>
            <?php
            $label = (string) ($metric['label'] ?? '');
            $value = $metric['value'] ?? '—';
            $type  = (string) ($metric['type'] ?? 'number');
            $numClass = 'metric-num';
            if ($type === 'money') {
                $numClass .= ' money-value';
            }
            ?>
            <article class="metric-card report-metric report-card">
                <span class="<?= e($numClass) ?>"><?= e((string) $value) ?></span>
                <span class="metric-label report-metric-label"><?= e($label) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
