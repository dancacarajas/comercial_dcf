<?php
$metrics = $metrics ?? [];
?>
<?php if ($metrics === []): ?>
    <div class="report-empty">
        <p><i data-lucide="bar-chart-2"></i> Nenhum indicador disponível para os filtros selecionados.</p>
    </div>
<?php else: ?>
    <div class="report-summary">
        <?php foreach ($metrics as $metric): ?>
            <?php
            $label = (string) ($metric['label'] ?? '');
            $value = $metric['value'] ?? '—';
            $type  = (string) ($metric['type'] ?? 'number');
            $class = 'report-metric report-card';
            if ($type === 'money') {
                $class .= ' report-metric-money';
            }
            ?>
            <div class="<?= e($class) ?>">
                <span class="report-metric-label"><?= e($label) ?></span>
                <strong class="report-metric-value"><?= e((string) $value) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
