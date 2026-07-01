<?php
$items = $items ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">E-mail transacional</span>
                <h1 class="h2-section">Templates</h1>
                <p class="page-sub">Modelos base para os gatilhos da trilha de captadores.</p>
            </div>
            <a href="<?= e(app_url('/settings/email')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Configuracao</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Evento</th><th>Nome</th><th>Assunto</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><span class="pill"><?= e($item['event_key'] ?? '') ?></span></td>
                        <td><?= e($item['name'] ?? '') ?></td>
                        <td><?= e($item['subject'] ?? '') ?></td>
                        <td><?= !empty($item['enabled']) ? 'Ativo' : 'Inativo' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($items === []): ?><tr><td colspan="4">Nenhum template cadastrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="page-sub">Total: <?= $total ?> template(s). Pagina <?= $page ?> de <?= $pages ?>.</p>
    </div>
</section>
