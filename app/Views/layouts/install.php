<?php
$appName = 'Dança Carajás Captação';
$pageTitle = isset($title) ? ($title . ' — ' . $appName) : $appName;
$step = (int) ($step ?? 1);
$steps = [
    1 => 'Requisitos',
    2 => 'Banco',
    3 => 'Sistema',
    4 => 'Admin',
    5 => 'Revisão',
    6 => 'Concluído',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700;900&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/dcx-theme.css">
</head>
<body class="auth-body install-body">
<main class="auth-shell install-shell">
    <div class="auth-card-wrap install-card-wrap">
        <a class="dcx-brand auth-brand" href="/install">
            <span class="brand-icon" aria-hidden="true"><i data-lucide="settings"></i></span>
            Instalação — <?= e($appName) ?>
        </a>

        <?php if ($step <= 5): ?>
        <nav class="install-steps" aria-label="Etapas da instalação">
            <?php foreach ($steps as $n => $label): if ($n > 5) break; ?>
                <span class="install-step<?= $n === $step ? ' is-active' : ($n < $step ? ' is-done' : '') ?>">
                    <span class="install-step-num"><?= $n ?></span>
                    <span class="install-step-label"><?= e($label) ?></span>
                </span>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
            <div class="notice notice-warning"><i data-lucide="alert-triangle"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <?= $content ?? '' ?>

        <p class="auth-foot">&copy; <?= date('Y') ?> Dança Carajás Festival — Kit de instalação Etapa 9B</p>
    </div>
</main>
<script src="/assets/vendor/lucide/lucide.min.js" defer></script>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
