<?php
$application = $application ?? [];
$data = $data ?? [];
$errors = $errors ?? [];
$isEdit = !empty($isEdit);
$types = $types ?? [];
$statuses = $statuses ?? [];
$accountTypes = $accountTypes ?? [];
$pixKeyTypes = $pixKeyTypes ?? [];
$rouanetOptions = $rouanetOptions ?? [];
$missing = $missing ?? [];

$appId = (int) ($application['id'] ?? 0);
$action = $isEdit
    ? app_url('/collector-applications/' . $appId . '/collector/update')
    : app_url('/collector-applications/' . $appId . '/collector');
$v = static fn (string $k, string $default = ''): string => e((string) ($data[$k] ?? $default));
$sel = static fn (string $k, string $val): string => (string) ($data[$k] ?? '') === $val ? 'selected' : '';
$chk = static fn (string $k): string => !empty($data[$k]) ? 'checked' : '';
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Captadores</span>
        <h1 class="h2-section"><?= $isEdit ? 'Editar cadastro do captador' : 'Cadastro do captador' ?></h1>
        <p class="page-sub"><?= e($application['name'] ?? '') ?> · <?= e($application['application_number'] ?? '') ?></p>
    </div>
    <a href="<?= e(app_url('/collector-applications/' . $appId)) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>

<?php if ($missing !== []): ?>
    <div class="alert alert-warning" style="margin-bottom:16px;">
        <strong>Pendências para liberar a geração de documentos:</strong>
        <ul style="margin:8px 0 0;padding-left:18px;">
            <?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="form-card">
    <?= csrf_field() ?>

    <h3 class="h3-card">Identificação</h3>
    <div class="form-grid">
        <div><label for="type">Tipo *</label>
            <select id="type" name="type" class="input" required>
                <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('type', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <?php if (!empty($errors['type'])): ?><span class="field-error"><?= e($errors['type']) ?></span><?php endif; ?>
        </div>
        <div><label for="status">Status operacional</label>
            <select id="status" name="status" class="input">
                <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('status', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label for="name">Nome *</label><input type="text" id="name" name="name" class="input" value="<?= $v('name') ?>" required><?php if (!empty($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?></div>
        <div><label for="document_number">CPF/CNPJ *</label><input type="text" id="document_number" name="document_number" class="input" value="<?= $v('document_number') ?>"></div>
        <div><label for="legal_name">Razão social (PJ)</label><input type="text" id="legal_name" name="legal_name" class="input" value="<?= $v('legal_name') ?>"></div>
        <div><label for="trade_name">Nome fantasia (PJ)</label><input type="text" id="trade_name" name="trade_name" class="input" value="<?= $v('trade_name') ?>"></div>
        <div><label for="state_registration">Inscrição estadual</label><input type="text" id="state_registration" name="state_registration" class="input" value="<?= $v('state_registration') ?>"></div>
        <div><label for="municipal_registration">Inscrição municipal</label><input type="text" id="municipal_registration" name="municipal_registration" class="input" value="<?= $v('municipal_registration') ?>"></div>
        <div><label for="birth_date">Data de nascimento</label><input type="date" id="birth_date" name="birth_date" class="input" value="<?= $v('birth_date') ?>"></div>
        <div><label for="nationality">Nacionalidade</label><input type="text" id="nationality" name="nationality" class="input" value="<?= $v('nationality') ?>"></div>
        <div><label for="marital_status">Estado civil</label><input type="text" id="marital_status" name="marital_status" class="input" value="<?= $v('marital_status') ?>"></div>
        <div><label for="profession">Profissão</label><input type="text" id="profession" name="profession" class="input" value="<?= $v('profession') ?>"></div>
    </div>

    <h3 class="h3-card" style="margin-top:18px;">Contato e endereço</h3>
    <div class="form-grid">
        <div><label for="email">E-mail</label><input type="email" id="email" name="email" class="input" value="<?= $v('email') ?>"><?php if (!empty($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?></div>
        <div><label for="phone_whatsapp">WhatsApp</label><input type="text" id="phone_whatsapp" name="phone_whatsapp" class="input" value="<?= $v('phone_whatsapp') ?>"></div>
        <div><label for="secondary_phone">Telefone secundário</label><input type="text" id="secondary_phone" name="secondary_phone" class="input" value="<?= $v('secondary_phone') ?>"></div>
        <div><label for="address_zipcode">CEP</label><input type="text" id="address_zipcode" name="address_zipcode" class="input" value="<?= $v('address_zipcode') ?>"></div>
        <div><label for="address_street">Logradouro</label><input type="text" id="address_street" name="address_street" class="input" value="<?= $v('address_street') ?>"></div>
        <div><label for="address_number">Número</label><input type="text" id="address_number" name="address_number" class="input" value="<?= $v('address_number') ?>"></div>
        <div><label for="address_complement">Complemento</label><input type="text" id="address_complement" name="address_complement" class="input" value="<?= $v('address_complement') ?>"></div>
        <div><label for="address_district">Bairro</label><input type="text" id="address_district" name="address_district" class="input" value="<?= $v('address_district') ?>"></div>
        <div><label for="address_city">Cidade</label><input type="text" id="address_city" name="address_city" class="input" value="<?= $v('address_city') ?>"></div>
        <div><label for="address_state">UF</label><input type="text" id="address_state" name="address_state" class="input" maxlength="2" value="<?= $v('address_state') ?>"></div>
    </div>

    <h3 class="h3-card" style="margin-top:18px;">Dados bancários</h3>
    <p class="text-sm text-muted-dcx" style="margin:-6px 0 10px;">Preencha os dados bancários completos <strong>ou</strong> uma chave PIX.</p>
    <div class="form-grid">
        <div><label for="bank_name">Banco</label><input type="text" id="bank_name" name="bank_name" class="input" value="<?= $v('bank_name') ?>"></div>
        <div><label for="bank_code">Código do banco</label><input type="text" id="bank_code" name="bank_code" class="input" value="<?= $v('bank_code') ?>"></div>
        <div><label for="agency">Agência</label><input type="text" id="agency" name="agency" class="input" value="<?= $v('agency') ?>"></div>
        <div><label for="account">Conta</label><input type="text" id="account" name="account" class="input" value="<?= $v('account') ?>"></div>
        <div><label for="account_digit">Dígito</label><input type="text" id="account_digit" name="account_digit" class="input" value="<?= $v('account_digit') ?>"></div>
        <div><label for="account_type">Tipo de conta</label>
            <select id="account_type" name="account_type" class="input">
                <option value="">—</option>
                <?php foreach ($accountTypes as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('account_type', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label for="bank_holder_name">Titular da conta</label><input type="text" id="bank_holder_name" name="bank_holder_name" class="input" value="<?= $v('bank_holder_name') ?>"></div>
        <div><label for="bank_holder_document">Documento do titular</label><input type="text" id="bank_holder_document" name="bank_holder_document" class="input" value="<?= $v('bank_holder_document') ?>"></div>
        <div><label for="pix_key">Chave PIX</label><input type="text" id="pix_key" name="pix_key" class="input" value="<?= $v('pix_key') ?>"></div>
        <div><label for="pix_key_type">Tipo da chave PIX</label>
            <select id="pix_key_type" name="pix_key_type" class="input">
                <option value="">—</option>
                <?php foreach ($pixKeyTypes as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('pix_key_type', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>

    <h3 class="h3-card" style="margin-top:18px;">Representante legal (PJ)</h3>
    <div class="form-grid">
        <div><label for="representative_name">Nome do representante</label><input type="text" id="representative_name" name="representative_name" class="input" value="<?= $v('representative_name') ?>"></div>
        <div><label for="representative_document">Documento do representante</label><input type="text" id="representative_document" name="representative_document" class="input" value="<?= $v('representative_document') ?>"></div>
        <div><label for="representative_email">E-mail do representante</label><input type="email" id="representative_email" name="representative_email" class="input" value="<?= $v('representative_email') ?>"></div>
        <div><label for="representative_phone">Telefone do representante</label><input type="text" id="representative_phone" name="representative_phone" class="input" value="<?= $v('representative_phone') ?>"></div>
        <div><label for="representative_role">Cargo do representante</label><input type="text" id="representative_role" name="representative_role" class="input" value="<?= $v('representative_role') ?>"></div>
    </div>

    <h3 class="h3-card" style="margin-top:18px;">Perfil comercial</h3>
    <div class="form-grid">
        <div><label for="rouanet_experience">Experiência Lei Rouanet</label>
            <select id="rouanet_experience" name="rouanet_experience" class="input">
                <option value="">Selecione</option>
                <?php foreach ($rouanetOptions as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('rouanet_experience', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label for="segments">Segmentos</label><input type="text" id="segments" name="segments" class="input" value="<?= $v('segments') ?>"></div>
        <div><label for="territory_scope">Território de atuação</label><input type="text" id="territory_scope" name="territory_scope" class="input" value="<?= $v('territory_scope') ?>"></div>
        <div class="form-grid-full"><label for="sponsor_network_description">Carteira / rede de patrocinadores</label><textarea id="sponsor_network_description" name="sponsor_network_description" class="input" rows="3"><?= $v('sponsor_network_description') ?></textarea></div>
        <div class="form-grid-full"><label for="portfolio_summary">Resumo de portfólio / experiência</label><textarea id="portfolio_summary" name="portfolio_summary" class="input" rows="3"><?= $v('portfolio_summary') ?></textarea></div>
        <div class="form-grid-full"><label class="check-inline"><input type="checkbox" name="has_rouanet_experience" value="1" <?= $chk('has_rouanet_experience') ?>> Possui experiência comprovada com Lei Rouanet</label></div>
    </div>

    <h3 class="h3-card" style="margin-top:18px;">Regras contratuais</h3>
    <div class="form-grid">
        <div><label for="commission_percentage">Percentual de comissão (%)</label><input type="text" id="commission_percentage" name="commission_percentage" class="input" value="<?= $v('commission_percentage') ?>" placeholder="ex.: 10"><?php if (!empty($errors['commission_percentage'])): ?><span class="field-error"><?= e($errors['commission_percentage']) ?></span><?php endif; ?></div>
        <div><label for="contract_start_date">Início da vigência</label><input type="date" id="contract_start_date" name="contract_start_date" class="input" value="<?= $v('contract_start_date') ?>"></div>
        <div><label for="contract_end_date">Término da vigência</label><input type="date" id="contract_end_date" name="contract_end_date" class="input" value="<?= $v('contract_end_date') ?>"></div>
        <div><label for="exclusivity_type">Tipo de exclusividade</label><input type="text" id="exclusivity_type" name="exclusivity_type" class="input" value="<?= $v('exclusivity_type') ?>"></div>
        <div class="form-grid-full"><label for="exclusivity_scope">Escopo de exclusividade</label><input type="text" id="exclusivity_scope" name="exclusivity_scope" class="input" value="<?= $v('exclusivity_scope') ?>"></div>
        <div class="form-grid-full"><label for="commission_payment_rule">Regra de pagamento da comissão</label><input type="text" id="commission_payment_rule" name="commission_payment_rule" class="input" value="<?= $v('commission_payment_rule') ?>" placeholder="ex.: 30 dias após o recebimento do aporte"></div>
        <div class="form-grid-full"><label for="commission_limit_rule">Regra de limite / teto de comissão</label><input type="text" id="commission_limit_rule" name="commission_limit_rule" class="input" value="<?= $v('commission_limit_rule') ?>"></div>
        <div class="form-grid-full"><label class="check-inline"><input type="checkbox" name="confidentiality_required" value="1" <?= $chk('confidentiality_required') ?>> Exige termo de confidencialidade</label></div>
        <div class="form-grid-full"><label for="internal_notes">Notas internas</label><textarea id="internal_notes" name="internal_notes" class="input" rows="3"><?= $v('internal_notes') ?></textarea></div>
    </div>

    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow">Salvar cadastro</button>
        <a href="<?= e(app_url('/collector-applications/' . $appId)) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
</div></section>
