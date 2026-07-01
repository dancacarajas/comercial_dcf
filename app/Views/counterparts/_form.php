<?php
$counterpart = $counterpart ?? [];
$old = $old ?? $counterpart;
$isEdit = !empty($counterpart['id']);
$formAction = $isEdit ? app_url('/counterparts/' . (int) $counterpart['id'] . '/update') : app_url('/counterparts');
$errors = $errors ?? [];
$categories = $categories ?? [];
$deliveryTypes = $deliveryTypes ?? [];
$statuses = $statuses ?? [];
$priorities = $priorities ?? [];
$projects = $projects ?? [];
$sponsors = $sponsors ?? [];
$companies = $companies ?? [];
$contacts = $contacts ?? [];
$opportunities = $opportunities ?? [];
$proposals = $proposals ?? [];
$quotas = $quotas ?? [];
$users = $users ?? [];
$documents = $documents ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box counterpart-form" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="incentive_project_id">Projeto incentivado *</label>
            <select id="incentive_project_id" name="incentive_project_id" required>
                <option value="">— Selecione —</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project['id'] ?>" <?= (int) $val('incentive_project_id') === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('incentive_project_id') ?>
        </div>
        <div class="form-grid-full">
            <label for="sponsor_id">Patrocinador / fechamento *</label>
            <select id="sponsor_id" name="sponsor_id" required>
                <option value="">— Selecione —</option>
                <?php foreach ($sponsors as $sp): ?>
                    <option value="<?= (int) $sp['id'] ?>" <?= (int) $val('sponsor_id') === (int) $sp['id'] ? 'selected' : '' ?>>
                        <?= e($sp['sponsor_display_name'] ?? $sp['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('sponsor_id') ?>
        </div>
        <div>
            <label for="company_id">Empresa</label>
            <select id="company_id" name="company_id">
                <option value="">— Opcional —</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= (int) $co['id'] ?>" <?= (int) $val('company_id') === (int) $co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('company_id') ?>
        </div>
        <div>
            <label for="contact_id">Contato</label>
            <select id="contact_id" name="contact_id">
                <option value="">— Opcional —</option>
                <?php foreach ($contacts as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>" <?= (int) $val('contact_id') === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="opportunity_id">Oportunidade</label>
            <select id="opportunity_id" name="opportunity_id">
                <option value="">— Opcional —</option>
                <?php foreach ($opportunities as $op): ?>
                    <option value="<?= (int) $op['id'] ?>" <?= (int) $val('opportunity_id') === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="proposal_id">Proposta</label>
            <select id="proposal_id" name="proposal_id">
                <option value="">— Opcional —</option>
                <?php foreach ($proposals as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= (int) $val('proposal_id') === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="quota_id">Cota</label>
            <select id="quota_id" name="quota_id">
                <option value="">— Opcional —</option>
                <?php foreach ($quotas as $q): ?>
                    <option value="<?= (int) $q['id'] ?>" <?= (int) $val('quota_id') === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($documents !== []): ?>
        <div>
            <label for="evidence_document_id">Documento de evidência</label>
            <select id="evidence_document_id" name="evidence_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('evidence_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('evidence_document_id') ?>
        </div>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="clipboard-list"></i> Dados da contrapartida</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" required maxlength="180">
            <?= $err('title') ?>
        </div>
        <div>
            <label for="category">Categoria *</label>
            <select id="category" name="category" required>
                <?php foreach ($categories as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('category', 'divulgacao_marca') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('category') ?>
        </div>
        <div>
            <label for="delivery_type">Tipo de entrega *</label>
            <select id="delivery_type" name="delivery_type" required>
                <?php foreach ($deliveryTypes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('delivery_type', 'entrega_unica') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('delivery_type') ?>
        </div>
        <div>
            <label for="priority">Prioridade *</label>
            <select id="priority" name="priority" required>
                <?php foreach ($priorities as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('priority', 'media') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('priority') ?>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'planejada') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="responsible_user_id">Responsável</label>
            <select id="responsible_user_id" name="responsible_user_id">
                <option value="">— Opcional —</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) $val('responsible_user_id') === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('responsible_user_id') ?>
        </div>
        <div class="form-grid-full">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="3"><?= e($val('description')) ?></textarea>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="package"></i> Entrega</h3>
    <div class="form-grid">
        <div>
            <label for="promised_quantity">Quantidade prometida</label>
            <input type="text" id="promised_quantity" name="promised_quantity" value="<?= e($val('promised_quantity')) ?>">
            <?= $err('promised_quantity') ?>
        </div>
        <div>
            <label for="delivered_quantity">Quantidade entregue</label>
            <input type="text" id="delivered_quantity" name="delivered_quantity" value="<?= e($val('delivered_quantity')) ?>">
            <?= $err('delivered_quantity') ?>
        </div>
        <div>
            <label for="unit">Unidade</label>
            <input type="text" id="unit" name="unit" value="<?= e($val('unit')) ?>" maxlength="60" placeholder="ex.: posts, banners, credenciais">
        </div>
        <div>
            <label for="due_date">Prazo</label>
            <input type="date" id="due_date" name="due_date" value="<?= e($val('due_date')) ?>">
            <?= $err('due_date') ?>
        </div>
        <div>
            <label for="started_at">Início</label>
            <input type="datetime-local" id="started_at" name="started_at" value="<?= e(str_replace(' ', 'T', substr($val('started_at'), 0, 16))) ?>">
        </div>
        <div>
            <label for="delivered_at">Entrega</label>
            <input type="datetime-local" id="delivered_at" name="delivered_at" value="<?= e(str_replace(' ', 'T', substr($val('delivered_at'), 0, 16))) ?>">
        </div>
        <div>
            <label for="approved_at">Aprovação</label>
            <input type="datetime-local" id="approved_at" name="approved_at" value="<?= e(str_replace(' ', 'T', substr($val('approved_at'), 0, 16))) ?>">
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-check"></i> Evidências</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="evidence_description">Descrição da evidência</label>
            <textarea id="evidence_description" name="evidence_description" rows="2"><?= e($val('evidence_description')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="evidence_url">URL da evidência</label>
            <input type="url" id="evidence_url" name="evidence_url" value="<?= e($val('evidence_url')) ?>" maxlength="255">
            <?= $err('evidence_url') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="message-square"></i> Observações</h3>
    <div class="form-grid">
        <div>
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
        </div>
        <?php if (can('counterparts.edit')): ?>
        <div>
            <label for="internal_notes">Observações internas</label>
            <textarea id="internal_notes" name="internal_notes" rows="3"><?= e($val('internal_notes')) ?></textarea>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= $isEdit ? 'Salvar alterações' : 'Registrar contrapartida' ?></button>
    </div>
</form>
