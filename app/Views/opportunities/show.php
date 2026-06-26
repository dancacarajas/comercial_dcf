<?php
/**
 * Visualização de oportunidade + bloco de ação rápida de status.
 *
 * Variáveis: $opportunity, $statusLabels, $statusProbabilities,
 * $quotaInterests, $sources, $urgencyLevels, $lostReasons
 */
$opportunity         = $opportunity ?? [];
$statusLabels        = $statusLabels ?? [];
$statusProbabilities = $statusProbabilities ?? [];
$urgencyLevels       = $urgencyLevels ?? [];
$lostReasons         = $lostReasons ?? [];

$oid        = (int) ($opportunity['id'] ?? 0);
$companyId  = (int) ($opportunity['company_id'] ?? 0);
$contactId  = (int) ($opportunity['contact_id'] ?? 0);
$isArchived = !empty($opportunity['archived_at']);
$st         = (string) ($opportunity['status'] ?? '');
$prob       = (int) ($opportunity['probability'] ?? 0);
$urgency    = (string) ($opportunity['urgency_level'] ?? 'normal');

$probClass = static function (int $p): string {
    if ($p >= 90) { return 'top'; }
    if ($p >= 60) { return 'high'; }
    if ($p >= 25) { return 'mid'; }
    return 'low';
};
$dash    = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
$probMap = htmlspecialchars(json_encode($statusProbabilities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Oportunidade</span>
                <h1 class="h2-section"><?= e($opportunity['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-status badge-status-<?= e($st) ?>"><?= e($statusLabels[$st] ?? $st) ?></span>
                    <span class="badge-probability badge-probability-<?= $probClass($prob) ?>"><?= $prob ?>%</span>
                    <span class="badge-urgency badge-urgency-<?= e($urgency) ?>"><?= e($urgencyLevels[$urgency] ?? $urgency) ?></span>
                    <?php if ($isArchived): ?>
                        <span class="badge-status badge-status-arquivado">Arquivada em <?= e($opportunity['archived_at']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/opportunities')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php if (!empty($opportunity['company_archived_at'])): ?>
            <div class="notice notice-warn" style="margin-bottom:18px;">
                <p class="mb-0"><i data-lucide="alert-triangle"></i> A empresa vinculada a esta oportunidade está <strong>arquivada</strong>.</p>
            </div>
        <?php endif; ?>

        <div class="detail-grid">
            <article class="card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculo</h3>
                <dl class="meta-list">
                    <dt>Empresa</dt>
                    <dd><a href="<?= e(app_url('/companies/' . $companyId)) ?>" class="link-strong"><?= e($opportunity['company_name'] ?? ('#' . $companyId)) ?></a></dd>
                    <dt>Contato principal</dt>
                    <dd>
                        <?php if ($contactId > 0): ?>
                            <a href="<?= e(app_url('/contacts/' . $contactId)) ?>" class="link-strong"><?= e($opportunity['contact_name'] ?? ('#' . $contactId)) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                </dl>
            </article>

            <?php
            $quotaId    = (int) ($opportunity['quota_id'] ?? 0);
            $quotaName  = trim((string) ($opportunity['quota_name'] ?? ''));
            $reserved   = (string) ($opportunity['quota_reserved_until'] ?? '');
            ?>
            <article class="card">
                <h3 class="h3-card"><i data-lucide="banknote"></i> Negociação</h3>
                <dl class="meta-list">
                    <dt>Cota de patrocínio</dt>
                    <dd>
                        <?php if ($quotaId > 0 && $quotaName !== ''): ?>
                            <a href="<?= e(app_url('/quotas/' . $quotaId)) ?>" class="link-strong"><span class="badge-quota"><?= e($quotaName) ?></span></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Interesse de cota (legado)</dt><dd><?= e($dash($opportunity['quota_interest'] ?? '')) ?></dd>
                    <?php if ($st === 'reserva_de_cota' || $reserved !== ''): ?>
                        <dt>Reserva válida até</dt><dd><?= e($dash($reserved)) ?></dd>
                    <?php endif; ?>
                    <dt>Valor estimado</dt><dd class="money-value"><?= e(money_br($opportunity['estimated_value'] ?? null)) ?></dd>
                    <dt>Probabilidade</dt><dd><span class="badge-probability badge-probability-<?= $probClass($prob) ?>"><?= $prob ?>%</span></dd>
                    <dt>Origem</dt><dd><?= e($opportunity['source'] ? ucfirst((string) $opportunity['source']) : '—') ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($opportunity['owner_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="calendar-clock"></i> Agenda</h3>
                <dl class="meta-list">
                    <dt>Abertura</dt><dd><?= e($dash($opportunity['opened_at'] ?? '')) ?></dd>
                    <dt>Última movimentação</dt><dd><?= e($dash($opportunity['last_interaction_at'] ?? '')) ?></dd>
                    <dt>Próxima ação</dt><dd><?= e($dash($opportunity['next_action_at'] ?? '')) ?></dd>
                </dl>
            </article>

            <?php if ($st === 'perdido' || !empty($opportunity['lost_reason'])): ?>
                <article class="card">
                    <h3 class="h3-card"><i data-lucide="circle-slash"></i> Resultado</h3>
                    <dl class="meta-list">
                        <dt>Motivo de perda</dt><dd><?= e($opportunity['lost_reason'] ? ucfirst((string) $opportunity['lost_reason']) : '—') ?></dd>
                    </dl>
                </article>
            <?php endif; ?>
        </div>

        <?php if (!empty($opportunity['notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="sticky-note"></i> Observações</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($opportunity['notes']) ?></p>
            </article>
        <?php endif; ?>

        <?php if (can('opportunities.edit') && !$isArchived): ?>
            <article class="card quick-status-form" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="git-branch"></i> Ação rápida de status</h3>
                <form method="post" action="<?= e(app_url('/opportunities/' . $oid . '/status')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div>
                            <label for="qs_status">Status</label>
                            <select id="qs_status" name="status" data-prob-map="<?= $probMap ?>">
                                <?php foreach ($statusLabels as $k => $label): ?>
                                    <option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="qs_prob">Probabilidade (%)</label>
                            <input type="number" id="qs_prob" name="probability" min="0" max="100" value="<?= $prob ?>">
                        </div>
                        <div>
                            <label for="qs_next">Próxima ação</label>
                            <input type="datetime-local" id="qs_next" name="next_action_at" value="<?= e($opportunity['next_action_at'] ? date('Y-m-d\TH:i', strtotime((string) $opportunity['next_action_at'])) : '') ?>">
                        </div>
                        <div>
                            <label for="qs_lost">Motivo de perda</label>
                            <select id="qs_lost" name="lost_reason">
                                <option value="">— Não se aplica —</option>
                                <?php foreach ($lostReasons as $lr): ?>
                                    <option value="<?= e($lr) ?>" <?= ($opportunity['lost_reason'] ?? '') === $lr ? 'selected' : '' ?>><?= e(ucfirst($lr)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:12px;">
                        <label for="qs_note">Observação (opcional)</label>
                        <input type="text" id="qs_note" name="notes" maxlength="500" placeholder="Acrescentar uma nota ao histórico simples">
                    </div>
                    <div class="actions-row" style="margin-top:14px;">
                        <button type="submit" class="btn btn-yellow"><i data-lucide="refresh-cw"></i> Atualizar status</button>
                    </div>
                </form>
            </article>
        <?php endif; ?>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($opportunity['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($opportunity['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($opportunity['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($opportunity['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>

        <?php if (can('tasks.view')): ?>
            <?php
            $blockTitle = 'Tarefas da oportunidade';
            $createUrl  = app_url('/tasks/create?opportunity_id=' . $oid);
            $allUrl     = app_url('/tasks?opportunity_id=' . $oid);
            $emptyText  = 'Nenhuma tarefa cadastrada para esta oportunidade ainda.';
            require dirname(__DIR__) . '/tasks/_summary_block.php';
            ?>
        <?php endif; ?>

        <div class="notice timeline-placeholder" style="margin-top:20px;">
            <p class="mb-0"><i data-lucide="info"></i> Propostas, documentos e histórico comercial detalhado serão vinculados a esta oportunidade nas próximas etapas.</p>
        </div>

        <div class="actions-row" style="margin-top:22px;">
            <?php if (can('opportunities.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/opportunities/' . $oid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
                <form method="post" action="<?= e(app_url('/opportunities/' . $oid . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar esta oportunidade? Ela sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>

            <?php if (can('opportunities.edit') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/opportunities/' . $oid . '/restore')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    var s = document.getElementById('qs_status');
    var p = document.getElementById('qs_prob');
    if (!s || !p) { return; }
    var map = {};
    try { map = JSON.parse(s.getAttribute('data-prob-map') || '{}'); } catch (e) { map = {}; }
    s.addEventListener('change', function () {
        if (s.value === 'fechado') { p.value = 100; return; }
        if (s.value === 'perdido') { p.value = 0; return; }
        if (map[s.value] !== undefined) { p.value = map[s.value]; }
    });
})();
</script>
