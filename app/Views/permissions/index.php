<?php
/**
 * Lista de permissões.
 *
 * Variaveis: $permissions (linhas)
 */
$permissions = $permissions ?? [];
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Controle de acesso</span>
                <h1 class="h2-section">Permissões</h1>
                <p class="page-sub">Permissões disponíveis no sistema. A atribuição é feita por perfil.</p>
            </div>
        </div>

        <div class="notice" style="margin-bottom:22px;">
            <p class="mb-0"><i data-lucide="info"></i> Permissões <strong>reservadas</strong> existem para preparar módulos futuros do CRM e ainda não possuem telas funcionais.</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Permissão</th><th>Slug</th><th>Descrição</th><th>Tipo</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $p): ?>
                        <?php $reserved = \App\Models\Permission::isReserved((string) $p['slug']); ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><span class="pill"><?= e($p['slug']) ?></span></td>
                            <td><?= e($p['description'] ?? '') ?></td>
                            <td>
                                <?php if ($reserved): ?>
                                    <span class="status status-inactive">Reservada</span>
                                <?php else: ?>
                                    <span class="status status-active">Administrativa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
