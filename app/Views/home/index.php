<?php
/**
 * Tela inicial institucional — base instalada.
 *
 * Variaveis esperadas:
 * - $phpVersion (string)
 * - $dbConnected (bool)
 * - $env (string)
 */
$dbConnected = $dbConnected ?? false;
?>

<!-- HERO -->
<section class="hero">
    <div class="container">
        <div class="hero-inner stack-md">
            <span class="kicker">CRM Cultural de Patrocínio</span>

            <h1 class="h1-hero">Dança Carajás Captação</h1>

            <p class="lead" style="max-width: 640px; color: rgba(255,255,255,.88);">
                Base técnica instalada para operação do sistema de captação de
                patrocínio do Dança Carajás Festival 2026.
            </p>

            <div class="flex flex-wrap gap-12 items-center">
                <a href="/health" class="btn btn-yellow"><i data-lucide="activity"></i>Verificar status</a>
                <a href="#fundacao" class="btn btn-ghost"><i data-lucide="info"></i>Sobre a fundação</a>
            </div>
        </div>
    </div>
</section>

<!-- CARDS DA FUNDAÇÃO -->
<section class="section section-soft" id="fundacao">
    <div class="container stack-md">
        <div class="stack-sm">
            <span class="kicker kicker-dark">Fundação técnica</span>
            <h2 class="h2-section">O que já está pronto</h2>
        </div>

        <div class="grid">
            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="database"></i></span>
                <h3>Stack PHP + MySQL</h3>
                <p>PHP 8.2+ com conexão PDO e prepared statements, sobre MySQL/MariaDB.</p>
            </article>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="layout-grid"></i></span>
                <h3>MVC leve</h3>
                <p>Roteador, controllers, models e views separados, sem dependências pesadas.</p>
            </article>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="shield-check"></i></span>
                <h3>Segurança inicial</h3>
                <p>Sessão segura, CSRF, sanitização de saída, validação e uploads privados.</p>
            </article>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="server"></i></span>
                <h3>Preparado para Hostinger</h3>
                <p>Compatível com hospedagem compartilhada: sem Docker, filas ou workers.</p>
            </article>
        </div>
    </div>
</section>

<!-- STATUS DA INSTALAÇÃO -->
<section class="section">
    <div class="container">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Versão do PHP</td>
                        <td><span class="badge-dcx"><?= e($phpVersion ?? PHP_VERSION) ?></span></td>
                    </tr>
                    <tr>
                        <td>Conexão com o banco</td>
                        <td>
                            <?php if ($dbConnected): ?>
                                <span class="badge-dcx badge-ok">Conectado</span>
                            <?php else: ?>
                                <span class="badge-dcx badge-off">Não conectado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Ambiente</td>
                        <td><span class="badge-dcx"><?= e($env ?? 'production') ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- AVISO INSTITUCIONAL -->
<section class="section section-soft">
    <div class="container">
        <div class="notice">
            <h3 class="h3-card flex items-center gap-12"><i data-lucide="megaphone"></i> Sistema em implantação controlada</h3>
            <p>
                Esta é a base inicial do sistema. Os módulos de CRM serão implantados
                em etapas controladas após validação da fundação técnica.
            </p>
        </div>
    </div>
</section>
