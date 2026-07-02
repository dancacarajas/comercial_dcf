<?php
$items = $items ?? [];
$filters = $filters ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">E-mail transacional</span>
                <h1 class="h2-section">Logs de E-mail</h1>
                <p class="page-sub">Rastreamento de testes, simulacoes, falhas e envios transacionais.</p>
            </div>
            <a href="<?= e(app_url('/settings/email')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Configuracao</a>
        </div>

        <form method="get" class="filters-bar">
            <div><label>Busca</label><input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"></div>
            <div><label>Evento</label><input type="text" name="event_key" value="<?= e($filters['event_key'] ?? '') ?>"></div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (['pending','sent','simulated','skipped','failed'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-actions">
                <button class="btn btn-sm btn-yellow" type="submit"><i data-lucide="search"></i> Filtrar</button>
                <a href="<?= e(app_url('/settings/email/logs')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Data</th><th>Evento</th><th>Destinatario</th><th>Assunto</th><th>Status</th><th>Erro</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['created_at'] ?? '') ?></td>
                        <td><span class="pill"><?= e($item['event_key'] ?? '') ?></span></td>
                        <td><?= e($item['recipient_name'] ?? '') ?><br><small><?= e($item['recipient_email'] ?? '') ?></small></td>
                        <td><?= e($item['subject'] ?? '') ?></td>
                        <td><?= e($item['status'] ?? '') ?></td>
                        <td><?= e($item['error_message'] ?? '') ?></td>
                        <td>
                            <?php if ((can('email_logs.resend') || can('email_settings.test')) && (int) ($item['outbox_id'] ?? 0) > 0): ?>
                                <form method="post" action="<?= e(app_url('/settings/email/outbox/' . (int) $item['outbox_id'] . '/resend')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-xs btn-outline"><i data-lucide="send"></i> Reenviar</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($items === []): ?><tr><td colspan="7">Nenhum log encontrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="page-sub">Total: <?= $total ?> log(s). Pagina <?= $page ?> de <?= $pages ?>.</p>
    </div>
</section>
