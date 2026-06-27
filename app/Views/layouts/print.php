<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($title ?? 'Impressão') ?> — Dança Carajás Captação</title>

    <?php

    $cssFile = dirname(__DIR__, 3) . '/public/assets/css/dcx-theme.css';

    $contractCss = dirname(__DIR__, 3) . '/public/assets/css/contract-print.css';

    $cssVer  = is_file($cssFile) ? (string) filemtime($cssFile) : '1';

    $contractVer = is_file($contractCss) ? (string) filemtime($contractCss) : '1';

    ?>

    <link rel="stylesheet" href="/assets/css/dcx-theme.css?v=<?= e($cssVer) ?>">

    <link rel="stylesheet" href="/assets/css/contract-print.css?v=<?= e($contractVer) ?>">

</head>

<body class="report-print-body">

    <div class="no-print print-toolbar">
        <a href="javascript:history.back()" class="print-toolbar__back">← Voltar</a>
        <span class="print-toolbar__sep" aria-hidden="true">·</span>
        <button type="button" class="print-toolbar__btn" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <?= $content ?? '' ?>

</body>

</html>

