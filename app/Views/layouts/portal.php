<?php
/**
 * Layout do Portal do Captador (Etapa 18C — Fase 2B).
 *
 * Layout próprio, sem o menu administrativo interno. Variáveis esperadas:
 * - $title     (string)
 * - $content   (string, injetado por View)
 * - $collector (array, opcional)
 */
$appConfig = require dirname(__DIR__, 3) . '/config/app.php';
$appName   = $appConfig['name'] ?? 'Dança Carajás Captação';
$pageTitle = isset($title) ? ($title . ' — ' . $appName) : $appName;
$collectorName = isset($collector['name']) ? (string) $collector['name'] : (string) ($_SESSION['user_name'] ?? '');
$collectorCode = isset($collector['collector_code']) ? (string) $collector['collector_code'] : '';
$flashSuccess = flash('success');
$flashError   = flash('error');
$flashInfo    = flash('info');
$current = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isCarteira = !str_contains($current,'/prospects') && !str_contains($current,'/deals');
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
    <style>
        .pt-shell{min-height:100vh;display:flex;flex-direction:column;background:#f5f6fb;font-family:'Nunito Sans',sans-serif;color:#1f2330}
        .pt-topbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1.4rem;background:#2a1a4a;color:#fff;flex-wrap:wrap}
        .pt-brand{display:flex;align-items:center;gap:.55rem;font-family:'Quicksand',sans-serif;font-weight:900;font-size:1.05rem;color:#fff;text-decoration:none}
        .pt-nav{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
        .pt-nav a{color:#e9e3ff;text-decoration:none;padding:.45rem .8rem;border-radius:.55rem;font-weight:700;font-size:.92rem}
        .pt-nav a:hover,.pt-nav a[aria-current]{background:rgba(255,255,255,.16);color:#fff}
        .pt-user{display:flex;align-items:center;gap:.7rem;font-size:.88rem}
        .pt-user .pt-code{opacity:.8;font-size:.8rem}
        .pt-logout{background:#ffffff22;border:1px solid #ffffff44;color:#fff;padding:.4rem .7rem;border-radius:.55rem;cursor:pointer;font-weight:700;font-size:.85rem}
        .pt-logout:hover{background:#ffffff33}
        .pt-main{flex:1;max-width:1040px;width:100%;margin:0 auto;padding:1.6rem 1.2rem 3rem}
        .pt-flash{padding:.8rem 1rem;border-radius:.6rem;margin-bottom:1rem;font-weight:600}
        .pt-flash.ok{background:#e6f7ec;color:#1b6b3a;border:1px solid #b6e6c6}
        .pt-flash.err{background:#fdecec;color:#a32626;border:1px solid #f3c2c2}
        .pt-flash.info{background:#eaf1fd;color:#1c4ea3;border:1px solid #c2d4f3}
        .pt-card{background:#fff;border:1px solid #e7e8f0;border-radius:.9rem;padding:1.2rem 1.3rem;margin-bottom:1.2rem;box-shadow:0 1px 2px rgba(20,20,50,.04)}
        .pt-card h2{font-family:'Quicksand',sans-serif;font-size:1.15rem;margin:0 0 .9rem;color:#2a1a4a}
        .pt-card h3{font-family:'Quicksand',sans-serif;font-size:1rem;margin:0 0 .7rem;color:#2a1a4a}
        .pt-table{width:100%;border-collapse:collapse;font-size:.9rem}
        .pt-table th,.pt-table td{text-align:left;padding:.6rem .55rem;border-bottom:1px solid #eef0f6}
        .pt-table th{color:#6b6f80;font-size:.78rem;text-transform:uppercase;letter-spacing:.03em}
        .pt-badge{display:inline-block;padding:.18rem .55rem;border-radius:999px;font-size:.74rem;font-weight:800;background:#efeaff;color:#5a3fb0}
        .pt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.9rem}
        .pt-field{display:flex;flex-direction:column;gap:.3rem;margin-bottom:.9rem}
        .pt-field label{font-weight:700;font-size:.85rem;color:#3a3d4d}
        .pt-field input,.pt-field select,.pt-field textarea{padding:.55rem .65rem;border:1px solid #d4d6e2;border-radius:.55rem;font:inherit;background:#fff}
        .pt-field .err{color:#a32626;font-size:.8rem}
        .pt-btn{display:inline-flex;align-items:center;gap:.4rem;background:#5a3fb0;color:#fff;border:none;padding:.6rem 1.1rem;border-radius:.6rem;font-weight:800;cursor:pointer;text-decoration:none;font-size:.92rem}
        .pt-btn:hover{background:#4a3296}
        .pt-btn.secondary{background:#fff;color:#5a3fb0;border:1px solid #ccbff0}
        .pt-muted{color:#6b6f80;font-size:.88rem}
        .pt-empty{padding:1.4rem;text-align:center;color:#8a8ea0}
        .pt-actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.4rem}
    </style>
</head>
<body>
<div class="pt-shell">
    <header class="pt-topbar">
        <a class="pt-brand" href="<?= e(app_url('/portal')) ?>">
            <i data-lucide="compass"></i> <?= e($appName) ?>
        </a>
        <nav class="pt-nav">
            <a href="<?= e(app_url('/portal')) ?>"<?= $isCarteira ? ' aria-current="page"' : '' ?>>Minha carteira</a>
            <a href="<?= e(app_url('/portal/prospects/create')) ?>"<?= str_contains($current,'/prospects') ? ' aria-current="page"' : '' ?>>Novo prospect</a>
        </nav>
        <div class="pt-user">
            <span>
                <?= e($collectorName) ?>
                <?php if ($collectorCode !== ''): ?><br><span class="pt-code"><?= e($collectorCode) ?></span><?php endif; ?>
            </span>
            <form method="post" action="<?= e(app_url('/logout')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="pt-logout"><i data-lucide="log-out"></i> Sair</button>
            </form>
        </div>
    </header>

    <main class="pt-main">
        <?php if ($flashSuccess): ?><div class="pt-flash ok"><?= e($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="pt-flash err"><?= e($flashError) ?></div><?php endif; ?>
        <?php if ($flashInfo): ?><div class="pt-flash info"><?= e($flashInfo) ?></div><?php endif; ?>
        <?= $content ?? '' ?>
    </main>

    <footer style="text-align:center;padding:1rem;color:#9aa;font-size:.8rem">&copy; <?= date('Y') ?> Dança Carajás Festival — Portal do Captador</footer>
</div>
<script src="/assets/vendor/lucide/lucide.min.js" defer></script>
<script>window.addEventListener('DOMContentLoaded',function(){if(window.lucide){window.lucide.createIcons();}});</script>
</body>
</html>
