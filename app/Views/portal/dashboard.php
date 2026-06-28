<?php
/** @var array $collector @var array $assignments @var array $deals @var array $dealStatuses @var array $assignStatuses @var array $assignTypes */
$deals = $deals ?? [];
$assignments = $assignments ?? [];
$dealStatuses = $dealStatuses ?? [];
$assignStatuses = $assignStatuses ?? [];
$assignTypes = $assignTypes ?? [];
?>
<div class="pt-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <div>
            <h2 style="margin-bottom:.2rem">Minha carteira</h2>
            <p class="pt-muted" style="margin:0">Empresas e prospects que você captou. Cadastre novas empresas para ampliar sua carteira.</p>
        </div>
        <a class="pt-btn" href="<?= e(app_url('/portal/prospects/create')) ?>"><i data-lucide="plus"></i> Novo prospect</a>
    </div>
</div>

<div class="pt-card">
    <h3>Captações (<?= count($deals) ?>)</h3>
    <?php if ($deals === []): ?>
        <div class="pt-empty">Você ainda não tem captações. Comece cadastrando um <a href="<?= e(app_url('/portal/prospects/create')) ?>">novo prospect</a>.</div>
    <?php else: ?>
        <table class="pt-table">
            <thead><tr><th>Empresa</th><th>Status</th><th>Origem</th><th>Atualizado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($deals as $d): ?>
                <tr>
                    <td><?= e($d['company_name'] ?? '—') ?></td>
                    <td><span class="pt-badge"><?= e($dealStatuses[$d['deal_status'] ?? ''] ?? ($d['deal_status'] ?? '—')) ?></span></td>
                    <td><?= e(($d['source'] ?? '') === 'portal_captador' ? 'Portal do captador' : ($d['source'] ?? '—')) ?></td>
                    <td class="pt-muted"><?= e(!empty($d['updated_at']) ? date('d/m/Y', strtotime((string) $d['updated_at'])) : (!empty($d['created_at']) ? date('d/m/Y', strtotime((string) $d['created_at'])) : '—')) ?></td>
                    <td><a class="pt-btn secondary" href="<?= e(app_url('/portal/deals/' . (int) $d['id'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="pt-card">
    <h3>Minhas atribuições (<?= count($assignments) ?>)</h3>
    <?php if ($assignments === []): ?>
        <div class="pt-empty">Nenhuma atribuição registrada.</div>
    <?php else: ?>
        <table class="pt-table">
            <thead><tr><th>Empresa</th><th>Tipo</th><th>Status</th><th>Exclusiva até</th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= e($a['company_name'] ?? '—') ?></td>
                    <td><?= e($assignTypes[$a['assignment_type'] ?? ''] ?? ($a['assignment_type'] ?? '—')) ?></td>
                    <td><span class="pt-badge"><?= e($assignStatuses[$a['status'] ?? ''] ?? ($a['status'] ?? '—')) ?></span></td>
                    <td class="pt-muted"><?= e(!empty($a['exclusive_until']) ? date('d/m/Y', strtotime((string) $a['exclusive_until'])) : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
