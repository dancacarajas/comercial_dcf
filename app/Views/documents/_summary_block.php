<?php
/**
 * Bloco reutilizável "Documentos" para empresa/contato/oportunidade/cota/proposta/lead.
 *
 * Variáveis: $blockTitle, $documents, $documentSummary, $documentModel,
 *            $createUrl, $allUrl, $emptyText
 */
$documents       = $documents ?? [];
$documentSummary = $documentSummary ?? ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
$documentModel   = $documentModel ?? null;
$categories      = $documentModel ? $documentModel->getCategories() : [];
$statuses        = $documentModel ? $documentModel->getStatuses() : [];
$accessLevels    = $documentModel ? $documentModel->getAccessLevels() : [];
?>
<article class="card document-summary" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <div>
            <h3 class="h3-card"><i data-lucide="folder"></i> <?= e($blockTitle) ?></h3>
            <p class="page-sub">
                <span class="pill"><?= (int) ($documentSummary['total'] ?? 0) ?> cadastrado(s)</span>
                <span class="pill"><?= (int) ($documentSummary['active'] ?? 0) ?> ativo(s)</span>
                <span class="pill <?= (int) ($documentSummary['expired'] ?? 0) > 0 ? 'pill-danger' : '' ?>"><?= (int) ($documentSummary['expired'] ?? 0) ?> vencido(s)</span>
                <span class="pill"><?= (int) ($documentSummary['expiring_soon'] ?? 0) ?> vencendo (30d)</span>
            </p>
        </div>
        <div class="actions-row">
            <?php if (can('documents.create')): ?>
                <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Novo documento</a>
            <?php endif; ?>
            <a href="<?= e($allUrl) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-right"></i> Ver todos</a>
        </div>
    </div>

    <?php if ($documents === []): ?>
        <p class="empty-inline"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="document-linked-list">
                <thead>
                    <tr>
                        <th>Título</th><th>Categoria</th><th>Status</th><th>Acesso</th><th>Versão</th><th>Validade</th><th>Tamanho</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <?php
                        $did     = (int) ($doc['id'] ?? 0);
                        $st      = (string) ($doc['status'] ?? '');
                        $cat     = (string) ($doc['category'] ?? '');
                        $access  = (string) ($doc['access_level'] ?? '');
                        $expired = $documentModel !== null && $documentModel->isExpired($doc);
                        ?>
                        <tr class="<?= $expired ? 'document-expired-row' : '' ?>">
                            <td><strong><?= e($doc['title'] ?? '') ?></strong></td>
                            <td><span class="badge-document document-category badge-document-<?= e($cat) ?>"><?= e($categories[$cat] ?? $cat) ?></span></td>
                            <td><span class="badge-document document-status badge-document-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td><span class="badge-document document-access badge-access-<?= e($access) ?>"><?= e($accessLevels[$access] ?? $access) ?></span></td>
                            <td>v<?= (int) ($doc['version_number'] ?? 1) ?></td>
                            <td class="<?= $expired ? 'overdue' : '' ?>"><?= e($doc['valid_until'] ?? '') ?: '—' ?></td>
                            <td><?= $documentModel ? e($documentModel->formatSize($doc['size_bytes'] ?? 0)) : '—' ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/documents/' . $did)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
