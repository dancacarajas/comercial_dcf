<?php
/**
 * Painel administrativo — central gerencial DCX.
 */
$user                = $user ?? null;
$userName            = $user['name'] ?? 'Usuario';
$userEmail           = $user['email'] ?? '';
$roleNames           = $roleNames ?? [];
$primaryRole         = $primaryRole ?? ($roleNames[0] ?? 'Usuario');
$appEnvLabel         = $appEnvLabel ?? 'Producao';
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

$tasksOpen    = $tasksOpen ?? null;
$tasksOverdue = $tasksOverdue ?? null;
$tasksToday   = $tasksToday ?? null;
$tasksMine    = $tasksMine ?? null;
$leadsNew     = $leadsNew ?? null;
$leadsTriagem = $leadsTriagem ?? null;
$quotasCount  = $quotasCount ?? null;
$quotasAvailable = $quotasAvailable ?? null;

$visualizations = $visualizations ?? [];
$reportsAvailable = !empty($reportsAvailable);

$showKpiNegotiation = can('opportunities.view') || can('proposals.view');
$showKpiCommitted   = can('sponsors.view');
$showKpiFormalized  = can('contracts.view');
$showKpiFinancial   = can('financials.view');

$dateLabel = (new DateTime('now', new DateTimeZone('America/Belem')))->format('d/m/Y');

$maxValue = static function (array $series): float {
    $max = 0.0;
    foreach ($series as $item) {
        $max = max($max, (float) ($item['value'] ?? 0));
    }
    return $max > 0 ? $max : 1.0;
};

$barWidth = static fn (float $value, float $max): string => number_format(max(0, min(100, ($value / $max) * 100)), 2, '.', '');

$commercialSeries = array_values(array_filter([
    $companiesCount !== null ? ['label' => 'Empresas', 'value' => (float) $companiesCount, 'display' => (string) (int) $companiesCount] : null,
    $contactsCount !== null ? ['label' => 'Contatos', 'value' => (float) $contactsCount, 'display' => (string) (int) $contactsCount] : null,
    $opportunitiesOpen !== null ? ['label' => 'Oportunidades', 'value' => (float) $opportunitiesOpen, 'display' => (string) (int) $opportunitiesOpen] : null,
    $proposalsOpen !== null ? ['label' => 'Propostas abertas', 'value' => (float) $proposalsOpen, 'display' => (string) (int) $proposalsOpen] : null,
    $sponsorsTotal !== null ? ['label' => 'Patrocinadores', 'value' => (float) $sponsorsTotal, 'display' => (string) (int) $sponsorsTotal] : null,
], static fn ($item): bool => $item !== null));

$financialSeries = array_values(array_filter([
    $financialsPlanned !== null ? ['label' => 'Previsto', 'value' => (float) $financialsPlanned, 'display' => money_br($financialsPlanned, 'R$ 0,00')] : null,
    $financialsReceived !== null ? ['label' => 'Recebido', 'value' => (float) $financialsReceived, 'display' => money_br($financialsReceived, 'R$ 0,00')] : null,
    $financialsRemaining !== null ? ['label' => 'Saldo', 'value' => (float) $financialsRemaining, 'display' => money_br($financialsRemaining, 'R$ 0,00')] : null,
], static fn ($item): bool => $item !== null));

$operationalSeries = array_values(array_filter([
    $tasksOpen !== null ? ['label' => 'Tarefas abertas', 'value' => (float) $tasksOpen, 'display' => (string) (int) $tasksOpen] : null,
    $leadsNew !== null ? ['label' => 'Leads novos', 'value' => (float) $leadsNew, 'display' => (string) (int) $leadsNew] : null,
    $collectorsPendingReview !== null ? ['label' => 'Captadores em analise', 'value' => (float) $collectorsPendingReview, 'display' => (string) (int) $collectorsPendingReview] : null,
    $contractsAwaiting !== null ? ['label' => 'Contratos aguardando', 'value' => (float) $contractsAwaiting, 'display' => (string) (int) $contractsAwaiting] : null,
    $documentsExpiring !== null ? ['label' => 'Documentos vencendo', 'value' => (float) $documentsExpiring, 'display' => (string) (int) $documentsExpiring] : null,
], static fn ($item): bool => $item !== null));

$commercialMax = $maxValue($commercialSeries);
$financialMax = $maxValue($financialSeries);
$operationalMax = $maxValue($operationalSeries);

$attentionTotal = $criticalAlertsCount
    + (int) ($tasksOverdue ?? 0)
    + (int) ($financialsOverdue ?? 0)
    + (int) ($counterpartsOverdue ?? 0)
    + (int) ($documentsExpired ?? 0);
$healthyTotal = max(1, count($commercialSeries) + count($financialSeries) + count($operationalSeries));
$attentionPct = max(0, min(100, ($attentionTotal / max(1, $attentionTotal + $healthyTotal)) * 100));
$healthyPct = 100 - $attentionPct;

$funnel = $visualizations['funnel'] ?? [];
$reportKey = 'executive';
?>

<div class="dashboard-main">
    <section class="dashboard-hero section-dark">
        <div class="container dashboard-hero__content">
            <div>
                <h1 class="h2-section dashboard-hero__title">Painel Administrativo</h1>
                <p class="lead dashboard-hero__lead">
                    Visao gerencial da captacao, contratos, financeiro, contrapartidas e dossies do Danca Carajas.
                </p>
            </div>
            <div class="dashboard-hero__meta">
                <span class="badge-dcx badge-ok"><i data-lucide="user-check" aria-hidden="true"></i><?= e($userName) ?></span>
                <span class="badge-dcx badge-muted"><i data-lucide="shield" aria-hidden="true"></i><?= e($primaryRole) ?></span>
                <span class="badge-dcx badge-env"><i data-lucide="server" aria-hidden="true"></i><?= e($appEnvLabel) ?></span>
                <span class="badge-dcx badge-muted"><i data-lucide="calendar" aria-hidden="true"></i>Atualizado em <?= e($dateLabel) ?></span>
            </div>
        </div>
    </section>

    <section class="section section-soft dashboard-body">
        <div class="container">
            <?php if ($showKpiNegotiation || $showKpiCommitted || $showKpiFormalized || $showKpiFinancial || $criticalAlertsCount > 0): ?>
                <div class="dashboard-kpi-strip">
                    <div class="dashboard-grid dashboard-grid--kpis">
                        <?php if ($showKpiNegotiation): ?>
                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="target" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Valor em negociacao</h2>
                                    <p class="dashboard-kpi-card__value money-value"><?= e(money_br(can('opportunities.view') ? $opportunitiesValue : $proposalsOpenValue, 'R$ 0,00')) ?></p>
                                    <p class="dashboard-kpi-card__meta">Pipeline comercial ativo</p>
                                    <a href="<?= e(app_url(can('opportunities.view') ? '/opportunities/pipeline' : '/proposals')) ?>" class="dashboard-kpi-card__link">
                                        Ver pipeline <i data-lucide="arrow-right" aria-hidden="true"></i>
                                    </a>
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
                                    <a href="<?= e(app_url('/sponsors')) ?>" class="dashboard-kpi-card__link">Ver patrocinadores <i data-lucide="arrow-right" aria-hidden="true"></i></a>
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
                                    <a href="<?= e(app_url('/contracts')) ?>" class="dashboard-kpi-card__link">Ver contratos <i data-lucide="arrow-right" aria-hidden="true"></i></a>
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
                                    <a href="<?= e(app_url('/financials')) ?>" class="dashboard-kpi-card__link">Ver financeiro <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                                </div>
                            </article>
                            <article class="dashboard-kpi-card">
                                <span class="dashboard-kpi-card__icon"><i data-lucide="circle-dollar-sign" aria-hidden="true"></i></span>
                                <div class="dashboard-kpi-card__body">
                                    <h2 class="dashboard-kpi-card__title">Saldo a receber</h2>
                                    <p class="dashboard-kpi-card__value money-value"><?= e(money_br($financialsRemaining, 'R$ 0,00')) ?></p>
                                    <p class="dashboard-kpi-card__meta">Previsto menos recebido</p>
                                    <a href="<?= e(app_url('/financials')) ?>" class="dashboard-kpi-card__link">Ver lancamentos <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                                </div>
                            </article>
                        <?php endif; ?>

                        <article class="dashboard-kpi-card<?= $criticalAlertsCount > 0 ? ' dashboard-kpi-card--alert' : '' ?>">
                            <span class="dashboard-kpi-card__icon"><i data-lucide="alert-triangle" aria-hidden="true"></i></span>
                            <div class="dashboard-kpi-card__body">
                                <h2 class="dashboard-kpi-card__title">Pendencias criticas</h2>
                                <p class="dashboard-kpi-card__value"><?= $criticalAlertsCount ?></p>
                                <p class="dashboard-kpi-card__meta">Atrasos e vencimentos</p>
                                <a href="#dashboard-alerts" class="dashboard-kpi-card__link">Ver pendencias <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </article>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-command-grid">
                <div class="dashboard-cell dashboard-span-8">
                    <?php if ($funnel !== []): ?>
                        <?php
                        $funnelTitle = 'Funil comercial';
                        $funnelSubtitle = 'Etapas da captacao e conversao entre fases';
                        $funnelLink = $reportsAvailable ? app_url('/reports/pipeline') : null;
                        $funnelLinkLabel = 'Ver relatorio completo';
                        require __DIR__ . '/../reports/_funnel.php';
                        ?>
                    <?php else: ?>
                        <article class="dashboard-panel dashboard-panel--full">
                            <header class="dashboard-panel__header">
                                <div>
                                    <h2><i data-lucide="git-branch" aria-hidden="true"></i> Funil comercial</h2>
                                    <p>Etapas da captacao e conversao entre fases</p>
                                </div>
                            </header>
                            <div class="dashboard-empty-state">
                                <i data-lucide="bar-chart-3" aria-hidden="true"></i>
                                <p>Sem dados suficientes para montar o funil.</p>
                            </div>
                        </article>
                    <?php endif; ?>
                </div>

                <article class="dashboard-panel dashboard-span-4">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="activity" aria-hidden="true"></i> Saude operacional</h2>
                            <p>Leitura rapida de risco e estabilidade</p>
                        </div>
                    </header>
                    <div class="dashboard-health">
                        <div class="dashboard-donut" style="--attention: <?= e(number_format($attentionPct, 2, '.', '')) ?>%; --healthy: <?= e(number_format($healthyPct, 2, '.', '')) ?>%;">
                            <div class="dashboard-donut__center">
                                <strong><?= $attentionTotal ?></strong>
                                <span>pontos de atencao</span>
                            </div>
                        </div>
                        <dl class="dashboard-health__legend">
                            <div><dt>Criticas</dt><dd><?= $criticalAlertsCount ?></dd></div>
                            <div><dt>Tarefas vencidas</dt><dd><?= (int) ($tasksOverdue ?? 0) ?></dd></div>
                            <div><dt>Financeiro em atraso</dt><dd><?= (int) ($financialsOverdue ?? 0) ?></dd></div>
                        </dl>
                    </div>
                </article>

                <article class="dashboard-panel dashboard-span-6">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="wallet" aria-hidden="true"></i> Grafico financeiro</h2>
                            <p>Previsto, recebido e saldo proporcional</p>
                        </div>
                        <?php if (can('financials.view')): ?>
                            <a href="<?= e(app_url('/financials')) ?>">Abrir financeiro</a>
                        <?php endif; ?>
                    </header>
                    <div class="dashboard-bars">
                        <?php foreach ($financialSeries as $item): ?>
                            <div class="dashboard-bar-row">
                                <span><?= e($item['label']) ?></span>
                                <div class="dashboard-bar-track"><div style="width: <?= e($barWidth((float) $item['value'], $financialMax)) ?>%;"></div></div>
                                <strong><?= e($item['display']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="dashboard-panel dashboard-span-6">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="database" aria-hidden="true"></i> Base comercial</h2>
                            <p>Volume de cadastros e atividades comerciais</p>
                        </div>
                        <?php if (can('companies.view')): ?>
                            <a href="<?= e(app_url('/companies')) ?>">Ver empresas</a>
                        <?php endif; ?>
                    </header>
                    <div class="dashboard-bars dashboard-bars--compact">
                        <?php foreach ($commercialSeries as $item): ?>
                            <div class="dashboard-bar-row">
                                <span><?= e($item['label']) ?></span>
                                <div class="dashboard-bar-track"><div style="width: <?= e($barWidth((float) $item['value'], $commercialMax)) ?>%;"></div></div>
                                <strong><?= e($item['display']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <section class="dashboard-panel dashboard-span-4" id="dashboard-alerts">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="bell" aria-hidden="true"></i> Atencao agora</h2>
                            <p>Alertas que exigem acao imediata</p>
                        </div>
                    </header>
                    <?php if ($dashboardAlerts === []): ?>
                        <div class="dashboard-empty-state dashboard-empty-state--panel">
                            <i data-lucide="check-circle" aria-hidden="true"></i>
                            <p>Nenhuma pendencia critica encontrada.</p>
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
                </section>

                <article class="dashboard-panel dashboard-span-8">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="zap" aria-hidden="true"></i> Acoes rapidas</h2>
                            <p>Atalhos mais usados conforme suas permissoes</p>
                        </div>
                    </header>
                    <div class="dashboard-quick-grid">
                        <?php if (can('companies.create')): ?><a href="<?= e(app_url('/companies/create')) ?>"><i data-lucide="building-2"></i><span>Nova empresa</span></a><?php endif; ?>
                        <?php if (can('contacts.create')): ?><a href="<?= e(app_url('/contacts/create')) ?>"><i data-lucide="contact"></i><span>Novo contato</span></a><?php endif; ?>
                        <?php if (can('opportunities.create')): ?><a href="<?= e(app_url('/opportunities/create')) ?>"><i data-lucide="target"></i><span>Nova oportunidade</span></a><?php endif; ?>
                        <?php if (can('proposals.create')): ?><a href="<?= e(app_url('/proposals/create')) ?>"><i data-lucide="file-text"></i><span>Nova proposta</span></a><?php endif; ?>
                        <?php if (can('sponsors.create')): ?><a href="<?= e(app_url('/sponsors/create')) ?>"><i data-lucide="handshake"></i><span>Novo patrocinador</span></a><?php endif; ?>
                        <?php if (can('financials.create')): ?><a href="<?= e(app_url('/financials/create')) ?>"><i data-lucide="wallet"></i><span>Novo lancamento</span></a><?php endif; ?>
                        <?php if ($reportsAvailable): ?><a href="<?= e(app_url('/reports')) ?>"><i data-lucide="chart-no-axes-combined"></i><span>Relatorios</span></a><?php endif; ?>
                    </div>
                </article>

                <article class="dashboard-panel dashboard-span-4">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="clipboard-list" aria-hidden="true"></i> Operacao</h2>
                            <p>Tarefas, leads e fila de captadores</p>
                        </div>
                    </header>
                    <div class="dashboard-bars dashboard-bars--compact">
                        <?php foreach ($operationalSeries as $item): ?>
                            <div class="dashboard-bar-row">
                                <span><?= e($item['label']) ?></span>
                                <div class="dashboard-bar-track"><div style="width: <?= e($barWidth((float) $item['value'], $operationalMax)) ?>%;"></div></div>
                                <strong><?= e($item['display']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="dashboard-panel dashboard-span-4">
                    <header class="dashboard-panel__header">
                        <div>
                            <h2><i data-lucide="folder-check" aria-hidden="true"></i> Formalizacao</h2>
                            <p>Contratos, documentos e dossies</p>
                        </div>
                    </header>
                    <dl class="dashboard-mini-stats">
                        <div><dt>Contratos</dt><dd><?= (int) ($contractsTotal ?? 0) ?></dd></div>
                        <div><dt>Documentos</dt><dd><?= (int) ($documentsTotal ?? 0) ?></dd></div>
                        <div><dt>Dossies</dt><dd><?= (int) ($dossiersTotal ?? 0) ?></dd></div>
                    </dl>
                </article>

                <?php if ($showAdminSection): ?>
                    <article class="dashboard-panel dashboard-span-4">
                        <header class="dashboard-panel__header">
                            <div>
                                <h2><i data-lucide="settings" aria-hidden="true"></i> Administracao</h2>
                                <p>Sessao e modulos administrativos</p>
                            </div>
                        </header>
                        <dl class="dashboard-mini-stats">
                            <div><dt>Usuario</dt><dd><?= e($userName) ?></dd></div>
                            <div><dt>Perfil</dt><dd><?= e($primaryRole) ?></dd></div>
                            <div><dt>Ambiente</dt><dd><?= e($appEnvLabel) ?></dd></div>
                        </dl>
                    </article>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
