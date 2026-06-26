<?php
$tables = $tables ?? [];
$rankings = $rankings ?? [];
?>
<?php foreach ($tables as $table): ?>
    <?php
    $title   = (string) ($table['title'] ?? 'Tabela');
    $headers = $table['headers'] ?? [];
    $rows    = $table['rows'] ?? [];
    ?>
    <div class="report-section">
        <h3 class="h4-section"><?= e($title) ?></h3>
        <?php if ($rows === []): ?>
            <div class="report-empty"><p class="mb-0">Sem registros para exibir.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-dcx report-table">
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
    </div>
<?php endforeach; ?>

<?php foreach ($rankings as $ranking): ?>
    <?php
    $title = (string) ($ranking['title'] ?? 'Ranking');
    $items = $ranking['items'] ?? [];
    ?>
    <div class="report-section report-ranking">
        <h3 class="h4-section"><?= e($title) ?></h3>
        <?php if ($items === []): ?>
            <div class="report-empty"><p class="mb-0">Sem itens no ranking.</p></div>
        <?php else: ?>
            <ol class="report-ranking-list">
                <?php foreach ($items as $item): ?>
                    <li>
                        <span><?= e((string) ($item['label'] ?? '')) ?></span>
                        <strong><?= e((string) ($item['value'] ?? '')) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
