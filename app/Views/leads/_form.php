<?php
$formAction  = $formAction ?? app_url('/leads');
$submitLabel = $submitLabel ?? 'Salvar';
$old         = $old ?? [];
$errors      = $errors ?? [];
$statuses    = $statuses ?? [];
$owners      = $owners ?? [];
$val = static fn (string $k, string $d = ''): string => (string) ($old[$k] ?? $d);
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<form method="post" action="<?= e($formAction) ?>" class="form-box" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="col-span-2">
            <label for="name">Nome *</label>
            <input type="text" id="name" name="name" value="<?= e($val('name')) ?>" required maxlength="180">
            <?= $err('name') ?>
        </div>
        <div><label for="company_name">Empresa</label><input type="text" id="company_name" name="company_name" value="<?= e($val('company_name')) ?>"></div>
        <div><label for="role_title">Cargo</label><input type="text" id="role_title" name="role_title" value="<?= e($val('role_title')) ?>"></div>
        <div><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?= e($val('email')) ?>"><?= $err('email') ?></div>
        <div><label for="whatsapp">WhatsApp</label><input type="text" id="whatsapp" name="whatsapp" value="<?= e($val('whatsapp')) ?>"></div>
        <div><label for="city">Cidade</label><input type="text" id="city" name="city" value="<?= e($val('city')) ?>"></div>
        <div><label for="state">UF</label><input type="text" id="state" name="state" value="<?= e($val('state')) ?>" maxlength="2"></div>
        <div><label for="segment">Segmento</label><input type="text" id="segment" name="segment" value="<?= e($val('segment')) ?>"></div>
        <div><label for="origin_page">Origem / página</label><input type="text" id="origin_page" name="origin_page" value="<?= e($val('origin_page')) ?>"></div>
        <div><label for="interest">Interesse</label><input type="text" id="interest" name="interest" value="<?= e($val('interest')) ?>"></div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'novo') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="assigned_user_id">Responsável</label>
            <select id="assigned_user_id" name="assigned_user_id">
                <option value="">—</option>
                <?php foreach ($owners as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (int) $val('assigned_user_id') === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-2">
            <label for="message">Mensagem</label>
            <textarea id="message" name="message" rows="4"><?= e($val('message')) ?></textarea>
        </div>
        <div class="col-span-2">
            <label class="check-inline"><input type="checkbox" name="contact_consent" value="1" <?= !empty($old['contact_consent']) ? 'checked' : '' ?>> Consentimento de contato (LGPD)</label>
        </div>
    </div>
    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
    </div>
</form>
