<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Impressão') ?> — Dança Carajás Captação</title>
    <?php
    $cssFile = dirname(__DIR__, 3) . '/public/assets/css/dcx-theme.css';
    $cssVer  = is_file($cssFile) ? (string) filemtime($cssFile) : '1';
    ?>
    <link rel="stylesheet" href="/assets/css/dcx-theme.css?v=<?= e($cssVer) ?>">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body class="report-print-body">
    <div class="no-print" style="padding:12px;text-align:center;background:#050505;color:#fff;">
        <a href="javascript:history.back()" style="color:#f7c400;">← Voltar</a>
        ·
        <button type="button" onclick="window.print()" style="background:#f7c400;border:0;padding:6px 14px;border-radius:999px;cursor:pointer;">Imprimir</button>
    </div>
    <?= $content ?? '' ?>
</body>
</html>
