<?php
$collector = $collector ?? [];
$types = $types ?? [];
$statuses = $statuses ?? [];
$registrationStatuses = $registrationStatuses ?? [];
$missing = $missing ?? [];

$appId = (int) ($collector['collector_application_id'] ?? 0);
$regStatus = (string) ($collector['registration_status'] ?? 'incompleto');
$regBadge = match ($regStatus) {
    'validado' => 'collector-doc-badge collector-doc-badge--aprovado',
    'completo' => 'collector-doc-badge collector-doc-badge--enviado',
    default    => 'collector-doc-badge collector-doc-badge--pendente',
};
$row = static fn (string $label, ?string $value): string => '<dt>' . e($label) . '</dt><dd>' . e($value !== null && $value !== '' ? $value : '—') . '</dd>';

$assignments = $assignments ?? [];
$assignmentTypes = $assignmentTypes ?? [];
$assignmentStatuses = $assignmentStatuses ?? [];
$deals = $deals ?? [];
$dealStatuses = $dealStatuses ?? [];
$collectorId = (int) ($collector['id'] ?? 0);
$activeStatuses = ['solicitada', 'autorizada'];
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Captadores credenciados</span>
        <h1 class="h2-section"><?= e($collector['name'] ?? 'Captador') ?></h1>
        <p class="page-sub"><?= e($collector['collector_code'] ?? '') ?> · <?= e($registrationStatuses[$regStatus] ?? $regStatus) ?></p>
    </div>
    <div class="actions-row">
        <?php if ($appId > 0): ?>
            <a href="<?= e(app_url('/collector-applications/' . $appId)) ?>" class="btn btn-sm btn-outline">Ver candidatura</a>
            <?php if (can('collectors.manage')): ?>
                <a href="<?= e(app_url('/collector-applications/' . $appId . '/collector/edit')) ?>" class="btn btn-sm btn-yellow">Editar cadastro</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= e(app_url('/collectors')) ?>" class="btn btn-sm btn-outline">Voltar</a>
    </div>
</div>

<?php if ($missing !== []): ?>
    <div class="alert alert-warning" style="margin-bottom:16px;">
        <strong>Cadastro incompleto.</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
            <?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <div class="card">
        <h3 class="h3-card">Identificação</h3>
        <dl class="detail-list">
            <?= $row('Tipo', $types[$collector['type'] ?? ''] ?? '') ?>
            <?= $row('Status', $statuses[$collector['status'] ?? ''] ?? '') ?>
            <?= $row('Cadastro', $registrationStatuses[$regStatus] ?? '') ?>
            <?= $row('Documento', (string) ($collector['document_number'] ?? '')) ?>
            <?= $row('Razão social', (string) ($collector['legal_name'] ?? '')) ?>
            <?= $row('Nome fantasia', (string) ($collector['trade_name'] ?? '')) ?>
            <?= $row('Profissão', (string) ($collector['profession'] ?? '')) ?>
            <?= $row('E-mail', (string) ($collector['email'] ?? '')) ?>
            <?= $row('WhatsApp', (string) ($collector['phone_whatsapp'] ?? '')) ?>
        </dl>
    </div>
    <div class="card">
        <h3 class="h3-card">Endereço e banco</h3>
        <dl class="detail-list">
            <?= $row('Endereço', trim(((string) ($collector['address_street'] ?? '')) . ' ' . (string) ($collector['address_number'] ?? ''))) ?>
            <?= $row('Bairro', (string) ($collector['address_district'] ?? '')) ?>
            <?= $row('Cidade/UF', trim(((string) ($collector['address_city'] ?? '')) . (($collector['address_state'] ?? '') !== '' ? '/' . (string) $collector['address_state'] : ''))) ?>
            <?= $row('CEP', (string) ($collector['address_zipcode'] ?? '')) ?>
            <?= $row('Banco', (string) ($collector['bank_name'] ?? '')) ?>
            <?= $row('Agência/Conta', trim(((string) ($collector['agency'] ?? '')) . ' / ' . (string) ($collector['account'] ?? ''))) ?>
            <?= $row('Titular', (string) ($collector['bank_holder_name'] ?? '')) ?>
            <?= $row('PIX', (string) ($collector['pix_key'] ?? '')) ?>
        </dl>
    </div>
</div>

<div class="detail-grid" style="margin-top:18px;">
    <div class="card">
        <h3 class="h3-card">Regras comerciais</h3>
        <dl class="detail-list">
            <?= $row('Comissão (%)', $collector['commission_percentage'] !== null && $collector['commission_percentage'] !== '' ? rtrim(rtrim(number_format((float) $collector['commission_percentage'], 3, ',', '.'), '0'), ',') : '') ?>
            <?= $row('Pagamento', (string) ($collector['commission_payment_rule'] ?? '')) ?>
            <?= $row('Limite/teto', (string) ($collector['commission_limit_rule'] ?? '')) ?>
            <?= $row('Vigência', trim(((string) ($collector['contract_start_date'] ?? '')) . ' a ' . (string) ($collector['contract_end_date'] ?? ''))) ?>
            <?= $row('Exclusividade', (string) ($collector['exclusivity_type'] ?? '')) ?>
        </dl>
    </div>
    <div class="card">
        <h3 class="h3-card">Representante e perfil</h3>
        <dl class="detail-list">
            <?= $row('Representante', (string) ($collector['representative_name'] ?? '')) ?>
            <?= $row('Documento rep.', (string) ($collector['representative_document'] ?? '')) ?>
            <?= $row('Cargo rep.', (string) ($collector['representative_role'] ?? '')) ?>
            <?= $row('Segmentos', (string) ($collector['segments'] ?? '')) ?>
            <?= $row('Território', (string) ($collector['territory_scope'] ?? '')) ?>
        </dl>
    </div>
</div>

<!-- Etapa 18C Fase 2 — Atribuição comercial -->
<div class="card" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <h3 class="h3-card" style="margin:0;">Empresas autorizadas / atribuições</h3>
        <?php if (can('collector_assignments.manage')): ?>
            <a href="<?= e(app_url('/collectors/' . $collectorId . '/assignments/create')) ?>" class="btn btn-sm btn-yellow">Autorizar abordagem</a>
        <?php endif; ?>
    </div>
    <?php if ($assignments === []): ?>
        <p class="muted">Nenhuma empresa atribuída a este captador.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Empresa</th><th>Tipo</th><th>Status</th><th>Exclusiva até</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <?php $st = (string) ($a['status'] ?? ''); ?>
                <tr>
                    <td><?= e($a['company_name'] ?? ('#' . (int) $a['company_id'])) ?></td>
                    <td><?= e($assignmentTypes[$a['assignment_type'] ?? ''] ?? $a['assignment_type'] ?? '') ?></td>
                    <td><?= e($assignmentStatuses[$st] ?? $st) ?></td>
                    <td><?= e((string) ($a['exclusive_until'] ?? '') !== '' ? (string) $a['exclusive_until'] : '—') ?></td>
                    <td>
                        <?php if (can('collector_assignments.manage')): ?>
                            <?php if ($st === 'solicitada'): ?>
                                <form method="post" action="<?= e(app_url('/collector-assignments/' . (int) $a['id'] . '/authorize')) ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-xs btn-yellow">Autorizar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($st === 'autorizada'): ?>
                                <form method="post" action="<?= e(app_url('/collector-assignments/' . (int) $a['id'] . '/convert')) ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-xs btn-outline">Converter em oportunidade</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($st, $activeStatuses, true)): ?>
                                <form method="post" action="<?= e(app_url('/collector-assignments/' . (int) $a['id'] . '/cancel')) ?>" style="display:inline;" onsubmit="return confirm('Cancelar esta atribuição?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-xs btn-outline">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<!-- Etapa 18C Fase 2 — Captações rastreadas (deals) -->
<div class="card" style="margin-top:18px;">
    <div class="page-head" style="margin-bottom:12px;">
        <h3 class="h3-card" style="margin:0;">Captações rastreadas</h3>
        <?php if (can('collector_deals.manage')): ?>
            <a href="<?= e(app_url('/collectors/' . $collectorId . '/deals/create')) ?>" class="btn btn-sm btn-yellow">Nova captação</a>
        <?php endif; ?>
    </div>
    <?php if ($deals === []): ?>
        <p class="muted">Nenhuma captação rastreada para este captador.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Empresa</th><th>Status</th><th>Oportunidade</th><th>Proposta</th><th>Patrocinador</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($deals as $d): ?>
                <?php $ds = (string) ($d['deal_status'] ?? ''); ?>
                <tr>
                    <td><?= e($d['company_name'] ?? ('#' . (int) $d['company_id'])) ?></td>
                    <td><?= e($dealStatuses[$ds] ?? $ds) ?></td>
                    <td><?php if (!empty($d['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $d['opportunity_id'])) ?>"><?= e($d['opportunity_title'] ?? ('#' . (int) $d['opportunity_id'])) ?></a><?php else: ?>—<?php endif; ?></td>
                    <td><?php if (!empty($d['proposal_id'])): ?><a href="<?= e(app_url('/proposals/' . (int) $d['proposal_id'])) ?>"><?= e($d['proposal_title'] ?? ('#' . (int) $d['proposal_id'])) ?></a><?php else: ?>—<?php endif; ?></td>
                    <td><?php if (!empty($d['sponsor_id'])): ?><a href="<?= e(app_url('/sponsors/' . (int) $d['sponsor_id'])) ?>"><?= e($d['sponsor_name'] ?? ('#' . (int) $d['sponsor_id'])) ?></a><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <?php if (can('collector_deals.manage')): ?>
                            <a href="<?= e(app_url('/collector-deals/' . (int) $d['id'] . '/edit')) ?>" class="btn btn-xs btn-outline">Editar</a>
                            <?php if (($d['attribution_type'] ?? '') === 'compartilhada'): ?>
                                <a href="<?= e(app_url('/collector-deals/' . (int) $d['id'] . '/shares')) ?>" class="btn btn-xs btn-outline">Rateio</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
</div></section>
