<?php
/**
 * Layout administrativo oficial — identidade visual Dança Carajás.
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

    <!-- Fontes oficiais: Quicksand (títulos) + Nunito Sans (textos) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700;900&family=Nunito+Sans:wght@400;600;700;800&display=swap"
        rel="stylesheet">

    <!-- CSS global oficial do sistema -->
    <link rel="stylesheet" href="/assets/css/dcx-theme.css">
</head>
<body>

    <header class="dcx-topbar">
        <div class="container topbar-inner">
            <a class="dcx-brand" href="/">
                <span class="brand-icon" aria-hidden="true"><i data-lucide="sparkles"></i></span>
                <?= e($appName) ?>
            </a>

            <button class="dcx-nav-toggle" type="button"
                    data-nav-toggle aria-expanded="false" aria-label="Abrir menu">
                <i data-lucide="menu"></i>
            </button>

            <nav data-nav aria-label="Navegação principal">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <a href="<?= e(app_url('/dashboard')) ?>"><i data-lucide="layout-dashboard"></i> Painel</a>
                    <?php if (can('companies.view')): ?>
                        <a href="<?= e(app_url('/companies')) ?>"><i data-lucide="building-2"></i> Empresas</a>
                    <?php endif; ?>
                    <?php if (can('contacts.view')): ?>
                        <a href="<?= e(app_url('/contacts')) ?>"><i data-lucide="contact"></i> Contatos</a>
                    <?php endif; ?>
                    <?php if (can('opportunities.view')): ?>
                        <a href="<?= e(app_url('/opportunities')) ?>"><i data-lucide="handshake"></i> Oportunidades</a>
                    <?php endif; ?>
                    <?php if (can('quotas.view')): ?>
                        <a href="<?= e(app_url('/quotas')) ?>"><i data-lucide="ticket"></i> Cotas</a>
                    <?php endif; ?>
                    <?php if (can('tasks.view')): ?>
                        <a href="<?= e(app_url('/tasks')) ?>"><i data-lucide="list-checks"></i> Tarefas</a>
                    <?php endif; ?>
                    <?php if (can('leads.view')): ?>
                        <a href="<?= e(app_url('/leads')) ?>"><i data-lucide="inbox"></i> Leads</a>
                    <?php endif; ?>
                    <?php if (can('proposals.view')): ?>
                        <a href="<?= e(app_url('/proposals')) ?>"><i data-lucide="file-text"></i> Propostas</a>
                    <?php endif; ?>
                    <?php if (can('documents.view')): ?>
                        <a href="<?= e(app_url('/documents')) ?>"><i data-lucide="folder"></i> Documentos</a>
                    <?php endif; ?>
                    <?php if (can('sponsors.view')): ?>
                        <a href="<?= e(app_url('/sponsors')) ?>"><i data-lucide="badge-dollar-sign"></i> Patrocinadores</a>
                    <?php endif; ?>
                    <?php if (can('counterparts.view')): ?>
                        <a href="<?= e(app_url('/counterparts')) ?>"><i data-lucide="list-checks"></i> Contrapartidas</a>
                    <?php endif; ?>
                    <?php if (can('contracts.view')): ?>
                        <a href="<?= e(app_url('/contracts')) ?>"><i data-lucide="file-signature"></i> Contratos</a>
                    <?php endif; ?>
                    <?php if (can('financials.view')): ?>
                        <a href="<?= e(app_url('/financials')) ?>"><i data-lucide="wallet"></i> Financeiro</a>
                    <?php endif; ?>
                    <?php if (can('users.view')): ?>
                        <a href="<?= e(app_url('/users')) ?>"><i data-lucide="users"></i> Usuários</a>
                    <?php endif; ?>
                    <?php if (can('roles.view')): ?>
                        <a href="<?= e(app_url('/roles')) ?>"><i data-lucide="shield"></i> Perfis</a>
                    <?php endif; ?>
                    <?php if (can('permissions.view')): ?>
                        <a href="<?= e(app_url('/permissions')) ?>"><i data-lucide="key-round"></i> Permissões</a>
                    <?php endif; ?>
                    <span class="dcx-user"><i data-lucide="user"></i> <?= e($_SESSION['user_name'] ?? 'Usuário') ?></span>
                    <form method="post" action="<?= e(app_url('/logout')) ?>" class="dcx-logout-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dcx-logout"><i data-lucide="log-out"></i> Sair</button>
                    </form>
                <?php else: ?>
                    <a href="/">Início</a>
                    <a href="/login">Entrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <?php
        $flashSuccess = flash('success');
        $flashError   = flash('error');
        $flashInfo    = flash('info');
        ?>
        <?php if ($flashSuccess || $flashError || $flashInfo): ?>
            <div class="container" style="padding-top:18px;">
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success" data-dismissible>
                        <i data-lucide="check-circle"></i>
                        <span><?= e($flashSuccess) ?></span>
                        <button type="button" class="alert-close" data-dismiss aria-label="Fechar">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="alert alert-error" data-dismissible>
                        <i data-lucide="alert-triangle"></i>
                        <span><?= e($flashError) ?></span>
                        <button type="button" class="alert-close" data-dismiss aria-label="Fechar">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($flashInfo): ?>
                    <div class="alert alert-info" data-dismissible>
                        <i data-lucide="info"></i>
                        <span><?= e($flashInfo) ?></span>
                        <button type="button" class="alert-close" data-dismiss aria-label="Fechar">&times;</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <footer class="dcx-footer">
        <div class="container footer-inner">
            <span>&copy; <?= date('Y') ?> Dança Carajás Festival — Captação de Patrocínio</span>
            <span><?= e($appName) ?> · base técnica</span>
        </div>
    </footer>

    <!--
        Biblioteca de ícones oficial: Lucide (instalação LOCAL, sem dependência externa).
        Fallback de CDN (opcional, apenas se o arquivo local não carregar):
        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    -->
    <script src="/assets/vendor/lucide/lucide.min.js" defer></script>

    <!-- JS leve do sistema (inicializa os ícones Lucide) -->
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
