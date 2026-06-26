<?php
$tables = $tables ?? [];
$rankings = $rankings ?? [];
$visualizations = $visualizations ?? [];

$detailTitles = ['Resumo por módulo', 'Indicadores de patrocínio', 'Resumo financeiro', 'Resumo de contratos', 'Resumo de contrapartidas', 'Resumo de dossiês'];
?>
<?php foreach ($tables as $tableIndex => $table): ?>
    <?php
    $title   = (string) ($table['title'] ?? 'Tabela');
    $headers = $table['headers'] ?? [];
    $rows    = $table['rows'] ?? [];
    $isDetail = in_array($title, $detailTitles, true)
        || ($headers === ['Indicador', 'Valor'] && count($headers) === 2);
    $displayTitle = $isDetail ? 'Detalhamento dos indicadores' : $title;
    ?>
    <details class="report-section report-detail-section"<?= $isDetail ? '' : ' open' ?>>
        <summary class="report-detail-summary">
            <h3 class="h4-section report-detail-title"><?= e($displayTitle) ?></h3>
            <?php if ($isDetail): ?>
                <span class="report-detail-hint">Expandir tabela completa</span>
            <?php endif; ?>
        </summary>
        <?php if ($rows === []): ?>
            <div class="dcx-empty-visual"><p>Sem registros para exibir.</p></div>
        <?php else: ?>
            <div class="table-wrap report-table-wrap">
                <table class="report-table report-table--compact">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $h): ?>
                                <th><?= e((string) $h) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ((array) $row as $cell): ?>
                                    <td><?= e((string) $cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </details>
<?php endforeach; ?>
