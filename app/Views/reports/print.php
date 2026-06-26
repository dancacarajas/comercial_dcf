<?php
$reportTitle = $reportTitle ?? ($title ?? 'Relatório');
$description = $description ?? '';
$filters = $filters ?? [];
$filterErrors = $filterErrors ?? [];
$data = $data ?? [];
$generatedAt = $generatedAt ?? date('d/m/Y H:i');
$metrics = $data['metrics'] ?? [];
$tables = $data['tables'] ?? [];
$alerts = $data['alerts'] ?? [];
$rankings = $data['rankings'] ?? [];
?>
<section class="section report-print">
    <div class="container">
        <header class="report-print-header">
            <span class="kicker kicker-dark">Dança Carajás · Indicadores gerenciais (interno)</span>
            <h1 class="h2-section"><?= e($reportTitle) ?></h1>
            <p class="page-sub"><?= e($description) ?> · Gerado em <?= e($generatedAt) ?></p>
        </header>

        <?php if ($filterErrors !== []): ?>
            <div class="report-alert"><p>Avisos de filtro: <?= e(implode(' ', $filterErrors)) ?></p></div>
        <?php endif; ?>

        <?php if ($filters !== []): ?>
            <div class="report-section">
                <h3>Filtros aplicados</h3>
                <ul>
                    <?php foreach ($filters as $k => $v): ?>
                        <?php if ($v !== '' && $v !== 0 && $v !== null): ?>
                            <li><?= e((string) $k) ?>: <?= e(is_bool($v) ? ($v ? 'sim' : 'não') : (string) $v) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/_metric_cards.php'; ?>
        <?php require __DIR__ . '/_tables.php'; ?>

        <p class="report-print-footer" style="margin-top:32px;font-size:12px;color:#666;">
            Documento interno autenticado — impressão via navegador. Não é PDF automático nem link público.
        </p>
    </div>
</section>
<script>window.addEventListener('load', function(){ window.print(); });</script>
