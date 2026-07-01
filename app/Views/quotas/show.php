<?php
/**
 * Visualização de cota de patrocínio + oportunidades vinculadas.
 *
 * Variáveis: $quota, $model (Quota), $statuses, $idealProfiles, $remaining,
 * $linkedSummary, $linked, $canSeeOpps, $oppStatusLabels
 */
$quota           = $quota ?? [];
$statuses        = $statuses ?? [];
$idealProfiles   = $idealProfiles ?? [];
$remaining       = (int) ($remaining ?? 0);
$linkedSummary   = $linkedSummary ?? [];
$linked          = $linked ?? [];
$canSeeOpps      = $canSeeOpps ?? false;
$oppStatusLabels = $oppStatusLabels ?? [];

$qid        = (int) ($quota['id'] ?? 0);
$isArchived = !empty($quota['archived_at']);
$st         = (string) ($quota['status'] ?? '');
$profile    = (string) ($quota['ideal_profile'] ?? '');

$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;

$oProbClass = static function (int $p): string {
    if ($p >= 90) { return 'top'; }
    if ($p >= 60) { return 'high'; }
    if ($p >= 25) { return 'mid'; }
    return 'low';
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Cota de patrocínio</span>
                <h1 class="h2-section"><?= e($quota['name'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-quota badge-quota-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="money-value"><?= $quota['amount'] !== null ? e(money_br($quota['amount'])) : 'Valor flexível' ?></span>
                    <?php if ($isArchived): ?>
                        <span class="badge-status badge-status-arquivado">Arquivada em <?= e($quota['archived_at']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/quotas')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card quota-card">
                <h3 class="h3-card"><i data-lucide="badge-dollar-sign"></i> Dados da cota</h3>
                <dl class="meta-list">
                    <dt>Nome comercial</dt><dd><?= e($dash($quota['commercial_name'] ?? '')) ?></dd>
                    <dt>Projeto</dt>
                    <dd>
                        <?php if (!empty($quota['incentive_project_id'])): ?>
                            <a href="<?= e(app_url('/projects/' . (int) $quota['incentive_project_id'])) ?>" class="link-strong">
                                <?= e($quota['project_name'] ?? ('Projeto #' . (int) $quota['incentive_project_id'])) ?>
                            </a>
                        <?php else: ?>
                            <?= e($dash(null)) ?>
                        <?php endif; ?>
                    </dd>
                    <dt>Valor</dt><dd class="money-value"><?= $quota['amount'] !== null ? e(money_br($quota['amount'])) : 'Flexível' ?></dd>
                    <dt>Status</dt><dd><span class="badge-quota badge-quota-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></dd>
                    <dt>Perfil indicado</dt><dd><?= e($idealProfiles[$profile] ?? ($profile !== '' ? $profile : '—')) ?></dd>
                    <dt>Ordem de exibição</dt><dd><?= (int) ($quota['display_order'] ?? 0) ?></dd>
                </dl>
            </article>

            <article class="card quota-card">
                <h3 class="h3-card"><i data-lucide="boxes"></i> Quantidades (manuais)</h3>
                <div class="quota-stock">
                    <div class="quota-stock-item"><span class="quota-stock-num"><?= (int) ($quota['available_quantity'] ?? 0) ?></span><span class="quota-stock-label">Disponível</span></div>
                    <div class="quota-stock-item"><span class="quota-stock-num"><?= (int) ($quota['reserved_quantity'] ?? 0) ?></span><span class="quota-stock-label">Reservada</span></div>
                    <div class="quota-stock-item"><span class="quota-stock-num"><?= (int) ($quota['closed_quantity'] ?? 0) ?></span><span class="quota-stock-label">Fechada</span></div>
                    <div class="quota-stock-item"><span class="remaining-quantity <?= $remaining < 0 ? 'remaining-negative' : '' ?>"><?= $remaining ?></span><span class="quota-stock-label">Saldo</span></div>
                </div>
                <small class="field-hint">Campos manuais editados pelo administrador. O resumo calculado abaixo é apenas apoio.</small>
            </article>
        </div>

        <?php if (!empty($quota['description']) || !empty($quota['notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <?php if (!empty($quota['description'])): ?>
                    <h3 class="h3-card"><i data-lucide="file-text"></i> Descrição</h3>
                    <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($quota['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($quota['notes'])): ?>
                    <h3 class="h3-card" style="margin-top:14px;"><i data-lucide="sticky-note"></i> Observações</h3>
                    <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($quota['notes']) ?></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if ($canSeeOpps): ?>
            <article class="card quota-summary" style="margin-top:18px;">
                <div class="page-head" style="margin-bottom:14px;">
                    <h2 class="h3-card" style="margin:0;"><i data-lucide="handshake"></i> Oportunidades vinculadas a esta cota</h2>
                </div>
                <div class="summary-grid">
                    <div class="metric-card"><span class="metric-num"><?= (int) ($linkedSummary['total'] ?? 0) ?></span><span class="metric-label">Vinculadas</span></div>
                    <div class="metric-card"><span class="metric-num"><?= (int) ($linkedSummary['open'] ?? 0) ?></span><span class="metric-label">Abertas</span></div>
                    <div class="metric-card"><span class="metric-num"><?= (int) ($linkedSummary['reserved'] ?? 0) ?></span><span class="metric-label">Em reserva de cota</span></div>
                    <div class="metric-card"><span class="metric-num"><?= (int) ($linkedSummary['closed'] ?? 0) ?></span><span class="metric-label">Fechadas</span></div>
                    <div class="metric-card"><span class="metric-num money-value"><?= e(money_br($linkedSummary['est_value'] ?? 0, 'R$ 0,00')) ?></span><span class="metric-label">Valor estimado</span></div>
                </div>

                <?php if ($linked === []): ?>
                    <p class="pipeline-empty" style="margin-top:14px;">Nenhuma oportunidade vinculada a esta cota ainda.</p>
                <?php else: ?>
                    <div class="table-wrap quota-linked-list" style="margin-top:14px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Empresa</th>
                                    <th>Contato</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Prob.</th>
                                    <th>Próxima ação</th>
                                    <th style="text-align:right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linked as $op): ?>
                                    <?php $opProb = (int) ($op['probability'] ?? 0); $ost = (string) ($op['status'] ?? ''); ?>
                                    <tr>
                                        <td><strong><?= e($op['title']) ?></strong></td>
                                        <td><?= e($op['company_name'] ?? '') ?: '—' ?></td>
                                        <td><?= e($op['contact_name'] ?? '') ?: '—' ?></td>
                                        <td><span class="badge-status badge-status-<?= e($ost) ?>"><?= e($oppStatusLabels[$ost] ?? $ost) ?></span></td>
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
                    <p style="margin-top:10px;">
                        <a href="<?= e(app_url('/opportunities?quota_id=' . $qid)) ?>" class="link-strong">Ver todas as oportunidades desta cota</a>
                    </p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if (can('proposals.view')): ?>
            <?php
            $blockTitle = 'Propostas vinculadas à cota';
            $createUrl  = app_url('/quotas/' . $qid . '/proposals/create');
            $allUrl     = app_url('/proposals?quota_id=' . $qid);
            $emptyText  = 'Nenhuma proposta vinculada a esta cota ainda.';
            require dirname(__DIR__) . '/proposals/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos da cota';
            $createUrl  = app_url('/quotas/' . $qid . '/documents/create');
            $allUrl     = app_url('/documents?quota_id=' . $qid);
            $emptyText  = 'Nenhum documento vinculado a esta cota ainda.';
            require dirname(__DIR__) . '/documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('sponsors.view')): ?>
            <?php
            $blockTitle = 'Patrocinadores / Fechamentos';
            $createUrl  = app_url('/quotas/' . $qid . '/sponsors/create');
            $allUrl     = app_url('/sponsors?quota_id=' . $qid);
            $emptyText  = 'Nenhum fechamento comercial vinculado a esta cota ainda.';
            require dirname(__DIR__) . '/sponsors/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('counterparts.view')): ?>
            <?php
            $blockTitle = 'Contrapartidas';
            $createUrl  = can('counterparts.create') ? app_url('/quotas/' . $qid . '/counterparts/create') : '';
            $allUrl     = app_url('/counterparts?quota_id=' . $qid);
            $emptyText  = 'Nenhuma contrapartida vinculada a esta cota ainda.';
            require dirname(__DIR__) . '/counterparts/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('contracts.view')): ?>
            <?php
            $blockTitle = 'Contratos';
            $createUrl  = can('contracts.create') ? app_url('/quotas/' . $qid . '/contracts/create') : '';
            $allUrl     = app_url('/contracts?quota_id=' . $qid);
            $emptyText  = 'Nenhum contrato vinculado a esta cota ainda.';
            require dirname(__DIR__) . '/contracts/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('financials.view')): ?>
            <?php
            $blockTitle = 'Financeiro';
            $createUrl  = can('financials.create') ? app_url('/quotas/' . $qid . '/financials/create') : '';
            $allUrl     = app_url('/financials?quota_id=' . $qid);
            $emptyText  = 'Nenhum lançamento financeiro vinculado a esta cota ainda.';
            require dirname(__DIR__) . '/financials/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('dossiers.view')): ?>
            <?php
            $blockTitle = 'Dossiês / Prestação Comercial';
            $createUrl  = can('dossiers.create') ? app_url('/quotas/' . $qid . '/dossiers/create') : '';
            $allUrl     = app_url('/sponsor-dossiers?quota_id=' . $qid);
            $emptyText  = 'Nenhum dossiê vinculado a esta cota ainda.';
            require dirname(__DIR__) . '/sponsor_dossiers/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="notice" style="margin-top:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Contratos, assinatura digital e portal do patrocinador serão tratados em etapas futuras.</p>
        </div>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($quota['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($quota['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($quota['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($quota['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>

        <div class="actions-row" style="margin-top:22px;">
            <?php if (can('quotas.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/quotas/' . $qid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
                <form method="post" action="<?= e(app_url('/quotas/' . $qid . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar esta cota? Ela sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>

            <?php if (can('quotas.edit') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/quotas/' . $qid . '/restore')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
