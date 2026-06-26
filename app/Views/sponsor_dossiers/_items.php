<?php
$dossier = $dossier ?? [];
$items = $items ?? [];
$itemModel = $itemModel ?? null;
$itemTypes = $itemTypes ?? ($itemModel ? $itemModel->getItemTypes() : []);
$itemStatuses = $itemStatuses ?? ($itemModel ? $itemModel->getStatuses() : []);
$evidenceStatuses = $evidenceStatuses ?? ($itemModel ? $itemModel->getEvidenceStatuses() : []);

$did = (int) ($dossier['id'] ?? 0);
$isArchived = !empty($dossier['archived_at']);
$canEdit = can('dossiers.edit') && !$isArchived;

$linkForItem = static function (array $it): string {
    if (!empty($it['contract_id'])) {
        return app_url('/contracts/' . (int) $it['contract_id']);
    }
    if (!empty($it['counterpart_id'])) {
        return app_url('/counterparts/' . (int) $it['counterpart_id']);
    }
    if (!empty($it['financial_entry_id'])) {
        return app_url('/financials/' . (int) $it['financial_entry_id']);
    }
    if (!empty($it['document_id'])) {
        return app_url('/documents/' . (int) $it['document_id']);
    }
    return '';
};
?>
<article class="card dossier-items dossier-section" id="dossier-items">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="list"></i> Itens do dossiê</h3>
            <p class="page-sub"><span class="pill"><?= count($items) ?> item(ns)</span></p>
        </div>
    </div>

    <?php if ($items === []): ?>
        <p class="empty-inline">Nenhum item consolidado. Gere a consolidação ou adicione itens manualmente.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="dossier-linked-list">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Origem</th>
                        <th>Status</th>
                        <th>Evidência</th>
                        <th>Valor</th>
                        <th>Referência</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <?php
                        $iid = (int) ($it['id'] ?? 0);
                        $st = (string) ($it['status'] ?? '');
                        $ev = (string) ($it['evidence_status'] ?? '');
                        $type = (string) ($it['item_type'] ?? '');
                        $itemArchived = !empty($it['archived_at']);
                        $href = $linkForItem($it);
                        ?>
                        <tr class="<?= $itemArchived ? 'dossier-archived-row' : '' ?>">
                            <td>
                                <?php if ($href !== ''): ?>
                                    <a href="<?= e($href) ?>" class="link-strong"><?= e($it['title'] ?? '') ?></a>
                                <?php else: ?>
                                    <strong><?= e($it['title'] ?? '') ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($it['description'])): ?>
                                    <br><small><?= e($it['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="dossier-type"><?= e($itemTypes[$type] ?? $type) ?></span></td>
                            <td><?= e($it['source_module'] ?? 'manual') ?></td>
                            <td><span class="dossier-status badge-dossier-<?= e($st) ?>"><?= e($itemStatuses[$st] ?? $st) ?></span></td>
                            <td><span class="dossier-delivery-status badge-evidence-<?= e($ev) ?>"><?= e($evidenceStatuses[$ev] ?? $ev) ?></span></td>
                            <td class="money-value"><?= isset($it['amount']) && $it['amount'] !== null ? e(money_br($it['amount'])) : '—' ?></td>
                            <td><?= e($it['date_ref'] ?? '—') ?></td>
                            <td style="text-align:right;">
                                <?php if ($canEdit): ?>
                                    <?php if (!$itemArchived): ?>
                                        <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/items/' . $iid . '/archive')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline" data-confirm="Arquivar este item?" title="Arquivar"><i data-lucide="archive"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/items/' . $iid . '/restore')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-light" title="Restaurar"><i data-lucide="rotate-ccw"></i></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
        <details class="dossier-section" style="margin-top:18px;">
            <summary class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Adicionar item manual</summary>
            <form method="post" action="<?= e(app_url('/sponsor-dossiers/' . $did . '/items')) ?>" class="form-box" style="margin-top:12px;">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="form-grid-full">
                        <label for="item_title">Título *</label>
                        <input type="text" id="item_title" name="title" maxlength="180" required placeholder="Título do item">
                    </div>
                    <div>
                        <label for="item_type">Tipo *</label>
                        <select id="item_type" name="item_type" required>
                            <?php foreach ($itemTypes as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $k === 'manual' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="item_status">Status</label>
                        <select id="item_status" name="status">
                            <?php foreach ($itemStatuses as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $k === 'ativo' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="item_evidence_status">Evidência</label>
                        <select id="item_evidence_status" name="evidence_status">
                            <?php foreach ($evidenceStatuses as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $k === 'nao_aplicavel' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="item_amount">Valor (R$)</label>
                        <input type="text" id="item_amount" name="amount" placeholder="0,00">
                    </div>
                    <div>
                        <label for="item_date_ref">Data de referência</label>
                        <input type="date" id="item_date_ref" name="date_ref">
                    </div>
                    <div>
                        <label for="item_sort_order">Ordem</label>
                        <input type="number" id="item_sort_order" name="sort_order" min="0" value="0">
                    </div>
                    <div class="form-grid-full">
                        <label for="item_description">Descrição</label>
                        <textarea id="item_description" name="description" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-yellow">Adicionar item</button>
            </form>
        </details>
    <?php endif; ?>
</article>
