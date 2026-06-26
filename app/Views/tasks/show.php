<?php
/**
 * Visualização de tarefa + ações rápidas.
 *
 * Variáveis: $task, $model (Task), $types, $priorities, $statuses
 */
$task       = $task ?? [];
$types      = $types ?? [];
$priorities = $priorities ?? [];
$statuses   = $statuses ?? [];

$tid        = (int) ($task['id'] ?? 0);
$isArchived = !empty($task['archived_at']);
$st         = (string) ($task['status'] ?? '');
$pr         = (string) ($task['priority'] ?? '');
$tp         = (string) ($task['type'] ?? '');
$overdue    = isset($model) && $model->isOverdue($task);
$isDone     = $st === 'concluida';

$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
$due  = trim((string) ($task['due_date'] ?? '') . ' ' . substr((string) ($task['due_time'] ?? ''), 0, 5));
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Tarefa</span>
                <h1 class="h2-section"><?= e($task['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-task-type"><?= e($types[$tp] ?? $tp) ?></span>
                    <span class="badge-priority badge-priority-<?= e($pr) ?>"><?= e($priorities[$pr] ?? $pr) ?></span>
                    <span class="badge-task badge-task-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <?php if ($overdue): ?><span class="badge-task badge-task-vencida">Vencida</span><?php endif; ?>
                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivada em <?= e($task['archived_at']) ?></span><?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/tasks')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card task-card <?= $overdue ? 'task-overdue' : '' ?>">
                <h3 class="h3-card"><i data-lucide="info"></i> Detalhes</h3>
                <dl class="meta-list">
                    <dt>Tipo</dt><dd><?= e($types[$tp] ?? $tp) ?></dd>
                    <dt>Prioridade</dt><dd><span class="badge-priority badge-priority-<?= e($pr) ?>"><?= e($priorities[$pr] ?? $pr) ?></span></dd>
                    <dt>Status</dt><dd><span class="badge-task badge-task-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></dd>
                    <dt>Vencimento</dt><dd class="<?= $overdue ? 'overdue' : '' ?>"><?= e($dash($due)) ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($task['assigned_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Empresa</dt>
                    <dd>
                        <?php if (!empty($task['company_id'])): ?>
                            <a href="<?= e(app_url('/companies/' . (int) $task['company_id'])) ?>" class="link-strong"><?= e($task['company_name'] ?? ('#' . $task['company_id'])) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Contato</dt>
                    <dd>
                        <?php if (!empty($task['contact_id'])): ?>
                            <a href="<?= e(app_url('/contacts/' . (int) $task['contact_id'])) ?>" class="link-strong"><?= e($task['contact_name'] ?? ('#' . $task['contact_id'])) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Oportunidade</dt>
                    <dd>
                        <?php if (!empty($task['opportunity_id'])): ?>
                            <a href="<?= e(app_url('/opportunities/' . (int) $task['opportunity_id'])) ?>" class="link-strong"><?= e($task['opportunity_title'] ?? ('#' . $task['opportunity_id'])) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="check-circle-2"></i> Conclusão</h3>
                <dl class="meta-list">
                    <dt>Concluída em</dt><dd><?= e($dash($task['completed_at'] ?? '')) ?></dd>
                    <dt>Concluída por</dt><dd><?= e($dash($task['completed_by_name'] ?? '')) ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($task['description'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="align-left"></i> Descrição</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($task['description']) ?></p>
            </article>
        <?php endif; ?>

        <?php if (!empty($task['result'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="clipboard-check"></i> Resultado</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($task['result']) ?></p>
            </article>
        <?php endif; ?>

        <?php if ((can('tasks.complete') || can('tasks.edit')) && !$isArchived): ?>
            <article class="card quick-task-actions" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="zap"></i> Ações rápidas</h3>
                <div class="actions-row" style="flex-wrap:wrap;gap:12px;">
                    <?php if (can('tasks.complete') && !$isDone): ?>
                        <form method="post" action="<?= e(app_url('/tasks/' . $tid . '/complete')) ?>" class="inline-form" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <div>
                                <label for="qresult">Resultado (opcional)</label>
                                <input type="text" id="qresult" name="result" maxlength="500" placeholder="Como foi a ação?">
                            </div>
                            <button type="submit" class="btn btn-yellow"><i data-lucide="check"></i> Concluir</button>
                        </form>
                    <?php endif; ?>
                    <?php if (can('tasks.complete') && $isDone): ?>
                        <form method="post" action="<?= e(app_url('/tasks/' . $tid . '/reopen')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-light"><i data-lucide="rotate-ccw"></i> Reabrir</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>

        <div class="notice timeline-placeholder" style="margin-top:20px;">
            <p class="mb-0"><i data-lucide="info"></i> Alertas automáticos por e-mail, calendário e cron serão avaliados em etapa futura. Nesta etapa, o controle é interno pelo painel.</p>
        </div>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($task['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($task['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($task['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($task['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>

        <div class="actions-row" style="margin-top:22px;">
            <?php if (can('tasks.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/tasks/' . $tid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
                <form method="post" action="<?= e(app_url('/tasks/' . $tid . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar esta tarefa? Ela sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>
            <?php if (can('tasks.edit') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/tasks/' . $tid . '/restore')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
