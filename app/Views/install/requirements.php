<?php $checks = $checks ?? []; $canNext = !empty($canNext); ?>
<section class="section install-section">
    <div class="form-box lead-card">
        <h1 class="h2-section">Verificação de requisitos</h1>
        <p class="text-muted">Antes de instalar em produção, confirme que o servidor atende aos requisitos mínimos.</p>

        <table class="table-dcx" style="margin-top:18px;">
            <thead><tr><th>Requisito</th><th>Status</th><th>Orientação</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $c): ?>
                <tr>
                    <td><?= e($c['label']) ?><?= !empty($c['required']) ? ' *' : '' ?></td>
                    <td>
                        <?php if ($c['ok']): ?>
                            <span class="badge badge-lead badge-lead-novo">OK</span>
                        <?php else: ?>
                            <span class="badge badge-lead badge-lead-descartado">Falhou</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm"><?= e($c['hint']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions-row" style="margin-top:22px;">
            <?php if ($canNext): ?>
                <a href="<?= e(app_url('/install/database')) ?>" class="btn btn-yellow">
                    <i data-lucide="arrow-right"></i> Continuar para banco de dados
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline" disabled>Corrija os requisitos para continuar</button>
            <?php endif; ?>
        </div>
    </div>
</section>
