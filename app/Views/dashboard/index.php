<?php
/**
 * Painel administrativo — central gerencial DCX.
 */
$user                = $user ?? null;
$userName            = $user['name'] ?? 'Usuário';
$userEmail           = $user['email'] ?? '';
$roleNames           = $roleNames ?? [];
$primaryRole         = $primaryRole ?? ($roleNames[0] ?? 'Usuário');
$appEnvLabel         = $appEnvLabel ?? 'Produção';
$dashboardAlerts     = $dashboardAlerts ?? [];
$criticalAlertsCount = (int) ($criticalAlertsCount ?? count($dashboardAlerts));
$showAdminSection    = !empty($showAdminSection);

$companiesCount     = $companiesCount ?? null;
$contactsCount      = $contactsCount ?? null;
$opportunitiesOpen  = $opportunitiesOpen ?? null;
$opportunitiesValue = $opportunitiesValue ?? null;
$proposalsOpen      = $proposalsOpen ?? null;
$proposalsOpenValue = $proposalsOpenValue ?? null;
$proposalsClosed    = $proposalsClosed ?? null;
$sponsorsTotal      = $sponsorsTotal ?? null;
$sponsorsConfirmed  = $sponsorsConfirmed ?? null;
$sponsorsCommitted  = $sponsorsCommitted ?? null;
$sponsorsConfirmedAmount = $sponsorsConfirmedAmount ?? null;

$financialsPlanned    = $financialsPlanned ?? null;
$financialsReceived   = $financialsReceived ?? null;
$financialsRemaining  = $financialsRemaining ?? null;
$financialsPartial    = $financialsPartial ?? null;
$financialsOverdue    = $financialsOverdue ?? null;
$financialsReconciled = $financialsReconciled ?? null;

$counterpartsTotal     = $counterpartsTotal ?? null;
$counterpartsPending   = $counterpartsPending ?? null;
$counterpartsDelivered = $counterpartsDelivered ?? null;
$counterpartsPartial   = $counterpartsPartial ?? null;
$counterpartsOverdue   = $counterpartsOverdue ?? null;

$contractsTotal      = $contractsTotal ?? null;
$contractsSigned     = $contractsSigned ?? null;
$contractsAwaiting   = $contractsAwaiting ?? null;
$contractsFormalized = $contractsFormalized ?? null;

$documentsTotal    = $documentsTotal ?? null;
$documentsActive   = $documentsActive ?? null;
$documentsExpiring = $documentsExpiring ?? null;
$documentsExpired  = $documentsExpired ?? null;

$dossiersTotal     = $dossiersTotal ?? null;
$dossiersDelivered = $dossiersDelivered ?? null;
$dossiersPending   = $dossiersPending ?? null;

$collectorsReceived = $collectorsReceived ?? null;
$collectorsPendingReview = $collectorsPendingReview ?? null;
$collectorsAwaitingDocs = $collectorsAwaitingDocs ?? null;
$collectorsApproved = $collectorsApproved ?? null;
$collectorsAccessReleased = $collectorsAccessReleased ?? null;

$visualizations = $visualizations ?? [];
$reportKey      = $reportKey ?? 'executive';
$reportsAvailable = !empty($reportsAvailable);

$hasCommercial = $companiesCount !== null
    || $contactsCount !== null
    || $opportunitiesOpen !== null
    || $proposalsOpen !== null
    || $sponsorsTotal !== null;

$hasFinancial = $financialsPlanned !== null;
$hasDeliveries = $counterpartsTotal !== null || $dossiersTotal !== null;
$hasFormalization = $contractsTotal !== null || $documentsTotal !== null || $dossiersTotal !== null;

$showKpiNegotiation = can('opportunities.view') || can('proposals.view');
$showKpiCommitted   = can('sponsors.view');
$showKpiFormalized  = can('contracts.view');
$showKpiFinancial   = can('financials.view');

$dateLabel = (new DateTime('now', new DateTimeZone('America/Belem')))->format('d/m/Y');
?>

<div class="dashboard-main">

    <section class="dashboard-hero section-dark">
        <div class="container dashboard-hero__content">
            <h1 class="h2-section dashboard-hero__title">Painel Administrativo</h1>
            <p class="lead dashboard-hero__lead">
                Visão geral da captação, contratos, financeiro, contrapartidas e dossiês do Dança Carajás.
            </p>
            <div class="dashboard-hero__meta">
                <span class="badge-dcx badge-ok">
                    <i data-lucide="user-check" aria-hidden="true"></i>
                    <?= e($userName) ?>
                </span>
                <span class="badge-dcx badge-muted">
                    <i data-lucide="shield" aria-hidden="true"></i>
                    <?= e($primaryRole) ?>
                </span>
                <span class="badge-dcx badge-env">
                    <i data-lucide="server" aria-hidden="true"></i>
                    <?= e($appEnvLabel) ?>
                </span>
                <span class="badge-dcx badge-muted">
                    <i data-lucide="calendar" aria-hidden="true"></i>
                    Atualizado em <?= e($dateLabel) ?>
                </span>
            </div>
        </div>
    </section>

    <section class="section section-soft dashboard-body">
        <div class="container stack-md">

            <?php if ($showKpiNegotiation || $showKpiCommitted || $showKpiFormalized || $showKpiFinancial || $criticalAlertsCount > 0): ?>
                <div class="dashboard-kpi-strip">
                    <div class="dashboard-grid dashboard-grid--kpis">
                        <?php if ($showKpiNegotiation): ?>
                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="target" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Valor em negociação</h2>
                                    <p class="dashboard-kpi-card__value money-value">
                                        <?= e(money_br(
                                            can('opportunities.view') ? $opportunitiesValue : $proposalsOpenValue,
                                            'R$ 0,00'
                                        )) ?>
                                    </p>
                                    <p class="dashboard-kpi-card__meta">Pipeline comercial ativo</p>
                                    <?php if (can('opportunities.view')): ?>
                                        <a href="<?= e(app_url('/opportunities/pipeline')) ?>" class="dashboard-kpi-card__link">
                                            Ver pipeline <i data-lucide="arrow-right" aria-hidden="true"></i>
                                        </a>
                                    <?php elseif (can('proposals.view')): ?>
                                        <a href="<?= e(app_url('/proposals')) ?>" class="dashboard-kpi-card__link">
                                            Ver propostas <i data-lucide="arrow-right" aria-hidden="true"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endif; ?>

                        <?php if ($showKpiCommitted): ?>
                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="handshake" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Valor comprometido</h2>
                                    <p class="dashboard-kpi-card__value money-value"><?= e(money_br($sponsorsCommitted, 'R$ 0,00')) ?></p>
                                    <p class="dashboard-kpi-card__meta">Fechamentos comerciais</p>
                                    <a href="<?= e(app_url('/sponsors')) ?>" class="dashboard-kpi-card__link">
                                        Ver patrocinadores <i data-lucide="arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endif; ?>

                        <?php if ($showKpiFormalized): ?>
                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="file-signature" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Valor formalizado</h2>
                                    <p class="dashboard-kpi-card__value money-value"><?= e(money_br($contractsFormalized, 'R$ 0,00')) ?></p>
                                    <p class="dashboard-kpi-card__meta">Contratos registrados</p>
                                    <a href="<?= e(app_url('/contracts')) ?>" class="dashboard-kpi-card__link">
                                        Ver contratos <i data-lucide="arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endif; ?>

                        <?php if ($showKpiFinancial): ?>
                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="wallet" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Valor recebido</h2>
                                    <p class="dashboard-kpi-card__value money-value"><?= e(money_br($financialsReceived, 'R$ 0,00')) ?></p>
                                    <p class="dashboard-kpi-card__meta">Financeiro confirmado</p>
                                    <a href="<?= e(app_url('/financials')) ?>" class="dashboard-kpi-card__link">
                                        Ver financeiro <i data-lucide="arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </article>

                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="circle-dollar-sign" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Saldo a receber</h2>
                                    <p class="dashboard-kpi-card__value money-value"><?= e(money_br($financialsRemaining, 'R$ 0,00')) ?></p>
                                    <p class="dashboard-kpi-card__meta">Previsto menos recebido</p>
                                    <a href="<?= e(app_url('/financials')) ?>" class="dashboard-kpi-card__link">
                                        Ver lançamentos <i data-lucide="arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endif; ?>

                        <article class="dashboard-kpi-card<?= $criticalAlertsCount > 0 ? ' dashboard-kpi-card--alert' : '' ?>">
                            <span class="dashboard-kpi-card__icon"><i data-lucide="alert-triangle" aria-hidden="true"></i></span>
                            <div class="dashboard-kpi-card__body">
                                <h2 class="dashboard-kpi-card__title">Pendências críticas</h2>
                                <p class="dashboard-kpi-card__value"><?= $criticalAlertsCount ?></p>
                                <p class="dashboard-kpi-card__meta">Atrasos e vencimentos</p>
                                <a href="#dashboard-alerts" class="dashboard-kpi-card__link">
                                    Ver pendências <i data-lucide="arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </article>
                    </div>
                </div>
            <?php endif; ?>

            <section class="dashboard-section" id="dashboard-alerts">
                <header class="dashboard-section__header">
                    <h2 class="dashboard-section__title"><i data-lucide="bell" aria-hidden="true"></i> Atenção agora</h2>
                    <p class="dashboard-section__subtitle">Alertas operacionais que exigem ação imediata</p>
                </header>
                <article class="dashboard-card dashboard-card--wide dashboard-alerts">
                    <?php if ($dashboardAlerts === []): ?>
                        <div class="dashboard-empty-state">
                            <i data-lucide="check-circle" aria-hidden="true"></i>
                            <p>Nenhuma pendência crítica encontrada.</p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-alerts__list">
                            <?php foreach ($dashboardAlerts as $alert): ?>
                                <li class="dashboard-alert-item">
                                    <a href="<?= e($alert['url']) ?>">
                                        <i data-lucide="<?= e($alert['icon']) ?>" aria-hidden="true"></i>
                                        <span><?= e($alert['label']) ?></span>
                                        <i data-lucide="chevron-right" class="dashboard-alert-item__chev" aria-hidden="true"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>

            <?php
            $reportKey = 'executive';
            require __DIR__ . '/../reports/_charts.php';
            ?>

            <?php if ($hasCommercial): ?>
                <section class="dashboard-section">
                    <header class="dashboard-section__header">
                        <h2 class="dashboard-section__title"><i data-lucide="trending-up" aria-hidden="true"></i> Captação comercial</h2>
                        <p class="dashboard-section__subtitle">Atalhos para empresas, oportunidades, propostas e relatórios</p>
                    </header>
                    <article class="dashboard-card dashboard-card--wide">
                        <?php if ((int) ($opportunitiesOpen ?? 0) === 0 && can('opportunities.create')): ?>
                            <div class="dashboard-empty-state dashboard-empty-state--inline">
                                <p>Nenhuma oportunidade aberta. Crie uma oportunidade para iniciar o acompanhamento comercial.</p>
                                <a href="<?= e(app_url('/opportunities/create')) ?>" class="btn btn-primary btn-sm">
                                    <i data-lucide="plus" aria-hidden="true"></i> Nova oportunidade
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="dashboard-actions">
                            <?php if (can('companies.create')): ?>
                                <a href="<?= e(app_url('/companies/create')) ?>" class="dashboard-action-link">
                                    <i data-lucide="building-2" aria-hidden="true"></i> Nova empresa
                                </a>
                            <?php endif; ?>
                            <?php if (can('opportunities.create')): ?>
                                <a href="<?= e(app_url('/opportunities/create')) ?>" class="dashboard-action-link">
                                    <i data-lucide="target" aria-hidden="true"></i> Nova oportunidade
                                </a>
                            <?php endif; ?>
                            <?php if (can('opportunities.view')): ?>
                                <a href="<?= e(app_url('/opportunities/pipeline')) ?>" class="dashboard-action-link">
                                    <i data-lucide="kanban-square" aria-hidden="true"></i> Ver pipeline
                                </a>
                            <?php endif; ?>
                            <?php if (can('proposals.view')): ?>
                                <a href="<?= e(app_url('/proposals')) ?>" class="dashboard-action-link">
                                    <i data-lucide="file-text" aria-hidden="true"></i> Ver propostas
                                </a>
                            <?php endif; ?>
                            <?php if ($reportsAvailable): ?>
                                <a href="<?= e(app_url('/reports/pipeline')) ?>" class="dashboard-action-link">
                                    <i data-lucide="chart-no-axes-combined" aria-hidden="true"></i> Relatório funil
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                </section>
            <?php endif; ?>

            <div class="dashboard-grid dashboard-grid--two">
                <?php if ($hasFinancial): ?>
                    <section class="dashboard-section">
                        <header class="dashboard-section__header">
                            <h2 class="dashboard-section__title"><i data-lucide="wallet" aria-hidden="true"></i> Financeiro</h2>
                        </header>
                        <article class="dashboard-card">
                            <div class="dashboard-metrics-row">
                                <div class="dashboard-metric">
                                    <span class="dashboard-metric__label">Previsto</span>
                                    <strong class="dashboard-metric__value money-value"><?= e(money_br($financialsPlanned, 'R$ 0,00')) ?></strong>
                                </div>
                                <div class="dashboard-metric">
                                    <span class="dashboard-metric__label">Recebido</span>
                                    <strong class="dashboard-metric__value money-value dashboard-metric__value--ok"><?= e(money_br($financialsReceived, 'R$ 0,00')) ?></strong>
                                </div>
                                <div class="dashboard-metric">
                                    <span class="dashboard-metric__label">Saldo</span>
                                    <strong class="dashboard-metric__value money-value"><?= e(money_br($financialsRemaining, 'R$ 0,00')) ?></strong>
                                </div>
                            </div>
                            <p class="dashboard-card__meta">
                                <span class="pill"><?= (int) $financialsPartial ?> parcial(is)</span>
                                <span class="pill<?= (int) $financialsOverdue > 0 ? ' pill-danger' : '' ?>"><?= (int) $financialsOverdue ?> em atraso</span>
                                <span class="pill"><?= (int) $financialsReconciled ?> conciliado(s)</span>
                            </p>
                            <div class="dashboard-actions">
                                <a href="<?= e(app_url('/financials')) ?>" class="dashboard-action-link">Gerenciar financeiro</a>
                                <?php if ($reportsAvailable): ?>
                                    <a href="<?= e(app_url('/reports/financials')) ?>" class="dashboard-action-link">Relatório financeiro</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ($hasDeliveries): ?>
                    <section class="dashboard-section">
                        <header class="dashboard-section__header">
                            <h2 class="dashboard-section__title"><i data-lucide="clipboard-check" aria-hidden="true"></i> Entregas e contrapartidas</h2>
                        </header>
                        <article class="dashboard-card">
                            <?php if ($counterpartsTotal !== null): ?>
                                <div class="dashboard-metrics-row dashboard-metrics-row--compact">
                                    <div class="dashboard-metric">
                                        <span class="dashboard-metric__label">Cadastradas</span>
                                        <strong class="dashboard-metric__value"><?= (int) $counterpartsTotal ?></strong>
                                    </div>
                                    <div class="dashboard-metric">
                                        <span class="dashboard-metric__label">Entregues</span>
                                        <strong class="dashboard-metric__value"><?= (int) $counterpartsDelivered ?></strong>
                                    </div>
                                    <div class="dashboard-metric">
                                        <span class="dashboard-metric__label">Pendentes</span>
                                        <strong class="dashboard-metric__value"><?= (int) $counterpartsPending ?></strong>
                                    </div>
                                    <div class="dashboard-metric">
                                        <span class="dashboard-metric__label">Atrasadas</span>
                                        <strong class="dashboard-metric__value<?= (int) $counterpartsOverdue > 0 ? ' dashboard-metric__value--danger' : '' ?>"><?= (int) $counterpartsOverdue ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($dossiersTotal !== null): ?>
                                <p class="dashboard-card__meta">
                                    Dossiês: <?= (int) $dossiersTotal ?> cadastrados ·
                                    <?= (int) $dossiersDelivered ?> entregues ·
                                    <?= (int) $dossiersPending ?> pendentes
                                </p>
                            <?php endif; ?>
                            <div class="dashboard-actions">
                                <?php if (can('counterparts.view')): ?>
                                    <a href="<?= e(app_url('/counterparts')) ?>" class="dashboard-action-link">Contrapartidas</a>
                                <?php endif; ?>
                                <?php if (can('dossiers.view')): ?>
                                    <a href="<?= e(app_url('/sponsor-dossiers')) ?>" class="dashboard-action-link">Dossiês</a>
                                <?php endif; ?>
                                <?php if ($reportsAvailable && can('counterparts.view')): ?>
                                    <a href="<?= e(app_url('/reports/counterparts')) ?>" class="dashboard-action-link">Relatório</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    </section>
                <?php endif; ?>
            </div>

            <?php if ($hasFormalization): ?>
                <section class="dashboard-section">
                    <header class="dashboard-section__header">
                        <h2 class="dashboard-section__title"><i data-lucide="folder-check" aria-hidden="true"></i> Formalização e documentação</h2>
                        <p class="dashboard-section__subtitle">Contratos, documentos e dossiês de patrocínio</p>
                    </header>
                    <article class="dashboard-card dashboard-card--wide">
                        <div class="dashboard-grid dashboard-grid--three dashboard-grid--inner">
                            <?php if ($contractsTotal !== null): ?>
                                <div class="dashboard-card dashboard-card--compact">
                                    <h3 class="dashboard-card__title">Contratos</h3>
                                    <p class="dashboard-card__value"><?= (int) $contractsTotal ?></p>
                                    <p class="dashboard-card__meta">
                                        <?= (int) $contractsSigned ?> assinados ·
                                        <?= (int) $contractsAwaiting ?> aguardando
                                    </p>
                                    <a href="<?= e(app_url('/contracts')) ?>" class="dashboard-kpi-card__link">Gerenciar</a>
                                </div>
                            <?php endif; ?>
                            <?php if ($documentsTotal !== null): ?>
                                <div class="dashboard-card dashboard-card--compact">
                                    <h3 class="dashboard-card__title">Documentos</h3>
                                    <p class="dashboard-card__value"><?= (int) $documentsTotal ?></p>
                                    <p class="dashboard-card__meta">
                                        <?= (int) $documentsActive ?> ativos ·
                                        <?= (int) $documentsExpiring ?> vencendo (30d)
                                    </p>
                                    <a href="<?= e(app_url('/documents')) ?>" class="dashboard-kpi-card__link">Gerenciar</a>
                                </div>
                            <?php endif; ?>
                            <?php if ($dossiersTotal !== null): ?>
                                <div class="dashboard-card dashboard-card--compact">
                                    <h3 class="dashboard-card__title">Dossiês</h3>
                                    <p class="dashboard-card__value"><?= (int) $dossiersTotal ?></p>
                                    <p class="dashboard-card__meta">
                                        <?= (int) $dossiersDelivered ?> entregues ·
                                        <?= (int) $dossiersPending ?> pendentes
                                    </p>
                                    <a href="<?= e(app_url('/sponsor-dossiers')) ?>" class="dashboard-kpi-card__link">Gerenciar</a>
                                </div>
                            <?php endif; ?>
                            <?php if ($collectorsReceived !== null): ?>
                                <div class="dashboard-card dashboard-card--compact">
                                    <h3 class="dashboard-card__title">Credenciamento captadores</h3>
                                    <p class="dashboard-card__value"><?= (int) $collectorsPendingReview ?></p>
                                    <p class="dashboard-card__meta">
                                        <?= (int) $collectorsReceived ?> recebidas ·
                                        <?= (int) $collectorsAwaitingDocs ?> aguard. docs ·
                                        <?= (int) $collectorsApproved ?> aprovados
                                    </p>
                                    <a href="<?= e(app_url('/collector-applications')) ?>" class="dashboard-kpi-card__link">Gerenciar</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                </section>
            <?php endif; ?>

            <section class="dashboard-section">
                <header class="dashboard-section__header">
                    <h2 class="dashboard-section__title"><i data-lucide="zap" aria-hidden="true"></i> Ações rápidas</h2>
                    <p class="dashboard-section__subtitle">Atalhos operacionais conforme suas permissões</p>
                </header>
                <div class="dashboard-actions dashboard-actions--grid">
                    <?php if (can('companies.create')): ?>
                        <a href="<?= e(app_url('/companies/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="building-2" aria-hidden="true"></i> Nova empresa
                        </a>
                    <?php endif; ?>
                    <?php if (can('contacts.create')): ?>
                        <a href="<?= e(app_url('/contacts/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="contact" aria-hidden="true"></i> Novo contato
                        </a>
                    <?php endif; ?>
                    <?php if (can('opportunities.create')): ?>
                        <a href="<?= e(app_url('/opportunities/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="target" aria-hidden="true"></i> Nova oportunidade
                        </a>
                    <?php endif; ?>
                    <?php if (can('proposals.create')): ?>
                        <a href="<?= e(app_url('/proposals/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="file-text" aria-hidden="true"></i> Nova proposta
                        </a>
                    <?php endif; ?>
                    <?php if (can('sponsors.create')): ?>
                        <a href="<?= e(app_url('/sponsors/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="handshake" aria-hidden="true"></i> Novo patrocinador
                        </a>
                    <?php endif; ?>
                    <?php if (can('contracts.create')): ?>
                        <a href="<?= e(app_url('/contracts/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="file-signature" aria-hidden="true"></i> Novo contrato
                        </a>
                    <?php endif; ?>
                    <?php if (can('financials.create')): ?>
                        <a href="<?= e(app_url('/financials/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="wallet" aria-hidden="true"></i> Novo lançamento
                        </a>
                    <?php endif; ?>
                    <?php if (can('dossiers.create')): ?>
                        <a href="<?= e(app_url('/sponsor-dossiers/create')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="folder-check" aria-hidden="true"></i> Novo dossiê
                        </a>
                    <?php endif; ?>
                    <?php if ($reportsAvailable): ?>
                        <a href="<?= e(app_url('/reports')) ?>" class="dashboard-action-link dashboard-action-link--pill">
                            <i data-lucide="chart-no-axes-combined" aria-hidden="true"></i> Ver relatórios
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($showAdminSection): ?>
                <section class="dashboard-section dashboard-admin-section">
                    <header class="dashboard-section__header">
                        <h2 class="dashboard-section__title"><i data-lucide="settings" aria-hidden="true"></i> Administração do sistema</h2>
                    </header>
                    <div class="dashboard-grid dashboard-grid--three">
                        <article class="dashboard-card dashboard-card--compact dashboard-card--admin">
                            <h3 class="dashboard-card__title">Sessão atual</h3>
                            <p class="dashboard-card__meta"><?= e($userName) ?></p>
                            <p class="dashboard-card__meta dashboard-card__meta--small"><?= e($userEmail) ?></p>
                            <?php if ($roleNames !== []): ?>
                                <p class="dashboard-card__meta">
                                    <?php foreach ($roleNames as $rn): ?>
                                        <span class="pill"><?= e($rn) ?></span>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>
                        </article>
                        <?php if (can('users.view')): ?>
                            <article class="dashboard-card dashboard-card--compact">
                                <span class="dashboard-card__icon"><i data-lucide="users" aria-hidden="true"></i></span>
                                <h3 class="dashboard-card__title">Usuários</h3>
                                <p class="dashboard-card__meta">Gerenciar acessos e perfis vinculados</p>
                                <a href="<?= e(app_url('/users')) ?>" class="dashboard-kpi-card__link">Abrir módulo</a>
                            </article>
                        <?php endif; ?>
                        <?php if (can('roles.view')): ?>
                            <article class="dashboard-card dashboard-card--compact">
                                <span class="dashboard-card__icon"><i data-lucide="shield" aria-hidden="true"></i></span>
                                <h3 class="dashboard-card__title">Perfis</h3>
                                <p class="dashboard-card__meta">Papéis e permissões por perfil</p>
                                <a href="<?= e(app_url('/roles')) ?>" class="dashboard-kpi-card__link">Abrir módulo</a>
                            </article>
                        <?php endif; ?>
                        <?php if (can('permissions.view')): ?>
                            <article class="dashboard-card dashboard-card--compact">
                                <span class="dashboard-card__icon"><i data-lucide="lock-keyhole" aria-hidden="true"></i></span>
                                <h3 class="dashboard-card__title">Permissões</h3>
                                <p class="dashboard-card__meta">Matriz de permissões do sistema</p>
                                <a href="<?= e(app_url('/permissions')) ?>" class="dashboard-kpi-card__link">Abrir módulo</a>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="notice dashboard-notice">
                <h3 class="h3-card flex items-center gap-12">
                    <i data-lucide="info" aria-hidden="true"></i>
                    Sistema comercial integrado
                </h3>
                <p>
                    O sistema registra o ciclo comercial completo da captação: empresas, oportunidades,
                    propostas, contratos, financeiro, contrapartidas, dossiês e relatórios gerenciais.
                </p>
            </div>

        </div>
    </section>
</div>
