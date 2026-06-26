<?php
/**
 * Visualização de contato.
 *
 * Variáveis: $contact, $decisionLevels, $influenceLevels, $channels, $statuses
 */
$contact         = $contact ?? [];
$decisionLevels  = $decisionLevels ?? [];
$influenceLevels = $influenceLevels ?? [];
$channels        = $channels ?? [];
$statuses        = $statuses ?? [];

$cid        = (int) ($contact['id'] ?? 0);
$companyId  = (int) ($contact['company_id'] ?? 0);
$isArchived = !empty($contact['archived_at']);

$dec = (string) ($contact['decision_level'] ?? 'nao_informado');
$inf = (string) ($contact['influence_level'] ?? 'media');
$st  = (string) ($contact['status'] ?? 'ativo');

$opportunities      = $opportunities ?? [];
$opportunitiesCount = $opportunitiesCount ?? 0;
$opportunityLabels  = $opportunityLabels ?? [];

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
                <span class="kicker kicker-dark">Contatos</span>
                <h1 class="h2-section"><?= e($contact['name'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-decision badge-decision-<?= e($dec) ?>"><?= e($decisionLevels[$dec] ?? $dec) ?></span>
                    <span class="badge-influence badge-influence-<?= e($inf) ?>"><?= e($influenceLevels[$inf] ?? $inf) ?></span>
                    <span class="badge-status badge-status-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <?php if ($isArchived): ?>
                        <span class="badge-status badge-status-arquivado">Arquivado em <?= e($contact['archived_at']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/contacts')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php if (!empty($contact['company_archived_at'])): ?>
            <div class="notice notice-warn" style="margin-bottom:18px;">
                <p class="mb-0"><i data-lucide="alert-triangle"></i> A empresa vinculada a este contato está <strong>arquivada</strong>.</p>
            </div>
        <?php endif; ?>

        <div class="detail-grid">
            <article class="card">
                <h3 class="h3-card"><i data-lucide="building-2"></i> Empresa vinculada</h3>
                <p style="margin-bottom:0;">
                    <a href="<?= e(app_url('/companies/' . $companyId)) ?>" class="link-strong">
                        <i data-lucide="external-link"></i> <?= e($contact['company_name'] ?? ('Empresa #' . $companyId)) ?>
                    </a>
                </p>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="briefcase"></i> Dados profissionais</h3>
                <dl class="meta-list">
                    <dt>Cargo</dt><dd><?= e($dash($contact['position_title'] ?? '')) ?></dd>
                    <dt>Área</dt><dd><?= e($contact['department'] ? ucfirst((string) $contact['department']) : '—') ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="contact"></i> Canais de contato</h3>
                <dl class="meta-list">
                    <dt>E-mail</dt><dd><?= e($dash($contact['email'] ?? '')) ?></dd>
                    <dt>WhatsApp</dt><dd><?= e($dash($contact['whatsapp'] ?? '')) ?></dd>
                    <dt>Telefone</dt><dd><?= e($dash($contact['phone'] ?? '')) ?></dd>
                    <dt>LinkedIn</dt><dd><?= e($dash($contact['linkedin'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="sliders-horizontal"></i> Classificação</h3>
                <dl class="meta-list">
                    <dt>Nível de decisão</dt><dd><?= e($decisionLevels[$dec] ?? $dec) ?></dd>
                    <dt>Influência</dt><dd><?= e($influenceLevels[$inf] ?? $inf) ?></dd>
                    <dt>Canal preferencial</dt><dd><?= e($channels[(string) ($contact['preferred_channel'] ?? '')] ?? '—') ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($contact['owner_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="calendar-clock"></i> Agenda</h3>
                <dl class="meta-list">
                    <dt>Última interação</dt><dd><?= e($dash($contact['last_interaction_at'] ?? '')) ?></dd>
                    <dt>Próximo contato</dt><dd><?= e($dash($contact['next_contact_at'] ?? '')) ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($contact['notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="sticky-note"></i> Observações</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($contact['notes']) ?></p>
            </article>
        <?php endif; ?>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($contact['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($contact['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($contact['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($contact['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>

        <?php if (can('opportunities.view')): ?>
            <section class="contact-opportunities" style="margin-top:26px;">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h2 class="h3-card" style="display:flex;align-items:center;gap:8px;margin:0;">
                            <i data-lucide="handshake"></i> Oportunidades vinculadas
                            <span class="pill"><?= (int) $opportunitiesCount ?></span>
                        </h2>
                    </div>
                    <div class="actions-row">
                        <?php if (can('opportunities.create')): ?>
                            <a href="<?= e(app_url('/contacts/' . $cid . '/opportunities/create')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Nova oportunidade</a>
                        <?php endif; ?>
                        <a href="<?= e(app_url('/opportunities?contact_id=' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Ver todas</a>
                    </div>
                </div>

                <?php if ($opportunities === []): ?>
                    <div class="empty-state">
                        <span class="card-icon"><i data-lucide="handshake"></i></span>
                        <p>Nenhuma oportunidade vinculada a este contato ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Empresa</th>
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
                                        <td><?= e($op['company_name'] ?? '') ?: '—' ?></td>
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
            $blockTitle = 'Tarefas do contato';
            $createUrl  = app_url('/tasks/create?contact_id=' . $cid);
            $allUrl     = app_url('/tasks?contact_id=' . $cid);
            $emptyText  = 'Nenhuma tarefa cadastrada para este contato ainda.';
            require dirname(__DIR__) . '/tasks/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('proposals.view')): ?>
            <?php
            $blockTitle = 'Propostas vinculadas';
            $createUrl  = app_url('/contacts/' . $cid . '/proposals/create');
            $allUrl     = app_url('/proposals?contact_id=' . $cid);
            $emptyText  = 'Nenhuma proposta vinculada a este contato ainda.';
            require dirname(__DIR__) . '/proposals/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos do contato';
            $createUrl  = app_url('/contacts/' . $cid . '/documents/create');
            $allUrl     = app_url('/documents?contact_id=' . $cid);
            $emptyText  = 'Nenhum documento vinculado a este contato ainda.';
            require dirname(__DIR__) . '/documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('sponsors.view')): ?>
            <?php
            $blockTitle = 'Patrocinadores / Fechamentos';
            $createUrl  = app_url('/contacts/' . $cid . '/sponsors/create');
            $allUrl     = app_url('/sponsors?contact_id=' . $cid);
            $emptyText  = 'Nenhum fechamento comercial vinculado a este contato ainda.';
            require dirname(__DIR__) . '/sponsors/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="notice timeline-placeholder" style="margin-top:20px;">
            <p class="mb-0"><i data-lucide="info"></i> Histórico comercial detalhado e relatórios serão vinculados a este contato nas próximas etapas.</p>
        </div>

        <div class="actions-row" style="margin-top:22px;">
            <?php if (can('contacts.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/contacts/' . $cid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
                <form method="post" action="<?= e(app_url('/contacts/' . $cid . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar este contato? Ele sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>

            <?php if (can('contacts.edit') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/contacts/' . $cid . '/restore')) ?>" class="inline-form actions-row">
                    <?= csrf_field() ?>
                    <select name="status" class="restore-status">
                        <?php foreach ($statuses as $key => $label): ?>
                            <?php if ($key === 'arquivado') { continue; } ?>
                            <option value="<?= e($key) ?>" <?= $key === 'ativo' ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
