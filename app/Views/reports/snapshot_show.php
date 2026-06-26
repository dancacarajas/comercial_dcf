<?php
$item = $item ?? [];
$reportKeys = $reportKeys ?? [];
$statuses = $statuses ?? [];
$filters = $filters ?? [];
$metrics = $metrics ?? [];
$summary = $summary ?? [];
$id = (int) ($item['id'] ?? 0);

$tables = $summary['tables'] ?? [];
$alerts = $summary['alerts'] ?? [];
$rankings = $summary['rankings'] ?? [];

$canArchive = can('reports.archive');
$isArchived = !empty($item['archived_at']);
?>
<section class="section">
    <div class="container report-snapshot">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Snapshot · <?= e($reportKeys[$item['report_key'] ?? ''] ?? '') ?></span>
                <h1 class="h2-section"><?= e($item['title'] ?? '') ?></h1>
                <?php if (!empty($item['description'])): ?>
                    <p class="page-sub"><?= e($item['description']) ?></p>
                <?php endif; ?>
            </div>
            <a href="<?= e(app_url('/reports/snapshots')) ?>" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="report-card card-pad" style="margin-bottom:16px;">
            <p><strong>Status:</strong> <?= e($statuses[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></p>
            <p><strong>Gerado em:</strong> <?= e($item['generated_at'] ?? $item['created_at'] ?? '—') ?> por <?= e($item['generated_by_name'] ?? '—') ?></p>
            <p><strong>Período:</strong> <?= e(trim(($item['period_start'] ?? '') . ' — ' . ($item['period_end'] ?? ''), ' —')) ?: '—' ?></p>
            <?php if (!empty($item['notes'])): ?>
                <p><strong>Observações:</strong> <?= e($item['notes']) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($filters !== []): ?>
            <div class="report-section">
                <h3 class="h4-section">Filtros salvos</h3>
                <pre class="report-card card-pad" style="white-space:pre-wrap;font-size:13px;"><?= e(json_encode($filters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        <?php endif; ?>

        <?php
        $metricsList = is_array($metrics) && array_is_list($metrics) ? $metrics : [];
        if ($metricsList !== []) {
            $metrics = $metricsList;
            require __DIR__ . '/_metric_cards.php';
        }
        ?>

        <?php
        $tables = is_array($tables) ? $tables : [];
        $rankings = is_array($rankings) ? $rankings : [];
        require __DIR__ . '/_tables.php';
        ?>

        <?php if ($canArchive): ?>
            <div class="actions-row" style="margin-top:24px;">
                <?php if ($isArchived): ?>
                    <form method="post" action="<?= e(app_url('/reports/snapshots/' . $id . '/restore')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar snapshot</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/reports/snapshots/' . $id . '/archive')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost"><i data-lucide="archive"></i> Arquivar snapshot</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
