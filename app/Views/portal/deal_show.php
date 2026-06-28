<?php
/** @var array $deal @var array $contacts @var array $dealStatuses */
$deal = $deal ?? [];
$contacts = $contacts ?? [];
$dealStatuses = $dealStatuses ?? [];
$companyId = (int) ($deal['company_id'] ?? 0);
$statusLabel = $dealStatuses[$deal['deal_status'] ?? ''] ?? ($deal['deal_status'] ?? '—');
$sourceLabel = ($deal['source'] ?? '') === 'portal_captador' ? 'Portal do captador' : ($deal['source'] ?? '—');
$notes = trim((string) ($deal['notes'] ?? ''));
?>
<div class="pt-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div>
            <h2 style="margin-bottom:.25rem"><?= e($deal['company_name'] ?? 'Captação') ?></h2>
            <span class="pt-badge"><?= e($statusLabel) ?></span>
            <span class="pt-muted" style="margin-left:.5rem">Origem: <?= e($sourceLabel) ?></span>
        </div>
        <a class="pt-btn secondary" href="<?= e(app_url('/portal')) ?>"><i data-lucide="arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="pt-card">
    <h3>Andamento</h3>
    <?php if ($notes === ''): ?>
        <p class="pt-muted">Nenhuma observação registrada ainda.</p>
    <?php else: ?>
        <pre style="white-space:pre-wrap;font-family:inherit;background:#f7f7fc;border:1px solid #ececf5;border-radius:.6rem;padding:.8rem;margin:0 0 1rem"><?= e($notes) ?></pre>
    <?php endif; ?>
    <form method="post" action="<?= e(app_url('/portal/deals/' . (int) ($deal['id'] ?? 0) . '/note')) ?>">
        <?= csrf_field() ?>
        <div class="pt-field">
            <label for="note">Nova observação / andamento</label>
            <textarea id="note" name="note" rows="3" required maxlength="1000" placeholder="Ex.: Primeiro contato realizado, aguardando retorno do setor de marketing."></textarea>
        </div>
        <button type="submit" class="pt-btn"><i data-lucide="message-square-plus"></i> Registrar andamento</button>
    </form>
</div>

<div class="pt-card">
    <h3>Contatos da empresa (<?= count($contacts) ?>)</h3>
    <?php if ($contacts === []): ?>
        <p class="pt-muted">Nenhum contato cadastrado.</p>
    <?php else: ?>
        <table class="pt-table">
            <thead><tr><th>Nome</th><th>Cargo</th><th>E-mail</th><th>WhatsApp</th></tr></thead>
            <tbody>
            <?php foreach ($contacts as $c): ?>
                <tr>
                    <td><?= e($c['name'] ?? '—') ?></td>
                    <td><?= e($c['position_title'] ?? '—') ?></td>
                    <td><?= e($c['email'] ?? '—') ?></td>
                    <td><?= e($c['whatsapp'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3 style="margin-top:1.2rem">Novo contato</h3>
    <form method="post" action="<?= e(app_url('/portal/companies/' . $companyId . '/contacts')) ?>">
        <?= csrf_field() ?>
        <div class="pt-grid">
            <div class="pt-field">
                <label for="c_name">Nome *</label>
                <input type="text" id="c_name" name="name" required maxlength="160">
            </div>
            <div class="pt-field">
                <label for="c_pos">Cargo</label>
                <input type="text" id="c_pos" name="position_title" maxlength="120">
            </div>
        </div>
        <div class="pt-grid">
            <div class="pt-field">
                <label for="c_email">E-mail</label>
                <input type="email" id="c_email" name="email" maxlength="180">
            </div>
            <div class="pt-field">
                <label for="c_wa">WhatsApp</label>
                <input type="text" id="c_wa" name="whatsapp" maxlength="40">
            </div>
        </div>
        <button type="submit" class="pt-btn"><i data-lucide="user-plus"></i> Cadastrar contato</button>
    </form>
</div>
