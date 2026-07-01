<?php
$deal = $deal ?? [];
$shares = $shares ?? [];
$statuses = $statuses ?? [];
$collectors = $collectors ?? [];
$sumActive = 0.0;
foreach ($shares as $share) {
    if (empty($share['archived_at'])) {
        $sumActive += (float) ($share['share_percent'] ?? 0);
    }
}
$isShared = (string) ($deal['attribution_type'] ?? '') === 'compartilhada';
$allDraft = $shares !== [];
foreach ($shares as $share) {
    if (empty($share['archived_at']) && (string) ($share['status'] ?? '') !== 'rascunho') {
        $allDraft = false;
    }
}
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Rateio</span>
                <h1 class="h2-section">Rateio da Captacao #<?= (int) ($deal['id'] ?? 0) ?></h1>
                <p class="page-sub"><?= e($deal['company_name'] ?? '') ?> - <?= e($deal['sponsor_name'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/collectors/' . (int) ($deal['collector_id'] ?? 0))) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card">
                <h3 class="h3-card"><i data-lucide="link"></i> Captacao</h3>
                <dl class="meta-list">
                    <dt>Tipo</dt><dd><?= e($deal['attribution_type'] ?? '') ?></dd>
                    <dt>Projeto</dt><dd>#<?= (int) ($deal['incentive_project_id'] ?? 0) ?></dd>
                    <dt>Financeiro</dt><dd>#<?= (int) ($deal['financial_entry_id'] ?? 0) ?></dd>
                    <dt>Soma ativa</dt><dd><strong><?= e(number_format($sumActive, 4, ',', '.')) ?>%</strong></dd>
                </dl>
            </article>

            <article class="card">
                <h3 class="h3-card"><i data-lucide="plus"></i> Novo rateio</h3>
                <?php if ($isShared): ?>
                    <form method="post" action="<?= e(app_url('/collector-deals/' . (int) ($deal['id'] ?? 0) . '/shares')) ?>" class="stack">
                        <?= csrf_field() ?>
                        <label>Captador</label>
                        <select name="collector_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($collectors as $collector): ?>
                                <option value="<?= (int) $collector['id'] ?>"><?= e($collector['label'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Percentual</label>
                        <input type="text" name="share_percent" required placeholder="50,00">
                        <label>Observacao</label>
                        <textarea name="notes" rows="3"></textarea>
                        <button type="submit" class="btn btn-sm btn-yellow">Adicionar</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Rateio disponivel somente para captacao compartilhada.</p>
                <?php endif; ?>
            </article>
        </div>

        <article class="card" style="margin-top:18px;">
            <div class="page-head" style="margin-bottom:12px;">
                <h3 class="h3-card" style="margin:0;"><i data-lucide="split"></i> Participantes</h3>
                <?php if ($isShared && $allDraft && abs(round($sumActive, 4) - 100.0) <= 0.0001): ?>
                    <form method="post" action="<?= e(app_url('/collector-deals/' . (int) ($deal['id'] ?? 0) . '/shares/approve')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-yellow">Aprovar rateio</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Captador</th>
                            <th>Percentual</th>
                            <th>Status</th>
                            <th>Aprovado em</th>
                            <th>Observacao</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shares as $share): ?>
                            <tr>
                                <td><?= e($share['collector_name'] ?? '') ?><br><small><?= e($share['collector_code'] ?? '') ?></small></td>
                                <td><?= e(number_format((float) ($share['share_percent'] ?? 0), 4, ',', '.')) ?>%</td>
                                <td><?= e($statuses[$share['status'] ?? ''] ?? ($share['status'] ?? '')) ?><?= !empty($share['archived_at']) ? ' / arquivado' : '' ?></td>
                                <td><?= e($share['approved_at'] ?? '-') ?></td>
                                <td><?= e($share['notes'] ?? '-') ?></td>
                                <td>
                                    <?php if (empty($share['archived_at']) && ($share['status'] ?? '') === 'rascunho'): ?>
                                        <form method="post" action="<?= e(app_url('/collector-deal-shares/' . (int) $share['id'] . '/update')) ?>" class="stack">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="collector_deal_id" value="<?= (int) ($deal['id'] ?? 0) ?>">
                                            <input type="text" name="share_percent" value="<?= e((string) ($share['share_percent'] ?? '')) ?>" required>
                                            <input type="text" name="notes" value="<?= e((string) ($share['notes'] ?? '')) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline">Atualizar</button>
                                        </form>
                                        <form method="post" action="<?= e(app_url('/collector-deal-shares/' . (int) $share['id'] . '/archive')) ?>" style="margin-top:8px;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="collector_deal_id" value="<?= (int) ($deal['id'] ?? 0) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline">Arquivar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($shares === []): ?>
                            <tr><td colspan="6" class="text-muted">Nenhum rateio cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
