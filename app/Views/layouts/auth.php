<?php
/**
 * Layout de autenticação — tela centrada, identidade Dança Carajás.
 *
 * Variaveis esperadas:
 * - $title   (string, opcional)
 * - $content (string) injetado por View::render
 */
$appConfig = require dirname(__DIR__, 3) . '/config/app.php';
$appName   = $appConfig['name'] ?? 'Dança Carajás Captação';
$pageTitle = isset($title) ? ($title . ' — ' . $appName) : $appName;
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
    <link
        href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700;900&family=Nunito+Sans:wght@400;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/dcx-theme.css">
</head>
<body class="auth-body">

    <main class="auth-shell">
        <div class="auth-card-wrap">
            <a class="dcx-brand auth-brand" href="/">
                <span class="brand-icon" aria-hidden="true"><i data-lucide="sparkles"></i></span>
                <?= e($appName) ?>
            </a>

            <?= $content ?? '' ?>

            <p class="auth-foot">&copy; <?= date('Y') ?> Dança Carajás Festival</p>
        </div>
    </main>

    <!-- Lucide local + JS do sistema -->
    <script src="/assets/vendor/lucide/lucide.min.js" defer></script>
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
