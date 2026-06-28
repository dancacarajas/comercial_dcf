<?php
/** @var array $data @var array $errors @var array $segments @var array $states */
$data = $data ?? [];
$errors = $errors ?? [];
$segments = $segments ?? [];
$states = $states ?? [];
$val = static fn (string $k): string => e((string) ($data[$k] ?? ''));
$err = static fn (string $k): string => isset($errors[$k]) ? '<span class="err">' . e((string) $errors[$k]) . '</span>' : '';
?>
<div class="pt-card">
    <h2>Novo prospect</h2>
    <p class="pt-muted">Cadastre uma empresa/prospect para a sua carteira. O sistema verifica automaticamente duplicidade e conflito antes de adicionar.</p>

    <form method="post" action="<?= e(app_url('/portal/prospects')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="pt-field">
            <label for="name">Nome da empresa / prospect *</label>
            <input type="text" id="name" name="name" value="<?= $val('name') ?>" required maxlength="180">
            <?= $err('name') ?>
        </div>
        <div class="pt-grid">
            <div class="pt-field">
                <label for="cnpj">CNPJ</label>
                <input type="text" id="cnpj" name="cnpj" value="<?= $val('cnpj') ?>" placeholder="00.000.000/0000-00" maxlength="20">
                <?= $err('cnpj') ?>
            </div>
            <div class="pt-field">
                <label for="segment">Segmento</label>
                <select id="segment" name="segment">
                    <option value="">— selecione —</option>
                    <?php foreach ($segments as $s): ?>
                        <option value="<?= e($s) ?>"<?= (string) ($data['segment'] ?? '') === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="pt-grid">
            <div class="pt-field">
                <label for="city">Cidade</label>
                <input type="text" id="city" name="city" value="<?= $val('city') ?>" maxlength="120">
            </div>
            <div class="pt-field">
                <label for="state">UF</label>
                <select id="state" name="state">
                    <option value="">—</option>
                    <?php foreach ($states as $code => $label): ?>
                        <?php $code = is_int($code) ? $label : $code; ?>
                        <option value="<?= e((string) $code) ?>"<?= (string) ($data['state'] ?? '') === (string) $code ? ' selected' : '' ?>><?= e((string) $code) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('state') ?>
            </div>
        </div>
        <div class="pt-grid">
            <div class="pt-field">
                <label for="email">E-mail de contato</label>
                <input type="email" id="email" name="email" value="<?= $val('email') ?>" maxlength="180">
                <?= $err('email') ?>
            </div>
            <div class="pt-field">
                <label for="phone">Telefone</label>
                <input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" maxlength="40">
            </div>
        </div>
        <div class="pt-field">
            <label for="notes">Observações iniciais</label>
            <textarea id="notes" name="notes" rows="3" maxlength="1000"><?= $val('notes') ?></textarea>
        </div>
        <div class="pt-actions">
            <button type="submit" class="pt-btn"><i data-lucide="check"></i> Adicionar à minha carteira</button>
            <a class="pt-btn secondary" href="<?= e(app_url('/portal')) ?>">Cancelar</a>
        </div>
    </form>
</div>
