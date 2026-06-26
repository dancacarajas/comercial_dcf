<?php
/**
 * Visualização de empresa.
 *
 * Variáveis: $company, $priorities, $statuses, $taxRegimes
 */
$company       = $company ?? [];
$priorities    = $priorities ?? [];
$statuses      = $statuses ?? [];
$taxRegimes    = $taxRegimes ?? [];
$contacts           = $contacts ?? [];
$contactsCount      = $contactsCount ?? 0;
$opportunities      = $opportunities ?? [];
$opportunitySummary = $opportunitySummary ?? ['open' => 0, 'closed' => 0, 'lost' => 0, 'open_value' => 0.0];
$opportunityLabels  = $opportunityLabels ?? [];
$cid                = (int) ($company['id'] ?? 0);
$isArchived         = !empty($company['archived_at']);

$oProbClass = static function (int $p): string {
    if ($p >= 90) { return 'top'; }
    if ($p >= 60) { return 'high'; }
    if ($p >= 25) { return 'mid'; }
    return 'low';
};

$prio = (string) ($company['priority'] ?? 'C');
$st   = (string) ($company['status'] ?? 'prospect');

$yesNo = static fn ($v): string => ((int) $v === 1) ? 'Sim' : 'Não';
$dash  = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;

$priorityLabel = $priorities[$prio] ?? $prio;
$statusLabel   = $statuses[$st] ?? $st;
$regimeLabel   = $taxRegimes[(string) ($company['tax_regime_guess'] ?? '')] ?? ($company['tax_regime_guess'] ?? '');
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Empresas</span>
                <h1 class="h2-section"><?= e($company['name'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-priority badge-priority-<?= e($prio) ?>"><?= e($prio) ?> · <?= e($priorityLabel) ?></span>
                    <span class="badge-status badge-status-<?= e($st) ?>"><?= e($statusLabel) ?></span>
                    <?php if ($isArchived): ?>
                        <span class="badge-status badge-status-arquivado">Arquivada em <?= e($company['archived_at']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/companies')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card">
                <h3 class="h3-card"><i data-lucide="building-2"></i> Dados principais</h3>
                <dl class="meta-list">
                    <dt>Nome fantasia</dt><dd><?= e($dash($company['trade_name'] ?? '')) ?></dd>
                    <dt>CNPJ</dt><dd><?= e($dash($company['cnpj'] ?? '')) ?></dd>
                    <dt>Segmento</dt><dd><?= e($company['segment'] ? ucfirst((string) $company['segment']) : '—') ?></dd>
                    <dt>Cidade / UF</dt><dd><?= e($dash(trim((string) ($company['city'] ?? '') . ' ' . (string) ($company['state'] ?? '')))) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="contact"></i> Contato geral</h3>
                <dl class="meta-list">
                    <dt>Site</dt><dd><?= e($dash($company['website'] ?? '')) ?></dd>
                    <dt>LinkedIn</dt><dd><?= e($dash($company['linkedin'] ?? '')) ?></dd>
                    <dt>E-mail</dt><dd><?= e($dash($company['general_email'] ?? '')) ?></dd>
                    <dt>Telefone</dt><dd><?= e($dash($company['general_phone'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="map-pin"></i> Atuação territorial</h3>
                <dl class="meta-list">
                    <dt>Pará</dt><dd><?= e($yesNo($company['operates_para'] ?? 0)) ?></dd>
                    <dt>Carajás</dt><dd><?= e($yesNo($company['operates_carajas'] ?? 0)) ?></dd>
                    <dt>Parauapebas</dt><dd><?= e($yesNo($company['operates_parauapebas'] ?? 0)) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="target"></i> Informações estratégicas</h3>
                <dl class="meta-list">
                    <dt>Regime tributário</dt><dd><?= e($dash($regimeLabel)) ?></dd>
                    <dt>Patrocínio cultural</dt><dd><?= e($yesNo($company['has_cultural_sponsorship_history'] ?? 0)) ?></dd>
                    <dt>Lei Rouanet</dt><dd><?= e($yesNo($company['has_rouanet_history'] ?? 0)) ?></dd>
                    <dt>Aderência ESG</dt><dd><?= e($yesNo($company['has_esg_alignment'] ?? 0)) ?></dd>
                    <dt>Origem</dt><dd><?= e($company['source'] ? ucfirst((string) $company['source']) : '—') ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($company['owner_name'] ?? '')) ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($company['notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="sticky-note"></i> Observações</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($company['notes']) ?></p>
            </article>
        <?php endif; ?>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($company['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($company['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($company['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($company['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>

        <?php if (can('contacts.view')): ?>
            <section class="company-contacts" style="margin-top:26px;">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h2 class="h3-card" style="display:flex;align-items:center;gap:8px;margin:0;">
                            <i data-lucide="contact"></i> Contatos da empresa
                            <span class="pill"><?= (int) $contactsCount ?> ativo(s)</span>
                        </h2>
                    </div>
                    <div class="actions-row">
                        <?php if (can('contacts.create')): ?>
                            <a href="<?= e(app_url('/companies/' . $cid . '/contacts/create')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="user-plus"></i> Novo contato</a>
                        <?php endif; ?>
                        <a href="<?= e(app_url('/contacts?company_id=' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Ver todos</a>
                    </div>
                </div>

                <?php if ($contacts === []): ?>
                    <div class="empty-state">
                        <span class="card-icon"><i data-lucide="contact"></i></span>
                        <p>Nenhum contato cadastrado para esta empresa ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Área</th>
                                    <th>E-mail</th>
                                    <th>WhatsApp</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contacts as $ct): ?>
                                    <tr>
                                        <td><strong><?= e($ct['name']) ?></strong></td>
                                        <td><?= e($ct['position_title'] ?? '') ?: '—' ?></td>
                                        <td><?= e($ct['department'] ? ucfirst((string) $ct['department']) : '—') ?></td>
                                        <td><?= e($ct['email'] ?? '') ?: '—' ?></td>
                                        <td><?= e($ct['whatsapp'] ?? '') ?: '—' ?></td>
                                        <td><span class="badge-status badge-status-<?= e((string) $ct['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $ct['status']))) ?></span></td>
                                        <td>
                                            <div class="actions-row" style="justify-content:flex-end;">
                                                <a href="<?= e(app_url('/contacts/' . (int) $ct['id'])) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (can('opportunities.view')): ?>
            <section class="company-opportunities" style="margin-top:26px;">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h2 class="h3-card" style="display:flex;align-items:center;gap:8px;margin:0;">
                            <i data-lucide="handshake"></i> Oportunidades da empresa
                        </h2>
                        <p class="page-sub" style="margin:6px 0 0;">
                            <span class="pill"><?= (int) $opportunitySummary['open'] ?> aberta(s)</span>
                            <span class="pill"><?= (int) $opportunitySummary['closed'] ?> fechada(s)</span>
                            <span class="money-value">Em negociação: <?= e(money_br($opportunitySummary['open_value'], 'R$ 0,00')) ?></span>
                        </p>
                    </div>
                    <div class="actions-row">
                        <?php if (can('opportunities.create')): ?>
                            <a href="<?= e(app_url('/companies/' . $cid . '/opportunities/create')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Nova oportunidade</a>
                        <?php endif; ?>
                        <a href="<?= e(app_url('/opportunities?company_id=' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Ver todas</a>
                    </div>
                </div>

                <?php if ($opportunities === []): ?>
                    <div class="empty-state">
                        <span class="card-icon"><i data-lucide="handshake"></i></span>
                        <p>Nenhuma oportunidade cadastrada para esta empresa ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Contato</th>
                                    <th>Cota</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Prob.</th>
                                    <th>Próxima ação</th>
                                    <th style="text-align:right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($opportunities as $op): ?>
                                    <?php
                                    $opProb     = (int) ($op['probability'] ?? 0);
                                    $opQuota    = trim((string) ($op['quota_name'] ?? ''));
                                    $opQuotaLeg = trim((string) ($op['quota_interest'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><strong><?= e($op['title']) ?></strong></td>
                                        <td><?= e($op['contact_name'] ?? '') ?: '—' ?></td>
                                        <td>
                                            <?php if ($opQuota !== ''): ?><span class="badge-quota"><?= e($opQuota) ?></span>
                                            <?php elseif ($opQuotaLeg !== ''): ?><span class="quota-legacy"><?= e($opQuotaLeg) ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td><span class="badge-status badge-status-<?= e((string) $op['status']) ?>"><?= e($opportunityLabels[(string) $op['status']] ?? $op['status']) ?></span></td>
                                        <td class="money-value"><?= e(money_br($op['estimated_value'] ?? null)) ?></td>
                                        <td><span class="badge-probability badge-probability-<?= $oProbClass($opProb) ?>"><?= $opProb ?>%</span></td>
                                        <td><?= e($op['next_action_at'] ?? '') ?: '—' ?></td>
                                        <td>
                                            <div class="actions-row" style="justify-content:flex-end;">
                                                <a href="<?= e(app_url('/opportunities/' . (int) $op['id'])) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (can('tasks.view')): ?>
            <?php
            $blockTitle = 'Tarefas da empresa';
            $createUrl  = app_url('/tasks/create?company_id=' . $cid);
            $allUrl     = app_url('/tasks?company_id=' . $cid);
            $emptyText  = 'Nenhuma tarefa cadastrada para esta empresa ainda.';
            require dirname(__DIR__) . '/tasks/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('proposals.view')): ?>
            <?php
            $blockTitle = 'Propostas da empresa';
            $createUrl  = app_url('/companies/' . $cid . '/proposals/create');
            $allUrl     = app_url('/proposals?company_id=' . $cid);
            $emptyText  = 'Nenhuma proposta cadastrada para esta empresa ainda.';
            require dirname(__DIR__) . '/proposals/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos da empresa';
            $createUrl  = app_url('/companies/' . $cid . '/documents/create');
            $allUrl     = app_url('/documents?company_id=' . $cid);
            $emptyText  = 'Nenhum documento cadastrado para esta empresa ainda.';
            require dirname(__DIR__) . '/documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('sponsors.view')): ?>
            <?php
            $blockTitle = 'Patrocinadores / Fechamentos';
            $createUrl  = app_url('/companies/' . $cid . '/sponsors/create');
            $allUrl     = app_url('/sponsors?company_id=' . $cid);
            $emptyText  = 'Nenhum fechamento comercial registrado para esta empresa ainda.';
            require dirname(__DIR__) . '/sponsors/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="notice" style="margin-top:20px;">
            <p class="mb-0"><i data-lucide="info"></i> Histórico comercial detalhado e relatórios serão vinculados a esta empresa nas próximas etapas.</p>
        </div>

        <div class="actions-row" style="margin-top:22px;">
            <?php if (can('companies.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/companies/' . $cid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
                <form method="post" action="<?= e(app_url('/companies/' . $cid . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar esta empresa? Ela sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>

            <?php if (can('companies.edit') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/companies/' . $cid . '/restore')) ?>" class="inline-form actions-row">
                    <?= csrf_field() ?>
                    <select name="status" class="restore-status">
                        <?php foreach ($statuses as $key => $label): ?>
                            <?php if ($key === 'arquivado') { continue; } ?>
                            <option value="<?= e($key) ?>" <?= $key === 'prospect' ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
