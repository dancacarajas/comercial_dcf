<?php
/**
 * Bloco reutilizável "Tarefas" para empresa/contato/oportunidade.
 *
 * Variáveis esperadas:
 *  $blockTitle  string  Ex.: "Tarefas da empresa"
 *  $tasks       array   Lista resumida de tarefas
 *  $taskSummary array   ['open' => int, 'overdue' => int]
 *  $taskModel   ?Task   Para isOverdue() e listas de rótulos
 *  $createUrl   string  URL contextual para nova tarefa
 *  $allUrl      string  URL da listagem filtrada
 *  $emptyText   string  Mensagem de empty-state
 *
 * Exibir apenas quando o usuário tem tasks.view (responsabilidade do include).
 */
$tasks       = $tasks ?? [];
$taskSummary = $taskSummary ?? ['open' => 0, 'overdue' => 0];
$taskTypes   = $taskModel ? $taskModel->getTypes() : [];
$taskPrio    = $taskModel ? $taskModel->getPriorities() : [];
$taskStat    = $taskModel ? $taskModel->getStatuses() : [];
?>
<article class="card task-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="list-checks"></i> <?= e($blockTitle) ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($taskSummary['open'] ?? 0) ?> aberta(s)</span>
                <span class="pill <?= (int) ($taskSummary['overdue'] ?? 0) > 0 ? 'pill-danger' : '' ?>"><?= (int) ($taskSummary['overdue'] ?? 0) ?> vencida(s)</span>
            </p>
        </div>
        <div class="actions-row">
            <?php if (can('tasks.create')): ?>
                <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Nova tarefa</a>
            <?php endif; ?>
            <a href="<?= e($allUrl) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todas</a>
        </div>
    </div>

    <?php if ($tasks === []): ?>
        <p class="empty-inline"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="task-linked-list">
                <thead>
                    <tr>
                        <th>Título</th><th>Tipo</th><th>Responsável</th>
                        <th>Vencimento</th><th>Prioridade</th><th>Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t): ?>
                        <?php
                        $tid     = (int) $t['id'];
                        $overdue = $taskModel !== null && $taskModel->isOverdue($t);
                        $st      = (string) ($t['status'] ?? '');
                        $pr      = (string) ($t['priority'] ?? '');
                        $tp      = (string) ($t['type'] ?? '');
                        $due     = trim((string) ($t['due_date'] ?? '') . ' ' . substr((string) ($t['due_time'] ?? ''), 0, 5));
                        ?>
                        <tr class="<?= $overdue ? 'task-overdue' : '' ?>">
                            <td><strong><?= e($t['title']) ?></strong><?php if ($overdue): ?> <span class="badge-task badge-task-vencida">Vencida</span><?php endif; ?></td>
                            <td><span class="badge-task-type"><?= e($taskTypes[$tp] ?? $tp) ?></span></td>
                            <td><?= e($t['assigned_name'] ?? '') ?: '—' ?></td>
                            <td class="<?= $overdue ? 'overdue' : '' ?>"><?= $due !== '' ? e($due) : '—' ?></td>
                            <td><span class="badge-priority badge-priority-<?= e($pr) ?>"><?= e($taskPrio[$pr] ?? $pr) ?></span></td>
                            <td><span class="badge-task badge-task-<?= e($st) ?>"><?= e($taskStat[$st] ?? $st) ?></span></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/tasks/' . $tid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
