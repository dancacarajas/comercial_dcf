<?php
$data = $data ?? [];
$errors = $errors ?? [];
$existing = $existing ?? null;
$statuses = $statuses ?? [];
$documentStatuses = $documentStatuses ?? [];
$reviewStatuses = $reviewStatuses ?? [];
$accessStatuses = $accessStatuses ?? [];
$rouanetOptions = $rouanetOptions ?? [];
$users = $users ?? [];
$isEdit = $existing !== null;
$action = $isEdit ? app_url('/collector-applications/' . (int) $existing['id'] . '/update') : app_url('/collector-applications');
$v = static fn (string $k, string $default = ''): string => e((string) ($data[$k] ?? $default));
?>
<form method="post" action="<?= e($action) ?>" class="form-card">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div><label for="name">Nome *</label><input type="text" id="name" name="name" class="input" value="<?= $v('name') ?>" required><?php if (!empty($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?></div>
        <div><label for="company_or_activity">Empresa / atuação</label><input type="text" id="company_or_activity" name="company_or_activity" class="input" value="<?= $v('company_or_activity') ?>"></div>
        <div><label for="document_number">CPF/CNPJ *</label><input type="text" id="document_number" name="document_number" class="input" value="<?= $v('document_number') ?>" required><?php if (!empty($errors['document_number'])): ?><span class="field-error"><?= e($errors['document_number']) ?></span><?php endif; ?></div>
        <div><label for="email">E-mail *</label><input type="email" id="email" name="email" class="input" value="<?= $v('email') ?>" required><?php if (!empty($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?></div>
        <div><label for="phone_whatsapp">WhatsApp *</label><input type="text" id="phone_whatsapp" name="phone_whatsapp" class="input" value="<?= $v('phone_whatsapp') ?>" required></div>
        <div><label for="city_state">Cidade/UF *</label><input type="text" id="city_state" name="city_state" class="input" value="<?= $v('city_state') ?>" required></div>
        <div><label for="rouanet_experience">Experiência Lei Rouanet *</label>
            <select id="rouanet_experience" name="rouanet_experience" class="input" required>
                <option value="">Selecione</option>
                <?php foreach ($rouanetOptions as $k => $label): ?><option value="<?= e($k) ?>" <?= ($data['rouanet_experience'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label for="segments">Segmentos</label><input type="text" id="segments" name="segments" class="input" value="<?= $v('segments') ?>"></div>
        <div class="form-grid-full"><label for="sponsor_network_description">Carteira / patrocinadores</label><textarea id="sponsor_network_description" name="sponsor_network_description" class="input" rows="3"><?= $v('sponsor_network_description') ?></textarea></div>
        <div class="form-grid-full"><label for="message">Mensagem</label><textarea id="message" name="message" class="input" rows="3"><?= $v('message') ?></textarea></div>
        <?php if ($isEdit): ?>
            <div><label for="status">Status</label><select id="status" name="status" class="input"><?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= ($data['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label for="assigned_user_id">Responsável</label><select id="assigned_user_id" name="assigned_user_id" class="input"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) ($data['assigned_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-grid-full"><label for="internal_notes">Notas internas</label><textarea id="internal_notes" name="internal_notes" class="input" rows="3"><?= $v('internal_notes') ?></textarea></div>
        <?php else: ?>
            <div class="form-grid-full"><label class="check-inline"><input type="checkbox" name="consent_contact" value="1" <?= !empty($data['consent_contact']) ? 'checked' : '' ?>> Consentimento de contato (LGPD)</label></div>
        <?php endif; ?>
    </div>
    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow">Salvar</button>
        <a href="<?= e(app_url('/collector-applications')) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
