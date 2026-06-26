<?php
/**
 * Lista de perfis.
 *
 * Variaveis: $roles (com users_count, permissions_count)
 */
$roles = $roles ?? [];
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Controle de acesso</span>
                <h1 class="h2-section">Perfis</h1>
                <p class="page-sub">Perfis de acesso e suas permissões vinculadas.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Slug</th>
                        <th>Descrição</th>
                        <th>Usuários</th>
                        <th>Permissões</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): ?>
                        <?php $rid = (int) $r['id']; ?>
                        <tr>
                            <td><strong><?= e($r['name']) ?></strong></td>
                            <td><span class="pill"><?= e($r['slug']) ?></span></td>
                            <td><?= e($r['description'] ?? '') ?></td>
                            <td><?= (int) $r['users_count'] ?></td>
                            <td><?= (int) $r['permissions_count'] ?></td>
                            <td>
                                <div class="actions-row" style="justify-content:flex-end;">
                                    <a href="<?= e(app_url('/roles/' . $rid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                    <?php if (can('roles.edit')): ?>
                                        <a href="<?= e(app_url('/roles/' . $rid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="sliders-horizontal"></i> Permissões</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
