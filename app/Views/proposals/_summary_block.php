<?php
/**
 * Bloco reutilizável "Propostas" para empresa/contato/oportunidade/cota.
 *
 * Variáveis: $blockTitle, $proposals, $proposalSummary, $proposalModel,
 *            $createUrl, $allUrl, $emptyText
 */
$proposals        = $proposals ?? [];
$proposalSummary  = $proposalSummary ?? ['total' => 0, 'sent' => 0, 'open' => 0, 'total_value' => 0.0];
$proposalTypes    = $proposalModel ? $proposalModel->getTypes() : [];
$proposalStatuses = $proposalModel ? $proposalModel->getStatuses() : [];
?>
<article class="card proposal-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="file-text"></i> <?= e($blockTitle) ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($proposalSummary['total'] ?? 0) ?> cadastrada(s)</span>
                <span class="pill"><?= (int) ($proposalSummary['sent'] ?? 0) ?> enviada(s)</span>
                <span class="pill"><?= (int) ($proposalSummary['open'] ?? 0) ?> em aberto</span>
                <span class="pill money-value"><?= e(money_br($proposalSummary['total_value'] ?? 0, 'R$ 0,00')) ?> proposto</span>
            </p>
        </div>
        <div class="actions-row">
            <?php if (can('proposals.create')): ?>
                <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Nova proposta</a>
            <?php endif; ?>
            <a href="<?= e($allUrl) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todas</a>
        </div>
    </div>

    <?php if ($proposals === []): ?>
        <p class="empty-inline"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="proposal-linked-list">
                <thead>
                    <tr>
                        <th>Título</th><th>Tipo</th><th>Valor</th><th>Versão</th><th>Status</th><th>Validade</th><th>Envio</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proposals as $p): ?>
                        <?php
                        $ppid    = (int) ($p['id'] ?? 0);
                        $pst     = (string) ($p['status'] ?? '');
                        $expired = $proposalModel !== null && $proposalModel->isExpired($p);
                        ?>
                        <tr class="<?= $expired ? 'proposal-expired-row' : '' ?>">
                            <td><strong><?= e($p['title'] ?? '') ?></strong></td>
                            <td><span class="badge-proposal badge-proposal-type"><?= e($proposalTypes[$p['type'] ?? ''] ?? ($p['type'] ?? '')) ?></span></td>
                            <td class="money-value"><?= isset($p['proposed_value']) && $p['proposed_value'] !== null ? e(money_br($p['proposed_value'])) : '—' ?></td>
                            <td><span class="proposal-version">v<?= (int) ($p['version_number'] ?? 1) ?></span></td>
                            <td><span class="badge-proposal proposal-status badge-proposal-<?= e($pst) ?>"><?= e($proposalStatuses[$pst] ?? $pst) ?></span></td>
                            <td class="<?= $expired ? 'overdue' : '' ?>"><?= e($p['valid_until'] ?? '') ?: '—' ?></td>
                            <td><?= !empty($p['sent_at']) ? e(substr((string) $p['sent_at'], 0, 16)) : '—' ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/proposals/' . $ppid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
