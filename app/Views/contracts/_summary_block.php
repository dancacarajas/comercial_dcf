<?php
/**
 * Bloco reutilizável "Contratos".
 *
 * Variáveis: $blockTitle, $contracts, $contractSummary, $contractModel,
 *            $createUrl, $allUrl, $emptyText
 */
$contracts = $contracts ?? [];
$contractSummary = $contractSummary ?? ['total' => 0, 'signed' => 0, 'awaiting_signature' => 0, 'vigente' => 0, 'expired' => 0, 'formalized_total' => 0.0];
$contractModel = $contractModel ?? null;
$contractTypes = $contractModel ? $contractModel->getContractTypes() : [];
$statuses = $contractModel ? $contractModel->getStatuses() : [];
$signatureStatuses = $contractModel ? $contractModel->getSignatureStatuses() : [];
?>
<article class="card contract-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="file-signature"></i> <?= e($blockTitle) ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($contractSummary['total'] ?? 0) ?> cadastrado(s)</span>
                <span class="pill"><?= (int) ($contractSummary['signed'] ?? 0) ?> assinado(s)</span>
                <span class="pill"><?= (int) ($contractSummary['awaiting_signature'] ?? 0) ?> aguardando assinatura</span>
                <span class="pill"><?= (int) ($contractSummary['vigente'] ?? 0) ?> vigente(s)</span>
                <span class="pill <?= (int) ($contractSummary['expired'] ?? 0) > 0 ? 'pill-danger' : '' ?>"><?= (int) ($contractSummary['expired'] ?? 0) ?> vencido(s)</span>
                <span class="pill contract-value money-value"><?= e(money_br($contractSummary['formalized_total'] ?? 0, 'R$ 0,00')) ?> formalizado</span>
            </p>
        </div>
        <div class="actions-row contract-actions">
            <?php if (can('contracts.create')): ?>
                <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Novo contrato</a>
            <?php endif; ?>
            <a href="<?= e($allUrl) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todos</a>
        </div>
    </div>

    <?php if ($contracts === []): ?>
        <p class="empty-inline"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="contract-linked-list">
                <thead>
                    <tr><th>Título</th><th>Tipo</th><th>Status</th><th>Assinatura</th><th>Vigência</th><th>Valor</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $ct): ?>
                        <?php
                        $cid = (int) ($ct['id'] ?? 0);
                        $st = (string) ($ct['status'] ?? '');
                        $sig = (string) ($ct['signature_status'] ?? '');
                        $expired = $contractModel && $contractModel->isExpired($ct);
                        ?>
                        <tr class="<?= $expired ? 'contract-expired-row' : '' ?>">
                            <td><strong><?= e($ct['title'] ?? '') ?></strong></td>
                            <td><span class="contract-type"><?= e($contractTypes[$ct['contract_type'] ?? ''] ?? '') ?></span></td>
                            <td><span class="contract-status badge-ct-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td><span class="contract-signature-status badge-sig-<?= e($sig) ?>"><?= e($signatureStatuses[$sig] ?? $sig) ?></span></td>
                            <td class="<?= $expired ? 'overdue' : '' ?>"><?= e($ct['start_date'] ?? '—') ?> — <?= e($ct['end_date'] ?? '—') ?></td>
                            <td class="contract-value money-value"><?= isset($ct['formalized_value']) && $ct['formalized_value'] !== null ? e(money_br($ct['formalized_value'])) : '—' ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/contracts/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
