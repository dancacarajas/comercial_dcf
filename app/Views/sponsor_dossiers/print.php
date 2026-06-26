<?php
$dossier = $dossier ?? [];
$model = $model ?? null;
$items = $items ?? [];
$dossierTypes = $dossierTypes ?? [];
$statuses = $statuses ?? [];
$deliveryStatuses = $deliveryStatuses ?? [];
$itemTypes = $itemTypes ?? [];
$itemStatuses = $itemStatuses ?? [];
$evidenceStatuses = $evidenceStatuses ?? [];

$st = (string) ($dossier['status'] ?? '');
$delivery = (string) ($dossier['delivery_status'] ?? '');
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;

$periodLabel = '';
if (!empty($dossier['period_start']) || !empty($dossier['period_end'])) {
    $periodLabel = trim(($dossier['period_start'] ?? '') . ' — ' . ($dossier['period_end'] ?? ''), ' —');
}
?>
<section class="section dossier-print">
    <div class="container">
        <header class="dossier-print-header" style="margin-bottom:24px;">
            <span class="kicker kicker-dark">Dança Carajás · Prestação de contas comercial</span>
            <h1 class="h2-section"><?= e($dossier['title'] ?? 'Dossiê') ?></h1>
            <p class="page-sub">
                <?php if (!empty($dossier['dossier_number'])): ?><strong>Nº <?= e($dossier['dossier_number']) ?></strong> · <?php endif; ?>
                <?= e($dossierTypes[$dossier['dossier_type'] ?? ''] ?? '') ?>
                · <?= e($statuses[$st] ?? $st) ?>
                · <?= e($deliveryStatuses[$delivery] ?? $delivery) ?>
                <?php if ($periodLabel !== ''): ?> · Período <?= e($periodLabel) ?><?php endif; ?>
            </p>
            <p class="page-sub">Patrocinador: <strong><?= e($dossier['sponsor_name'] ?? '—') ?></strong>
                <?php if (!empty($dossier['company_name'])): ?> · Empresa: <?= e($dossier['company_name']) ?><?php endif; ?>
                <?php if (!empty($dossier['contract_title']) || !empty($dossier['contract_number'])): ?> · Contrato: <?= e($dossier['contract_title'] ?? $dossier['contract_number']) ?><?php endif; ?>
            </p>
            <p class="page-sub" style="font-size:0.9em;">Impresso em <?= e(date('d/m/Y H:i')) ?></p>
        </header>

        <div class="dossier-metrics metrics-grid" style="margin-bottom:20px;">
            <div class="dossier-card metric-card">
                <strong>Contratos</strong><br>
                <?= (int) ($dossier['contracts_count'] ?? 0) ?> total · <?= (int) ($dossier['signed_contracts_count'] ?? 0) ?> assinado(s)
            </div>
            <div class="dossier-card metric-card">
                <strong>Contrapartidas</strong><br>
                <?= (int) ($dossier['counterparts_count'] ?? 0) ?> total · <?= (int) ($dossier['counterparts_delivered_count'] ?? 0) ?> entregue(s) · <?= (int) ($dossier['counterparts_pending_count'] ?? 0) ?> pendente(s)
            </div>
            <div class="dossier-card metric-card">
                <strong>Financeiro</strong><br>
                Previsto <?= e(money_br($dossier['financial_planned_amount'] ?? 0)) ?> · Recebido <?= e(money_br($dossier['financial_received_amount'] ?? 0)) ?> · Saldo <?= e(money_br($dossier['financial_remaining_amount'] ?? 0)) ?>
            </div>
            <div class="dossier-card metric-card">
                <strong>Documentos</strong><br>
                <?= (int) ($dossier['documents_count'] ?? 0) ?> vinculado(s) · <?= (int) ($dossier['evidence_documents_count'] ?? 0) ?> evidência(s)
            </div>
        </div>

        <?php
        $textBlocks = [
            'executive_summary' => 'Resumo executivo',
            'commercial_summary' => 'Resumo comercial',
            'counterparts_summary' => 'Contrapartidas',
            'financial_summary' => 'Financeiro',
            'documents_summary' => 'Documentos',
            'pending_notes' => 'Pendências',
            'approval_notes' => 'Aprovação',
            'delivery_notes' => 'Entrega',
            'notes' => 'Observações',
        ];
        foreach ($textBlocks as $key => $label):
            if (empty($dossier[$key])) {
                continue;
            }
        ?>
            <section class="dossier-section" style="margin-bottom:16px;">
                <h2 class="h3-card"><?= e($label) ?></h2>
                <div><?= nl2br(e($dossier[$key])) ?></div>
            </section>
        <?php endforeach; ?>

        <?php if ($items !== []): ?>
            <section class="dossier-section dossier-items" style="margin-top:24px;">
                <h2 class="h3-card">Itens consolidados</h2>
                <table class="dossier-linked-list" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Título</th>
                            <th style="text-align:left;">Tipo</th>
                            <th style="text-align:left;">Status</th>
                            <th style="text-align:left;">Valor</th>
                            <th style="text-align:left;">Referência</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <?= e($it['title'] ?? '') ?>
                                    <?php if (!empty($it['description'])): ?><br><small><?= e($it['description']) ?></small><?php endif; ?>
                                </td>
                                <td><?= e($itemTypes[$it['item_type'] ?? ''] ?? ($it['item_type'] ?? '')) ?></td>
                                <td><?= e($itemStatuses[$it['status'] ?? ''] ?? ($it['status'] ?? '')) ?></td>
                                <td><?= isset($it['amount']) && $it['amount'] !== null ? e(money_br($it['amount'])) : '—' ?></td>
                                <td><?= e($dash($it['date_ref'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <footer class="dossier-section" style="margin-top:32px;padding-top:12px;border-top:1px solid #ddd;font-size:0.85em;">
            <p class="mb-0">
                Responsável: <?= e($dash($dossier['responsible_name'] ?? '')) ?>
                <?php if (!empty($dossier['generated_at'])): ?> · Gerado em <?= e($dossier['generated_at']) ?><?php endif; ?>
                <?php if (!empty($dossier['approved_at'])): ?> · Aprovado em <?= e($dossier['approved_at']) ?><?php endif; ?>
                <?php if (!empty($dossier['delivered_at'])): ?> · Entregue em <?= e($dossier['delivered_at']) ?><?php endif; ?>
            </p>
            <p class="mb-0" style="margin-top:8px;">Documento interno de consolidação comercial. Portal externo do patrocinador não incluído nesta versão.</p>
        </footer>
    </div>
</section>
