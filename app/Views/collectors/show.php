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
</div></section>
