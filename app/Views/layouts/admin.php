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

$navPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navPath = rtrim($navPath, '/') ?: '/';

$navIsActive = static function (string $base) use ($navPath): bool {
    $base = rtrim($base, '/') ?: '/';
    if ($base === '/dashboard') {
        return $navPath === '/dashboard' || $navPath === '/';
    }

    return $navPath === $base || str_starts_with($navPath, $base . '/');
};

$navSection = 'dashboard';
foreach ([
    'portal' => ['/portal'],
    'projects' => ['/projects'],
    'companies' => ['/companies', '/contacts', '/leads'],
    'opportunities' => ['/opportunities'],
    'proposals' => ['/proposals'],
    'financials' => ['/financials'],
    'commissions' => ['/commissions'],
    'reports' => ['/reports'],
    'sponsors' => ['/sponsors', '/counterparts', '/contracts', '/sponsor-dossiers', '/documents'],
    'quotas' => ['/quotas'],
    'tasks' => ['/tasks'],
    'collectors' => ['/collector-applications', '/collectors', '/contract-templates', '/signature-requests'],
    'settings' => ['/settings/email', '/users', '/roles', '/permissions'],
] as $section => $bases) {
    foreach ($bases as $base) {
        if ($navIsActive($base)) {
            $navSection = $section;
            break 2;
        }
    }
}

$secondaryNav = [];
$addSecondary = static function (string $section, string $permission, string $href, string $icon, string $label) use (&$secondaryNav): void {
    if (can($permission)) {
        $secondaryNav[$section][] = ['href' => $href, 'icon' => $icon, 'label' => $label];
    }
};

$addSecondary('portal', 'collector_portal.view', '/portal', 'briefcase-business', 'Minha carteira');
$addSecondary('portal', 'collector_portal.view', '/portal/commissions', 'receipt', 'Minhas comissoes');
$addSecondary('portal', 'collector_portal.companies.create', '/portal/prospects/create', 'plus-circle', 'Novo prospect');

$addSecondary('projects', 'quotas.view', '/quotas', 'circle-dollar-sign', 'Cotas');
$addSecondary('projects', 'opportunities.view', '/opportunities', 'target', 'Oportunidades');
$addSecondary('projects', 'proposals.view', '/proposals', 'file-text', 'Propostas');
$addSecondary('projects', 'financials.view', '/financials', 'wallet', 'Financeiro');
$addSecondary('projects', 'commissions.view', '/commissions', 'badge-dollar-sign', 'Comissoes');
$addSecondary('projects', 'reports.view', '/reports', 'chart-no-axes-combined', 'Relatorios');

$addSecondary('companies', 'contacts.view', '/contacts', 'contact', 'Contatos');
$addSecondary('companies', 'leads.view', '/leads', 'inbox', 'Leads');
$addSecondary('companies', 'opportunities.view', '/opportunities', 'target', 'Oportunidades');
$addSecondary('companies', 'sponsors.view', '/sponsors', 'handshake', 'Patrocinadores');

$addSecondary('opportunities', 'companies.view', '/companies', 'building-2', 'Empresas');
$addSecondary('opportunities', 'contacts.view', '/contacts', 'contact', 'Contatos');
$addSecondary('opportunities', 'proposals.view', '/proposals', 'file-text', 'Propostas');
$addSecondary('opportunities', 'quotas.view', '/quotas', 'circle-dollar-sign', 'Cotas');

$addSecondary('proposals', 'opportunities.view', '/opportunities', 'target', 'Oportunidades');
$addSecondary('proposals', 'sponsors.view', '/sponsors', 'handshake', 'Patrocinadores');
$addSecondary('proposals', 'contracts.view', '/contracts', 'file-signature', 'Contratos');
$addSecondary('proposals', 'documents.view', '/documents', 'folder', 'Documentos');

$addSecondary('financials', 'sponsors.view', '/sponsors', 'handshake', 'Patrocinadores');
$addSecondary('financials', 'contracts.view', '/contracts', 'file-signature', 'Contratos');
$addSecondary('financials', 'commissions.view', '/commissions', 'badge-dollar-sign', 'Comissoes');
$addSecondary('financials', 'reports.view', '/reports', 'chart-no-axes-combined', 'Relatorios');

$addSecondary('commissions', 'financials.view', '/financials', 'wallet', 'Financeiro');
$addSecondary('commissions', 'collectors.view', '/collectors', 'contact', 'Captadores');
$addSecondary('commissions', 'reports.view', '/reports', 'chart-no-axes-combined', 'Relatorios');

$addSecondary('sponsors', 'counterparts.view', '/counterparts', 'clipboard-check', 'Contrapartidas');
$addSecondary('sponsors', 'contracts.view', '/contracts', 'file-signature', 'Contratos');
$addSecondary('sponsors', 'dossiers.view', '/sponsor-dossiers', 'folder-check', 'Dossies');
$addSecondary('sponsors', 'documents.view', '/documents', 'folder', 'Documentos');
$addSecondary('sponsors', 'financials.view', '/financials', 'wallet', 'Financeiro');

$addSecondary('collectors', 'collector_applications.view', '/collector-applications', 'user-check', 'Captadores');
$addSecondary('collectors', 'collectors.view', '/collectors', 'contact', 'Cadastro mestre');
$addSecondary('collectors', 'contract_templates.view', '/contract-templates', 'file-signature', 'Modelos');
$addSecondary('collectors', 'signature_requests.view', '/signature-requests', 'pen-line', 'Assinaturas');

$addSecondary('settings', 'email_settings.view', '/settings/email', 'mail', 'E-mail');
$addSecondary('settings', 'users.view', '/users', 'users', 'Usuarios');
$addSecondary('settings', 'roles.view', '/roles', 'shield', 'Perfis');
$addSecondary('settings', 'permissions.view', '/permissions', 'lock-keyhole', 'Permissoes');

$activeSecondaryNav = $secondaryNav[$navSection] ?? [];
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
    <?php
    $cssFile = dirname(__DIR__, 3) . '/public/assets/css/dcx-theme.css';
    $cssVer  = is_file($cssFile) ? (string) filemtime($cssFile) : '1';
    ?>
    <link rel="stylesheet" href="/assets/css/dcx-theme.css?v=<?= e($cssVer) ?>">
</head>
<body>

    <header class="dcx-admin-header dcx-topbar">
        <div class="container dcx-admin-header__grid topbar-inner">
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a class="dcx-admin-brand dcx-brand" href="<?= e(app_url('/dashboard')) ?>">
                    <span class="dcx-admin-brand__mark brand-icon" aria-hidden="true">
                        <i data-lucide="sparkles"></i>
                    </span>
                    <span class="dcx-admin-brand__text">
                        <strong>Dança Carajás</strong>
                        <small>Captação</small>
                    </span>
                </a>

                <button class="dcx-nav-toggle" type="button"
                        data-nav-toggle aria-expanded="false" aria-label="Abrir menu">
                    <i data-lucide="menu"></i>
                </button>

                <div class="dcx-admin-user">
                    <span class="dcx-admin-user__name dcx-user">
                        <i data-lucide="user" aria-hidden="true"></i>
                        <span><?= e($_SESSION['user_name'] ?? 'Usuário') ?></span>
                        <i data-lucide="chevron-down" class="dcx-admin-user__chevron" aria-hidden="true"></i>
                    </span>
                    <form method="post" action="<?= e(app_url('/logout')) ?>" class="dcx-logout-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dcx-admin-logout dcx-logout" aria-label="Sair do sistema">
                            <i data-lucide="log-out" aria-hidden="true"></i>
                            <span>Sair</span>
                        </button>
                    </form>
                </div>

                <div class="dcx-admin-nav-panel" data-nav>
                    <nav class="dcx-admin-primary-nav" aria-label="Navegação principal">
                        <?php $dashActive = $navIsActive('/dashboard'); ?>
                        <a class="dcx-nav-primary__item dcx-nav-link<?= $dashActive ? ' is-active' : '' ?>"
                           href="<?= e(app_url('/dashboard')) ?>"<?= $dashActive ? ' aria-current="page"' : '' ?>>
                            <i data-lucide="layout-dashboard" aria-hidden="true"></i>
                            <span>Painel</span>
                        </a>
                        <?php if (can('collector_portal.view')): ?>
                            <?php $active = $navPath === '/portal' || $navPath === '/portal/captador' || str_starts_with($navPath, '/portal/deals'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/portal')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="briefcase-business" aria-hidden="true"></i>
                                <span>Minha carteira</span>
                            </a>
                            <?php $active = $navIsActive('/portal/commissions'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/portal/commissions')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="receipt" aria-hidden="true"></i>
                                <span>Minhas comissoes</span>
                            </a>
                            <?php if (can('collector_portal.companies.create')): ?>
                                <?php $active = $navIsActive('/portal/prospects'); ?>
                                <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                                   href="<?= e(app_url('/portal/prospects/create')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                    <i data-lucide="plus-circle" aria-hidden="true"></i>
                                    <span>Novo prospect</span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (can('incentive_projects.view')): ?>
                            <?php $active = $navIsActive('/projects'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/projects')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="folder-kanban" aria-hidden="true"></i>
                                <span>Projetos</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('companies.view')): ?>
                            <?php $active = $navIsActive('/companies'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/companies')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="building-2" aria-hidden="true"></i>
                                <span>Empresas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('contacts.view')): ?>
                            <?php $active = $navIsActive('/contacts'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/contacts')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="contact" aria-hidden="true"></i>
                                <span>Contatos</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('opportunities.view')): ?>
                            <?php $active = $navIsActive('/opportunities'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/opportunities')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="target" aria-hidden="true"></i>
                                <span>Oportunidades</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('proposals.view')): ?>
                            <?php $active = $navIsActive('/proposals'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/proposals')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="file-text" aria-hidden="true"></i>
                                <span>Propostas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('financials.view')): ?>
                            <?php $active = $navIsActive('/financials'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/financials')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="wallet" aria-hidden="true"></i>
                                <span>Financeiro</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('commissions.view')): ?>
                            <?php $active = $navIsActive('/commissions'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/commissions')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="badge-dollar-sign" aria-hidden="true"></i>
                                <span>Comissoes</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('reports.view')): ?>
                            <?php $active = $navIsActive('/reports'); ?>
                            <a class="dcx-nav-primary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/reports')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="chart-no-axes-combined" aria-hidden="true"></i>
                                <span>Relatórios</span>
                            </a>
                        <?php endif; ?>
                    </nav>

                    <?php if ($activeSecondaryNav !== []): ?>
                        <div class="dcx-header-divider" aria-hidden="true"></div>

                        <nav class="dcx-admin-secondary-nav" aria-label="Navegacao secundaria">
                            <?php foreach ($activeSecondaryNav as $item): ?>
                                <?php $active = $navIsActive($item['href']); ?>
                                <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                                   href="<?= e(app_url($item['href'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                    <i data-lucide="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                                    <span><?= e($item['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>

                    <?php if (false): ?>
                    <div class="dcx-header-divider" aria-hidden="true"></div>

                    <nav class="dcx-admin-secondary-nav" aria-label="Navegação secundária">
                        <?php if (can('sponsors.view')): ?>
                            <?php $active = $navIsActive('/sponsors'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/sponsors')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="handshake" aria-hidden="true"></i>
                                <span>Patrocinadores</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('counterparts.view')): ?>
                            <?php $active = $navIsActive('/counterparts'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/counterparts')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="clipboard-check" aria-hidden="true"></i>
                                <span>Contrapartidas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('contracts.view')): ?>
                            <?php $active = $navIsActive('/contracts'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/contracts')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="file-signature" aria-hidden="true"></i>
                                <span>Contratos</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('dossiers.view')): ?>
                            <?php $active = $navIsActive('/sponsor-dossiers'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/sponsor-dossiers')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="folder-check" aria-hidden="true"></i>
                                <span>Dossiês</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('documents.view')): ?>
                            <?php $active = $navIsActive('/documents'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/documents')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="folder" aria-hidden="true"></i>
                                <span>Documentos</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('quotas.view')): ?>
                            <?php $active = $navIsActive('/quotas'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/quotas')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="circle-dollar-sign" aria-hidden="true"></i>
                                <span>Cotas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('tasks.view')): ?>
                            <?php $active = $navIsActive('/tasks'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/tasks')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="list-checks" aria-hidden="true"></i>
                                <span>Tarefas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('leads.view')): ?>
                            <?php $active = $navIsActive('/leads'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/leads')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="inbox" aria-hidden="true"></i>
                                <span>Leads</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('collector_applications.view')): ?>
                            <?php $active = $navIsActive('/collector-applications'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/collector-applications')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="user-check" aria-hidden="true"></i>
                                <span>Captadores</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('collectors.view')): ?>
                            <?php $active = $navIsActive('/collectors'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/collectors')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="contact" aria-hidden="true"></i>
                                <span>Cadastro de captadores</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('contract_templates.view')): ?>
                            <?php $active = $navIsActive('/contract-templates'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/contract-templates')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="file-signature" aria-hidden="true"></i>
                                <span>Modelos de contrato</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('signature_requests.view')): ?>
                            <?php $active = $navIsActive('/signature-requests'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/signature-requests')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="pen-line" aria-hidden="true"></i>
                                <span>Assinaturas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('email_settings.view')): ?>
                            <?php $active = $navIsActive('/settings/email'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/settings/email')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="mail" aria-hidden="true"></i>
                                <span>E-mail</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('users.view')): ?>
                            <?php $active = $navIsActive('/users'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/users')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="users" aria-hidden="true"></i>
                                <span>Usuários</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('roles.view')): ?>
                            <?php $active = $navIsActive('/roles'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/roles')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="shield" aria-hidden="true"></i>
                                <span>Perfis</span>
                            </a>
                        <?php endif; ?>
                        <?php if (can('permissions.view')): ?>
                            <?php $active = $navIsActive('/permissions'); ?>
                            <a class="dcx-nav-secondary__item dcx-nav-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= e(app_url('/permissions')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                                <span>Permissões</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </div>
            <?php elseif (str_starts_with($navPath, '/captadores/credenciamento')): ?>
                <a class="dcx-admin-brand dcx-brand" href="<?= e(app_url('/')) ?>">
                    <span class="dcx-admin-brand__mark brand-icon" aria-hidden="true">
                        <i data-lucide="sparkles"></i>
                    </span>
                    <span class="dcx-admin-brand__text">
                        <strong>Dança Carajás</strong>
                        <small>Captação</small>
                    </span>
                </a>

                <button class="dcx-nav-toggle" type="button"
                        data-nav-toggle aria-expanded="false" aria-label="Abrir menu">
                    <i data-lucide="menu"></i>
                </button>

                <div class="dcx-admin-user">
                    <span class="dcx-admin-user__name dcx-user">
                        <i data-lucide="shield-check" aria-hidden="true"></i>
                        <span>Área do captador</span>
                    </span>
                </div>

                <div class="dcx-admin-nav-panel" data-nav>
                    <nav class="dcx-admin-primary-nav" aria-label="Credenciamento">
                        <span class="dcx-nav-primary__item dcx-nav-link is-active" aria-current="page">
                            <i data-lucide="user-check" aria-hidden="true"></i>
                            <span>Credenciamento</span>
                        </span>
                    </nav>
                </div>

                <div class="dcx-header-divider" aria-hidden="true"></div>
            <?php else: ?>
                <a class="dcx-admin-brand dcx-brand" href="<?= e(app_url('/')) ?>">
                    <span class="dcx-admin-brand__mark brand-icon" aria-hidden="true">
                        <i data-lucide="sparkles"></i>
                    </span>
                    <span class="dcx-admin-brand__text">
                        <strong>Dança Carajás</strong>
                        <small>Captação</small>
                    </span>
                </a>

                <button class="dcx-nav-toggle" type="button"
                        data-nav-toggle aria-expanded="false" aria-label="Abrir menu">
                    <i data-lucide="menu"></i>
                </button>

                <nav class="dcx-admin-guest-nav" data-nav aria-label="Navegação">
                    <a class="dcx-nav-link" href="<?= e(app_url('/')) ?>">Início</a>
                    <a class="dcx-nav-link" href="<?= e(app_url('/login')) ?>">Entrar</a>
                </nav>
            <?php endif; ?>
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
